<?php

namespace App\Services\Purchases;

use App\Models\BusinessTransaction;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Models\SupplierAccountEntry;
use App\Support\TransactionUnits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PurchaseApprovalService
{
    public function approve(PurchaseInvoice $invoice): PurchaseInvoice
    {
        $approved = DB::transaction(function () use ($invoice): PurchaseInvoice {
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
            $supplier = Supplier::query()
                ->whereKey($invoice->supplier_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $supplier->is_active) {
                throw ValidationException::withMessages([
                    'supplier_id' => __('catalog.errors.supplier_inactive'),
                ]);
            }

            $lines = PurchaseLine::query()
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

            foreach ($lines as $line) {
                $product = $products->get($line->product_id);
                $factor = $product
                    ? TransactionUnits::conversionFactor($product, $line->input_unit)
                    : null;

                if ($factor === null
                    || bccomp($factor, (string) $line->conversion_factor, 6) !== 0
                    || bccomp((string) $line->base_quantity, bcmul(
                        (string) $line->entered_quantity,
                        $factor,
                        3,
                    ), 3) !== 0
                    || $product->base_unit === null) {
                    throw ValidationException::withMessages([
                        'lines' => __('catalog.errors.invalid_product_unit'),
                    ]);
                }
            }

            $total = (string) DB::table('purchase_lines')
                ->where('transaction_id', $invoice->transaction_id)
                ->selectRaw('COALESCE(SUM(line_total), 0)::numeric(19, 2) AS total')
                ->value('total');

            if (bccomp($total, (string) $invoice->invoice_total, 2) !== 0) {
                throw ValidationException::withMessages([
                    'invoice_total' => __('catalog.errors.invoice_total_mismatch'),
                ]);
            }

            if (bccomp($total, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'invoice_total' => __('catalog.errors.purchase_total_must_be_positive'),
                ]);
            }

            if (SupplierAccountEntry::query()
                ->where('source_purchase_transaction_id', $invoice->transaction_id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'purchase' => __('catalog.errors.purchase_already_posted'),
                ]);
            }

            $transaction->status = 'approved';
            $transaction->approved_by = auth()->id();
            $transaction->approved_at = now();
            $transaction->save();

            foreach ($lines as $line) {
                $product = $products->get($line->product_id);
                DB::table('inventory_movements')->insert([
                    'transaction_id' => $invoice->transaction_id,
                    'product_id' => $product->getKey(),
                    'movement_type' => 'purchase',
                    'quantity_delta' => $line->base_quantity,
                    'base_unit' => $product->base_unit->value,
                    'source_id' => $line->getKey(),
                ]);
            }

            SupplierAccountEntry::query()->create([
                'transaction_id' => $invoice->transaction_id,
                'supplier_id' => $supplier->getKey(),
                'source_purchase_transaction_id' => $invoice->transaction_id,
                'currency_code' => 'YER',
                'debit' => '0.00',
                'credit' => $total,
                'description' => __('catalog.messages.purchase_ledger_description', [
                    'reference' => $invoice->invoice_number,
                ]),
            ]);

            return $invoice->fresh(['supplier', 'businessTransaction', 'lines.product']);
        });

        Log::info('Purchase invoice approved', [
            'transaction_id' => $approved->transaction_id,
            'supplier_id' => $approved->supplier_id,
            'invoice_total' => $approved->invoice_total,
            'actor_user_id' => auth()->id(),
        ]);

        return $approved;
    }
}
