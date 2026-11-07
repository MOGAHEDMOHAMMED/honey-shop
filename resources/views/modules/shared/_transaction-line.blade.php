@php
    $selectedProduct = old("lines.{$index}.product_id", $line['product_id'] ?? '');
    $enteredQuantity = old("lines.{$index}.entered_quantity", $line['entered_quantity'] ?? '');
    $selectedUnit = old("lines.{$index}.input_unit", $line['input_unit'] ?? '');
    $purchaseUnitPrice = old("lines.{$index}.purchase_unit_price", $line['purchase_unit_price'] ?? '');
@endphp
<div class="transaction-line border rounded p-3 mb-3" data-transaction-line>
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
            <label for="line-product-{{ $index }}" class="form-label">
                {{ __('catalog.fields.product') }} <span class="required-mark">*</span>
            </label>
            <select id="line-product-{{ $index }}" name="lines[{{ $index }}][product_id]"
                    class="form-select" data-line-product required>
                <option value="">{{ __('catalog.fields.choose_product') }}</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}"
                            data-category="{{ mb_strtolower($product->category->name) }}"
                            data-base-unit="{{ $product->base_unit->value }}"
                            @selected((string) $selectedProduct === (string) $product->id)>
                        {{ $product->name }} — {{ __('catalog.categories.'.$product->category->name) }}
                    </option>
                @endforeach
            </select>
            @error("lines.{$index}.product_id")
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-6 col-md-2">
            <label for="line-quantity-{{ $index }}" class="form-label">
                {{ __('catalog.fields.entered_quantity') }} <span class="required-mark">*</span>
            </label>
            <input id="line-quantity-{{ $index }}" name="lines[{{ $index }}][entered_quantity]"
                   type="number" min="0.001" step="0.001" value="{{ $enteredQuantity }}"
                   class="form-control" required>
            @error("lines.{$index}.entered_quantity")
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-6 col-md-2">
            <label for="line-unit-{{ $index }}" class="form-label">
                {{ __('catalog.fields.input_unit') }} <span class="required-mark">*</span>
            </label>
            <select id="line-unit-{{ $index }}" name="lines[{{ $index }}][input_unit]"
                    class="form-select" data-line-unit required>
                <option value="">{{ __('catalog.fields.choose_unit') }}</option>
                @foreach (['KG', 'GALLON', 'CAN'] as $unit)
                    <option value="{{ $unit }}" @selected($selectedUnit === $unit)>
                        {{ __('catalog.units.'.$unit) }}
                    </option>
                @endforeach
            </select>
            @error("lines.{$index}.input_unit")
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        @if ($showCost)
            <div class="col-6 col-md-2">
                <label for="line-cost-{{ $index }}" class="form-label">
                    {{ __('catalog.fields.unit_cost') }} <span class="required-mark">*</span>
                </label>
                <div class="input-group">
                    <input id="line-cost-{{ $index }}" name="lines[{{ $index }}][purchase_unit_price]"
                           type="number" min="0" step="0.01" value="{{ $purchaseUnitPrice }}"
                           class="form-control" required>
                    <span class="input-group-text">{{ __('catalog.currency') }}</span>
                </div>
                @error("lines.{$index}.purchase_unit_price")
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        @endif
        <div class="col-6 col-md-2">
            <button type="button" class="btn btn-outline-danger w-100"
                    data-line-remove @disabled(! $canRemove)>
                {{ __('catalog.actions.remove_line') }}
            </button>
        </div>
    </div>
</div>
