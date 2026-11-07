<?php

namespace App\Http\Requests\OpeningInventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpeningInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'effective_at' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['required', 'uuid', 'distinct', Rule::exists('products', 'id')],
            'lines.*.entered_quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'lines.*.input_unit' => ['required', Rule::in(['KG', 'GALLON', 'CAN'])],
        ];
    }
}
