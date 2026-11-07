<?php

namespace App\Services\OpeningInventory;

use App\Models\BusinessTransaction;
use App\Models\OpeningInventory;
use App\Models\Product;
use App\Support\TransactionUnits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OpeningInventoryService
{
    public function create(array $data): OpeningInventory
    {
        $document = DB::transaction(function () use ($data): OpeningInventory {
            $this->ensureNoApprovedOpening();
            $products = $this->lockProducts($data['lines']);
            $transactionId = (string) Str::uuid();

            DB::table('business_transactions')->insert([
                'id' => $transactionId,
                'transaction_type' => 'opening_inventory',
                'status' => 'draft',
                'occurred_at' => $data['effective_at'].' 00:00:00',
                'created_by' => auth()->id(),
                'notes' => $this->nullableText($data['notes'] ?? null),
            ]);

            $document = OpeningInventory::query()->create([
                'transaction_id' => $transactionId,
                'effective_at' => $data['effective_at'].' 00:00:00',
            ]);
            $this->replaceLines($document, $data['lines'], $products);

            return $document;
        });

        Log::info('Opening inventory draft created', [
            'transaction_id' => $document->transaction_id,
            'actor_user_id' => auth()->id(),
        ]);

        return $document;
    }

    public function update(OpeningInventory $document, array $data): OpeningInventory
    {
        $updated = DB::transaction(function () use ($document, $data): OpeningInventory {
            $transaction = BusinessTransaction::query()
                ->whereKey($document->transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->transaction_type !== 'opening_inventory' || $transaction->status !== 'draft') {
                throw ValidationException::withMessages([
                    'opening_inventory' => __('catalog.errors.transaction_not_draft'),
                ]);
            }

            $document = OpeningInventory::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $products = $this->lockProducts($data['lines']);

            $transaction->occurred_at = $data['effective_at'].' 00:00:00';
            $transaction->notes = $this->nullableText($data['notes'] ?? null);
            $transaction->save();
            $document->effective_at = $data['effective_at'].' 00:00:00';
            $document->save();
            $document->lines()->delete();
            $this->replaceLines($document, $data['lines'], $products);

            return $document;
        });

        Log::info('Opening inventory draft updated', [
            'transaction_id' => $updated->transaction_id,
            'actor_user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function approve(OpeningInventory $document): OpeningInventory
    {
        $approved = DB::transaction(function () use ($document): OpeningInventory {
            $transaction = BusinessTransaction::query()
                ->whereKey($document->transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->transaction_type !== 'opening_inventory' || $transaction->status !== 'draft') {
                throw ValidationException::withMessages([
                    'opening_inventory' => __('catalog.errors.transaction_not_draft'),
                ]);
            }

            $document = OpeningInventory::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lines = $document->lines()
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => __('catalog.errors.document_requires_lines'),
                ]);
            }

            $this->ensureNoApprovedOpening($document->transaction_id);
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
                    || bccomp((string) $line->quantity, bcmul(
                        (string) $line->entered_quantity,
                        $factor,
                        3,
                    ), 3) !== 0) {
                    throw ValidationException::withMessages([
                        'lines' => __('catalog.errors.invalid_product_unit'),
                    ]);
                }
            }

            $transaction->status = 'approved';
            $transaction->approved_by = auth()->id();
            $transaction->approved_at = now();
            $transaction->save();

            foreach ($lines as $line) {
                $product = $products->get($line->product_id);
                DB::table('inventory_movements')->insert([
                    'transaction_id' => $document->transaction_id,
                    'product_id' => $product->getKey(),
                    'movement_type' => 'opening_balance',
                    'quantity_delta' => $line->quantity,
                    'base_unit' => $product->base_unit->value,
                    'source_id' => $line->getKey(),
                ]);
            }

            return $document->fresh(['businessTransaction', 'lines.product']);
        });

        Log::info('Opening inventory approved', [
            'transaction_id' => $approved->transaction_id,
            'actor_user_id' => auth()->id(),
        ]);

        return $approved;
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
        OpeningInventory $document,
        array $lines,
        Collection $products,
    ): void {
        foreach ($lines as $line) {
            $product = $products->get($line['product_id']);
            $factor = TransactionUnits::conversionFactor($product, $line['input_unit']);

            $document->lines()->create([
                'product_id' => $product->getKey(),
                'quantity' => TransactionUnits::baseQuantity(
                    $line['entered_quantity'],
                    $factor,
                ),
                'entered_quantity' => $line['entered_quantity'],
                'input_unit' => $line['input_unit'],
                'conversion_factor' => $factor,
            ]);
        }
    }

    private function ensureNoApprovedOpening(?string $exceptId = null): void
    {
        if ($exceptId !== null) {
            DB::select(
                'SELECT pg_advisory_xact_lock(hashtext(?))',
                ['honey_shop.opening_inventory.approval'],
            );
        }

        $approvedExists = DB::table('opening_inventory')
            ->join(
                'business_transactions',
                'business_transactions.id',
                '=',
                'opening_inventory.transaction_id',
            )
            ->where('business_transactions.transaction_type', 'opening_inventory')
            ->where('business_transactions.status', 'approved')
            ->when($exceptId, fn ($query) => $query->where('business_transactions.id', '<>', $exceptId))
            ->exists();

        if ($approvedExists) {
            throw ValidationException::withMessages([
                'opening_inventory' => __('catalog.errors.opening_inventory_already_approved'),
            ]);
        }
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
