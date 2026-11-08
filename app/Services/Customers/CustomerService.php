<?php

namespace App\Services\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    public function create(array $data): Customer
    {
        $customer = DB::transaction(
            fn (): Customer => Customer::query()->create($this->attributes($data)),
        );

        Log::info('Customer created', [
            'customer_id' => $customer->getKey(),
            'actor_user_id' => auth()->id(),
        ]);

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $updated = DB::transaction(function () use ($customer, $data): Customer {
            $customer = Customer::query()->whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $customer->fill($this->attributes($data));
            $customer->save();

            return $customer;
        });

        Log::info('Customer updated', [
            'customer_id' => $updated->getKey(),
            'actor_user_id' => auth()->id(),
        ]);

        return $updated;
    }

    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'phone' => $this->nullableText($data['phone'] ?? null),
            'country' => $this->nullableText($data['country'] ?? null),
            'city' => $this->nullableText($data['city'] ?? null),
            'address' => $this->nullableText($data['address'] ?? null),
            'is_active' => (bool) $data['is_active'],
            'notes' => $this->nullableText($data['notes'] ?? null),
        ];
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
