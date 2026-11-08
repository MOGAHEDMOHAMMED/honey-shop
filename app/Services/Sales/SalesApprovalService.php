<?php

namespace App\Services\Sales;

use App\Models\BusinessTransaction;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesLine;
use App\Support\Money;
use App\Support\TransactionUnits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SalesApprovalService
{
    public function approve(SalesInvoice $invoice, ?string $requestedSeries): SalesInvoice
    {
        $approved = DB::transaction(function () use ($invoice, $requestedSeries): SalesInvoice {
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
            $customer = Customer::query()
                ->whereKey($invoice->customer_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    'customer_id' => __('catalog.errors.customer_inactive'),
                ]);
            }

            $lines = SalesLine::query()
                ->where('transaction_id', $invoice->transaction_id)
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => __('catalog.errors.document_requires_lines'),
                ]);
            }

            $products = Product::query()
                ->with('category')
                ->whereIn('id', $lines->pluck('product_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $categories = [];

            foreach ($lines as $line) {
                $product = $products->get($line->product_id);
                $factor = $product
                    ? TransactionUnits::conversionFactor($product, $line->input_unit)
                    : null;
                if (! $product || ! $product->is_active || $factor === null
                    || bccomp($factor, (string) $line->conversion_factor, 6) !== 0
                    || bccomp((string) $line->base_quantity, bcmul(
                        (string) $line->entered_quantity,
                        $factor,
                        3,
                    ), 3) !== 0) {
                    throw ValidationException::withMessages([
                        'lines' => __('catalog.errors.invalid_product_unit'),
                    ]);
                }

                if (bccomp((string) $line->discount_rate, (string) $product->max_discount_rate, 6) > 0) {
                    throw ValidationException::withMessages([
                        'lines' => __('catalog.errors.discount_exceeds_product_maximum'),
                    ]);
                }

                $gross = Money::roundHalfUp(bcmul(
                    (string) $line->entered_quantity,
                    (string) $line->unit_price,
                    10,
                ));
                $net = Money::roundHalfUp(bcmul(
                    bcmul(
                        (string) $line->entered_quantity,
                        (string) $line->unit_price,
                        10,
                    ),
                    bcsub('1', (string) $line->discount_rate, 6),
                    10,
                ));
                $discount = bcsub($gross, $net, 2);

                if (bccomp($discount, (string) $line->discount_amount, 2) !== 0
                    || bccomp((string) $line->line_total, $net, 2) !== 0) {
                    throw ValidationException::withMessages([
                        'lines' => __('catalog.errors.sale_line_total_mismatch'),
                    ]);
                }

                $categories[mb_strtolower(trim((string) $product->category->name))] = true;
            }

            $series = $this->seriesCategory(array_keys($categories), $requestedSeries);
            $stock = DB::table('inventory_balances')
                ->whereIn('product_id', $lines->pluck('product_id')->unique())
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($lines as $line) {
                $available = (string) ($stock->get($line->product_id)->quantity ?? '0');
                if (bccomp($available, (string) $line->base_quantity, 3) < 0) {
                    throw ValidationException::withMessages([
                        'lines' => __('catalog.errors.insufficient_stock', [
                            'product' => $products->get($line->product_id)->name,
                        ]),
                    ]);
                }
            }

            $totals = DB::table('sales_lines')
                ->where('transaction_id', $invoice->transaction_id)
                ->selectRaw('COALESCE(SUM(ROUND(entered_quantity * unit_price, 2)), 0)::numeric(19, 2) AS gross')
                ->selectRaw('COALESCE(SUM(discount_amount), 0)::numeric(19, 2) AS discounts')
                ->first();
            $net = bcsub((string) $totals->gross, (string) $totals->discounts, 2);
            $finalTotal = bcadd($net, (string) $invoice->delivery_charge, 2);

            if (bccomp((string) $invoice->products_subtotal, (string) $totals->gross, 2) !== 0
                || bccomp((string) $invoice->discounts_total, (string) $totals->discounts, 2) !== 0
                || bccomp((string) $invoice->final_total, $finalTotal, 2) !== 0) {
                throw ValidationException::withMessages([
                    'invoice_total' => __('catalog.errors.invoice_total_mismatch'),
                ]);
            }

            if (bccomp($finalTotal, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'invoice_total' => __('catalog.errors.sale_total_must_be_positive'),
                ]);
            }

            if (CustomerAccountEntry::query()
                ->where('source_sale_transaction_id', $invoice->transaction_id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'sale' => __('catalog.errors.sale_already_posted'),
                ]);
            }

            $number = DB::selectOne(
                'UPDATE sales_invoice_counters
                 SET last_issued_suffix = last_issued_suffix + 1
                 WHERE series_category = ?
                 RETURNING prefix, last_issued_suffix',
                [$series],
            );
            if (! $number) {
                throw new \LogicException('The sales invoice series counter is missing.');
            }

            $invoice->invoice_number = $number->prefix.$number->last_issued_suffix;
            $invoice->series_category = $series;
            $invoice->order_status = $invoice->sales_channel === 'external' ? 'shipped' : null;
            $invoice->save();

            $transaction->status = 'approved';
            $transaction->approved_by = auth()->id();
            $transaction->approved_at = now();
            $transaction->save();

            foreach ($lines as $line) {
                $product = $products->get($line->product_id);
                DB::table('inventory_movements')->insert([
                    'transaction_id' => $invoice->transaction_id,
                    'product_id' => $product->getKey(),
                    'movement_type' => 'sale',
                    'quantity_delta' => bcsub('0', (string) $line->base_quantity, 3),
                    'base_unit' => $product->base_unit->value,
                    'source_id' => $line->getKey(),
                ]);
            }

            CustomerAccountEntry::query()->create([
                'transaction_id' => $invoice->transaction_id,
                'source_sale_transaction_id' => $invoice->transaction_id,
                'customer_id' => $customer->getKey(),
                'currency_code' => 'YER',
                'debit' => $finalTotal,
                'credit' => '0.00',
                'description' => __('catalog.messages.sale_ledger_description', [
                    'reference' => $invoice->invoice_number,
                ]),
            ]);

            return $invoice->fresh(['customer', 'businessTransaction', 'lines.product']);
        });

        Log::info('Sales invoice approved', [
            'transaction_id' => $approved->transaction_id,
            'invoice_number' => $approved->invoice_number,
            'customer_id' => $approved->customer_id,
            'invoice_total' => $approved->final_total,
            'actor_user_id' => auth()->id(),
        ]);

        return $approved;
    }

    private function seriesCategory(array $categories, ?string $requested): string
    {
        if (count($categories) === 1) {
            return match ($categories[0]) {
                'honey' => 'honey',
                'ghee' => 'ghee',
                'nuts' => 'nuts',
                default => throw ValidationException::withMessages([
                    'lines' => __('catalog.errors.unsupported_category'),
                ]),
            };
        }

        if (! in_array($requested, ['honey', 'ghee', 'nuts'], true)) {
            throw ValidationException::withMessages([
                'series_category' => __('catalog.errors.mixed_sale_series_required'),
            ]);
        }

        return $requested;
    }
}
