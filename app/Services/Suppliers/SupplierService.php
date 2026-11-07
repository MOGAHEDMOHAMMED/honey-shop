<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function create(array $data): Supplier
    {
        try {
            $supplier = DB::transaction(function () use ($data): Supplier {
                $type = SupplierType::query()
                    ->whereKey($data['supplier_type_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $type->is_active) {
                    throw ValidationException::withMessages([
                        'supplier_type_id' => __('catalog.errors.supplier_type_inactive'),
                    ]);
                }

                $this->ensureUniqueName($type, $data['name']);

                return Supplier::query()->create($this->attributes($data));
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateNameValidation($exception);
        }

        Log::info('Supplier created', [
            'supplier_id' => $supplier->getKey(),
            'supplier_type_id' => $supplier->supplier_type_id,
            'actor_user_id' => auth()->id(),
        ]);

        return $supplier;
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        try {
            $updated = DB::transaction(function () use ($supplier, $data): Supplier {
                $supplier = Supplier::query()
                    ->whereKey($supplier->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $type = SupplierType::query()
                    ->whereKey($data['supplier_type_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $type->is_active && $supplier->supplier_type_id !== $type->getKey()) {
                    throw ValidationException::withMessages([
                        'supplier_type_id' => __('catalog.errors.supplier_type_inactive'),
                    ]);
                }

                if ($supplier->supplier_type_id !== $type->getKey()
                    && ($supplier->purchases()->exists() || $supplier->accountEntries()->exists())) {
                    throw ValidationException::withMessages([
                        'supplier_type_id' => __('catalog.errors.supplier_type_has_history'),
                    ]);
                }

                $this->ensureUniqueName($type, $data['name'], $supplier->getKey());
                $supplier->fill($this->attributes($data));
                $supplier->save();

                return $supplier;
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateNameValidation($exception);
        }

        Log::info('Supplier updated', [
            'supplier_id' => $updated->getKey(),
            'actor_user_id' => auth()->id(),
        ]);

        return $updated;
    }

    private function ensureUniqueName(
        SupplierType $type,
        string $name,
        ?string $ignoreId = null,
    ): void {
        $duplicate = Supplier::query()
            ->where('supplier_type_id', $type->getKey())
            ->whereRaw('LOWER(BTRIM(name)) = LOWER(BTRIM(?))', [$name])
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => __('catalog.errors.duplicate_supplier'),
            ]);
        }
    }

    private function attributes(array $data): array
    {
        return [
            'supplier_type_id' => $data['supplier_type_id'],
            'name' => trim($data['name']),
            'phone' => $data['phone'] ? trim($data['phone']) : null,
            'address' => $data['address'] ? trim($data['address']) : null,
            'notes' => $data['notes'] ? trim($data['notes']) : null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    private function throwDuplicateNameValidation(QueryException $exception): never
    {
        if ($exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'suppliers_type_normalized_name_key')) {
            throw ValidationException::withMessages([
                'name' => __('catalog.errors.duplicate_supplier'),
            ]);
        }

        throw $exception;
    }
}
