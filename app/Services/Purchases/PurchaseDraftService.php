<?php

namespace App\Services\Purchases;

use App\Models\BusinessTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Support\TransactionUnits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseDraftService
{
    public function create(array $data): PurchaseInvoice
    {
        $invoice = DB::transaction(function () use ($data): PurchaseInvoice {
            $supplier = $this->lockSupplier($data['supplier_id']);
            $products = $this->lockProducts($data['lines']);
            $transactionId = (string) Str::uuid();

            DB::table('business_transactions')->insert([
                'id' => $transactionId,
                'transaction_type' => 'purchase',
                'status' => 'draft',
                'occurred_at' => $data['document_date'].' 00:00:00',
                'created_by' => auth()->id(),
                'notes' => $this->nullableText($data['notes'] ?? null),
            ]);

            $invoice = PurchaseInvoice::query()->create([
                'transaction_id' => $transactionId,
                'supplier_invoice_number' => $this->nullableText($data['supplier_invoice_number'] ?? null),
                'supplier_id' => $supplier->getKey(),
                'currency_code' => 'YER',
                'invoice_total' => '0.00',
            ]);

            $this->replaceLines($invoice, $data['lines'], $products);
            $this->setInvoiceTotal($invoice);

            return $invoice;
        });

        Log::info('Purchase draft created', [
            'transaction_id' => $invoice->transaction_id,
            'supplier_id' => $invoice->supplier_id,
            'actor_user_id' => auth()->id(),
        ]);

        return $invoice;
    }

    public function update(PurchaseInvoice $invoice, array $data): PurchaseInvoice
    {
        $updated = DB::transaction(function () use ($invoice, $data): PurchaseInvoice {
            $transaction = BusinessTransaction::query()
                ->whereKey($invoice->transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->transaction_type !== 'purchase' || $transaction->status !== 'draft') {
                throw ValidationException::withMessages([
                    'purchase' => __('catalog.errors.transaction_not_draft'),
                ]);
            }

            $invoice = PurchaseInvoice::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $supplier = $this->lockSupplier($data['supplier_id']);
            $products = $this->lockProducts($data['lines']);

            $transaction->occurred_at = $data['document_date'].' 00:00:00';
            $transaction->notes = $this->nullableText($data['notes'] ?? null);
            $transaction->save();
            $invoice->supplier_id = $supplier->getKey();
            $invoice->supplier_invoice_number = $this->nullableText($data['supplier_invoice_number'] ?? null);
            $invoice->save();

            $invoice->lines()->delete();
            $this->replaceLines($invoice, $data['lines'], $products);
            $this->setInvoiceTotal($invoice);

            return $invoice;
        });

        Log::info('Purchase draft updated', [
            'transaction_id' => $updated->transaction_id,
            'supplier_id' => $updated->supplier_id,
            'actor_user_id' => auth()->id(),
        ]);

        return $updated;
    }

    private function lockSupplier(string $supplierId): Supplier
    {
        $supplier = Supplier::query()->whereKey($supplierId)->lockForUpdate()->firstOrFail();

        if (! $supplier->is_active) {
            throw ValidationException::withMessages([
                'supplier_id' => __('catalog.errors.supplier_inactive'),
            ]);
        }

        return $supplier;
    }

    private function lockProducts(array $lines): Collection
    {
        $productIds = collect($lines)->pluck('product_id')->unique()->sort()->values();
        $products = Product::query()
            ->with('category')
            ->whereIn('id', $productIds)
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

            if (TransactionUnits::conversionFactor($product, $line['input_unit']) === null) {
                throw ValidationException::withMessages([
                    "lines.{$index}.input_unit" => __('catalog.errors.invalid_product_unit'),
                ]);
            }
        }

        return $products;
    }

    private function replaceLines(
        PurchaseInvoice $invoice,
        array $lines,
        Collection $products,
    ): void {
        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);
            $factor = TransactionUnits::conversionFactor($product, $line['input_unit']);

            $invoice->lines()->create([
                'product_id' => $product->getKey(),
                'input_unit' => $line['input_unit'],
                'conversion_factor' => $factor,
                'entered_quantity' => $line['entered_quantity'],
                'purchase_unit_price' => $line['purchase_unit_price'],
            ]);
        }
    }

    private function setInvoiceTotal(PurchaseInvoice $invoice): void
    {
        $total = DB::table('purchase_lines')
            ->where('transaction_id', $invoice->transaction_id)
            ->selectRaw('COALESCE(SUM(line_total), 0)::numeric(19, 2) AS total')
            ->value('total');

        $invoice->invoice_total = (string) $total;
        $invoice->save();
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
