<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', Rule::exists('customers', 'id')],
            'sales_channel' => ['required', Rule::in(['direct', 'external'])],
            'document_date' => ['required', 'date_format:Y-m-d'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'delivery_cost_bearer' => ['required_if:sales_channel,external', 'nullable', Rule::in(['shop', 'customer', 'shared'])],
            'delivery_provider_id' => ['nullable', 'uuid', Rule::exists('delivery_providers', 'id')],
            'representative_id' => ['nullable', 'uuid', Rule::exists('representatives', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.product_id' => ['required', 'uuid', Rule::exists('products', 'id')],
            'lines.*.entered_quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'lines.*.input_unit' => ['required', Rule::in(['KG', 'GALLON', 'CAN'])],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('sales_channel') === 'direct') {
                    if ($this->hasNonZeroDeliveryCharge()) {
                        $validator->errors()->add('delivery_charge', __('catalog.errors.direct_sale_delivery'));
                    }

                    return;
                }

                if ($this->input('sales_channel') === 'external'
                    && $this->input('delivery_cost_bearer') === 'shop'
                    && $this->hasNonZeroDeliveryCharge()) {
                    $validator->errors()->add('delivery_charge', __('catalog.errors.shop_delivery_charge'));
                }
            },
        ];
    }

    private function hasNonZeroDeliveryCharge(): bool
    {
        $charge = $this->input('delivery_charge', '0');
        if (! is_string($charge) && ! is_int($charge) && ! is_float($charge)) {
            return false;
        }
        $charge = (string) $charge;

        return is_numeric($charge)
            && preg_match('/^\d+(?:\.\d{1,2})?$/', $charge) === 1
            && bccomp($charge, '0', 2) !== 0;
    }
}
