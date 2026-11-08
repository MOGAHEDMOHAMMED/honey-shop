<?php

namespace App\Http\Requests\Purchases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid', Rule::exists('suppliers', 'id')],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'document_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'uuid', Rule::exists('products', 'id')],
            'lines.*.entered_quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'lines.*.input_unit' => ['required', Rule::in(['KG', 'GALLON', 'CAN'])],
            'lines.*.purchase_unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }
}
