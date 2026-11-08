<?php

namespace App\Services\Sales;

use App\Models\BusinessTransaction;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Support\Money;
use App\Support\TransactionUnits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesDraftService
{
    public function create(array $data): SalesInvoice
    {
        $invoice = DB::transaction(function () use ($data): SalesInvoice {
            $customer = $this->lockCustomer($data['customer_id']);
            $products = $this->lockProducts($data['lines']);
            $transactionId = (string) Str::uuid();
            $external = $data['sales_channel'] === 'external';

            DB::table('business_transactions')->insert([
                'id' => $transactionId,
                'transaction_type' => 'sale',
                'status' => 'draft',
                'occurred_at' => $data['document_date'].' 00:00:00',
                'created_by' => auth()->id(),
                'notes' => $this->nullableText($data['notes'] ?? null),
            ]);

            $invoice = SalesInvoice::query()->create([
                'transaction_id' => $transactionId,
                'invoice_number' => 'DRAFT-'.strtoupper($transactionId),
                'customer_id' => $customer->getKey(),
                'sales_channel' => $data['sales_channel'],
                'currency_code' => 'YER',
                'delivery_provider_id' => $external ? ($data['delivery_provider_id'] ?? null) : null,
                'representative_id' => $external ? ($data['representative_id'] ?? null) : null,
                'order_status' => $external ? 'pending_shipment' : null,
                'products_subtotal' => '0.00',
                'discounts_total' => '0.00',
                'delivery_charge' => $external ? ($data['delivery_charge'] ?? '0.00') : '0.00',
                'final_total' => '0.00',
                'delivery_cost_bearer' => $external
                    ? ($data['delivery_cost_bearer'] ?? 'shop')
                    : 'shop',
            ]);

            $this->replaceLines($invoice, $data['lines'], $products);
            $this->calculateTotals($invoice);

            return $invoice;
        });

        Log::info('Sales draft created', [
            'transaction_id' => $invoice->transaction_id,
            'customer_id' => $invoice->customer_id,
            'sales_channel' => $invoice->sales_channel,
            'actor_user_id' => auth()->id(),
        ]);

        return $invoice;
    }

    public function update(SalesInvoice $invoice, array $data): SalesInvoice
    {
        $updated = DB::transaction(function () use ($invoice, $data): SalesInvoice {
            $transaction = BusinessTransaction::query()
                ->whereKey($invoice->transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->transaction_type !== 'sale' || $transaction->status !== 'draft') {
                throw ValidationException::withMessages([
                    'sale' => __('catalog.errors.transaction_not_draft'),
                ]);
            }

            $invoice = SalesInvoice::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $customer = $this->lockCustomer($data['customer_id']);
            $products = $this->lockProducts($data['lines']);
            $external = $data['sales_channel'] === 'external';

            $transaction->occurred_at = $data['document_date'].' 00:00:00';
            $transaction->notes = $this->nullableText($data['notes'] ?? null);
            $transaction->save();

            $invoice->customer_id = $customer->getKey();
            $invoice->sales_channel = $data['sales_channel'];
            $invoice->delivery_provider_id = $external ? ($data['delivery_provider_id'] ?? null) : null;
            $invoice->representative_id = $external ? ($data['representative_id'] ?? null) : null;
            $invoice->order_status = $external ? 'pending_shipment' : null;
            $invoice->delivery_charge = $external ? ($data['delivery_charge'] ?? '0.00') : '0.00';
            $invoice->delivery_cost_bearer = $external
                ? ($data['delivery_cost_bearer'] ?? 'shop')
                : 'shop';
            $invoice->save();

            $invoice->lines()->delete();
            $this->replaceLines($invoice, $data['lines'], $products);
            $this->calculateTotals($invoice);

            return $invoice;
        });

        Log::info('Sales draft updated', [
            'transaction_id' => $updated->transaction_id,
            'customer_id' => $updated->customer_id,
            'actor_user_id' => auth()->id(),
        ]);

        return $updated;
    }

    private function lockCustomer(string $customerId): Customer
    {
        $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => __('catalog.errors.customer_inactive'),
            ]);
        }

        return $customer;
    }

    private function lockProducts(array $lines): Collection
    {
        $ids = collect($lines)->pluck('product_id')->unique()->sort()->values();
        $products = Product::query()
            ->with('category')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($lines as $index => $line) {
            $product = $products->get($line['product_id']);
            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => __('catalog.errors.product_inactive'),
                ]);
            }

            $factor = TransactionUnits::conversionFactor($product, $line['input_unit']);
            if ($factor === null) {
                throw ValidationException::withMessages([
                    "lines.{$index}.input_unit" => __('catalog.errors.invalid_product_unit'),
                ]);
            }

            $rate = bcdiv((string) ($line['discount_percentage'] ?? '0'), '100', 6);
            if (bccomp($rate, (string) $product->max_discount_rate, 6) > 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.discount_percentage" => __('catalog.errors.discount_exceeds_product_maximum'),
                ]);
            }
        }

        return $products;
    }

    private function replaceLines(SalesInvoice $invoice, array $lines, Collection $products): void
    {
        foreach ($lines as $index => $line) {
            $product = $products->get($line['product_id']);
            $factor = TransactionUnits::conversionFactor($product, $line['input_unit']);
            $quantity = (string) $line['entered_quantity'];
            $price = (string) $line['unit_price'];
            $rate = bcdiv((string) ($line['discount_percentage'] ?? '0'), '100', 6);
            $gross = Money::roundHalfUp(bcmul($quantity, $price, 10));
            $netRaw = bcmul(
                bcmul($quantity, $price, 10),
                bcsub('1', $rate, 6),
                10,
            );
            $net = Money::roundHalfUp($netRaw);
            $discount = bcsub($gross, $net, 2);

            if (bccomp($discount, '0', 2) < 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.discount_percentage" => __('catalog.errors.invalid_discount'),
                ]);
            }

            $invoice->lines()->create([
                'product_id' => $product->getKey(),
                'entered_quantity' => $quantity,
                'input_unit' => $line['input_unit'],
                'conversion_factor' => $factor,
                'unit_price' => $price,
                'discount_rate' => $rate,
                'discount_amount' => $discount,
            ]);
        }
    }

    private function calculateTotals(SalesInvoice $invoice): void
    {
        $totals = DB::table('sales_lines')
            ->where('transaction_id', $invoice->transaction_id)
            ->selectRaw('COALESCE(SUM(ROUND(entered_quantity * unit_price, 2)), 0)::numeric(19, 2) AS gross')
            ->selectRaw('COALESCE(SUM(discount_amount), 0)::numeric(19, 2) AS discounts')
            ->first();

        $net = bcsub((string) $totals->gross, (string) $totals->discounts, 2);
        $invoice->products_subtotal = (string) $totals->gross;
        $invoice->discounts_total = (string) $totals->discounts;
        $invoice->final_total = bcadd($net, (string) $invoice->delivery_charge, 2);
        $invoice->save();
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
