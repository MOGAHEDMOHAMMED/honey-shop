@php
    $selectedCategoryId = old('category_id', $product->category_id);
    $selectedUnit = $categoryUnits[$selectedCategoryId] ?? $product->base_unit?->value;
    $discountPercentage = $product->exists
        ? bcmul((string) $product->max_discount_rate, '100', 4)
        : '';
@endphp

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="product-name" class="form-label">
            {{ __('catalog.fields.name') }} <span class="required-mark">*</span>
        </label>
        <input id="product-name" name="name" value="{{ old('name', $product->name) }}"
               class="form-control @error('name') is-invalid @enderror" maxlength="255" required>
    </div>
    <div class="col-12 col-md-6">
        <label for="product-sku" class="form-label">{{ __('catalog.fields.sku') }}</label>
        <input id="product-sku" name="sku" value="{{ old('sku', $product->sku) }}"
               class="form-control @error('sku') is-invalid @enderror" maxlength="100">
    </div>
    <div class="col-12 col-md-6">
        <label for="category-id" class="form-label">
            {{ __('catalog.fields.category') }} <span class="required-mark">*</span>
        </label>
        <select id="category-id" name="category_id"
                class="form-select @error('category_id') is-invalid @enderror" required>
            <option value="">{{ __('catalog.fields.choose_category') }}</option>
            @foreach ($categories as $category)
                @php($unit = $categoryUnits[$category->id] ?? null)
                @if ($unit)
                    <option value="{{ $category->id }}"
                            data-unit="{{ __('catalog.units.'.$unit) }}"
                            @selected((string) $selectedCategoryId === (string) $category->id)>
                        {{ __('catalog.categories.'.$category->name) }}
                    </option>
                @endif
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label" for="base-unit-display">{{ __('catalog.fields.base_unit') }}</label>
        <input id="base-unit-display" class="form-control" value="{{ $selectedUnit ? __('catalog.units.'.$selectedUnit) : '' }}"
               readonly tabindex="-1">
        <div class="form-text">{{ __('catalog.messages.base_unit_from_category') }}</div>
    </div>
    <div class="col-12 col-md-6">
        <label for="selling-price" class="form-label">
            {{ __('catalog.fields.selling_price') }} <span class="required-mark">*</span>
        </label>
        <div class="input-group">
            <input id="selling-price" name="selling_price" type="number" min="0" step="0.01"
                   value="{{ old('selling_price', $product->selling_price) }}"
                   class="form-control @error('selling_price') is-invalid @enderror" required>
            <span class="input-group-text">{{ __('catalog.currency') }}</span>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <label for="max-discount" class="form-label">
            {{ __('catalog.fields.max_discount_percentage') }} <span class="required-mark">*</span>
        </label>
        <div class="input-group">
            <input id="max-discount" name="max_discount_percentage" type="number" min="0" max="100"
                   step="0.0001" value="{{ old('max_discount_percentage', $discountPercentage) }}"
                   class="form-control @error('max_discount_percentage') is-invalid @enderror" required>
            <span class="input-group-text">%</span>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <label for="minimum-stock" class="form-label">{{ __('catalog.fields.minimum_stock') }}</label>
        <input id="minimum-stock" name="minimum_stock" type="number" min="0" step="0.001"
               value="{{ old('minimum_stock', $product->minimum_stock) }}"
               class="form-control @error('minimum_stock') is-invalid @enderror">
    </div>
    <div class="col-12 col-md-6">
        <label class="form-label d-block">{{ __('catalog.fields.status') }}</label>
        <input type="hidden" name="is_active" value="0">
        <div class="form-check">
            <input id="product-active" name="is_active" type="checkbox" value="1"
                   class="form-check-input" @checked((string) old('is_active', $product->is_active ? '1' : '0') === '1')>
            <label for="product-active" class="form-check-label">{{ __('catalog.fields.active') }}</label>
        </div>
    </div>
    <div class="col-12">
        <label for="product-notes" class="form-label">{{ __('catalog.fields.notes') }}</label>
        <textarea id="product-notes" name="notes" rows="3" maxlength="2000"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $product->notes) }}</textarea>
    </div>
    @if ($product->exists)
        <div class="col-12">
            <label for="price-change-reason" class="form-label">{{ __('catalog.fields.change_reason') }}</label>
            <input id="price-change-reason" name="price_change_reason"
                   value="{{ old('price_change_reason') }}"
                   class="form-control @error('price_change_reason') is-invalid @enderror" maxlength="500">
        </div>
    @endif
</div>

@push('scripts')
    <script>
        const categoryInput = document.getElementById('category-id');
        const unitInput = document.getElementById('base-unit-display');

        categoryInput.addEventListener('change', () => {
            unitInput.value = categoryInput.selectedOptions[0]?.dataset.unit ?? '';
        });
    </script>
@endpush
