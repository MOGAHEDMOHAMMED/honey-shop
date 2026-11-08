<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'uuid', Rule::exists('product_categories', 'id')],
            'minimum_stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'selling_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'max_discount_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'price_change_reason' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
