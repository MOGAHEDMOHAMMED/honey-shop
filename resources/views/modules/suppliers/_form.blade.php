<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="supplier-name" class="form-label">
            {{ __('catalog.fields.name') }} <span class="required-mark">*</span>
        </label>
        <input id="supplier-name" name="name" value="{{ old('name', $supplier->name) }}"
               class="form-control @error('name') is-invalid @enderror" maxlength="255" required>
    </div>
    <div class="col-12 col-md-6">
        <label for="supplier-type" class="form-label">
            {{ __('catalog.fields.supplier_type') }} <span class="required-mark">*</span>
        </label>
        <select id="supplier-type" name="supplier_type_id"
                class="form-select @error('supplier_type_id') is-invalid @enderror" required>
            <option value="">{{ __('catalog.fields.choose_supplier_type') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}"
                        @selected((string) old('supplier_type_id', $supplier->supplier_type_id) === (string) $type->id)>
                    {{ __('catalog.supplier_types.'.$type->name, ['name' => $type->name]) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-6">
        <label for="supplier-phone" class="form-label">{{ __('catalog.fields.phone') }}</label>
        <input id="supplier-phone" name="phone" value="{{ old('phone', $supplier->phone) }}"
               class="form-control @error('phone') is-invalid @enderror" maxlength="100" dir="ltr">
    </div>
    <div class="col-12">
        <label for="supplier-address" class="form-label">{{ __('catalog.fields.address') }}</label>
        <textarea id="supplier-address" name="address" rows="2" maxlength="1000"
                  class="form-control @error('address') is-invalid @enderror">{{ old('address', $supplier->address) }}</textarea>
    </div>
    <div class="col-12">
        <label for="supplier-notes" class="form-label">{{ __('catalog.fields.notes') }}</label>
        <textarea id="supplier-notes" name="notes" rows="3" maxlength="2000"
                  class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $supplier->notes) }}</textarea>
    </div>
    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check">
            <input id="supplier-active" name="is_active" type="checkbox" value="1"
                   class="form-check-input"
                   @checked((string) old('is_active', $supplier->is_active ? '1' : '0') === '1')>
            <label for="supplier-active" class="form-check-label">{{ __('catalog.fields.active') }}</label>
        </div>
    </div>
</div>
