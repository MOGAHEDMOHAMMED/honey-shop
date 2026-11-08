<form method="post" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
    @csrf
    @if ($customer->exists)
        @method('PUT')
    @endif
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="customer-name" class="form-label">{{ __('catalog.fields.customer_name') }} <span class="required-mark">*</span></label>
            <input id="customer-name" name="name" value="{{ old('name', $customer->name) }}"
                   class="form-control @error('name') is-invalid @enderror" maxlength="255" required>
        </div>
        <div class="col-12 col-md-6">
            <label for="customer-phone" class="form-label">{{ __('catalog.fields.phone') }}</label>
            <input id="customer-phone" name="phone" value="{{ old('phone', $customer->phone) }}"
                   class="form-control @error('phone') is-invalid @enderror" maxlength="50">
        </div>
        <div class="col-12 col-md-6">
            <label for="customer-country" class="form-label">{{ __('catalog.fields.country') }}</label>
            <input id="customer-country" name="country" value="{{ old('country', $customer->country) }}"
                   class="form-control @error('country') is-invalid @enderror" maxlength="100">
        </div>
        <div class="col-12 col-md-6">
            <label for="customer-city" class="form-label">{{ __('catalog.fields.city') }}</label>
            <input id="customer-city" name="city" value="{{ old('city', $customer->city) }}"
                   class="form-control @error('city') is-invalid @enderror" maxlength="100">
        </div>
        <div class="col-12">
            <label for="customer-address" class="form-label">{{ __('catalog.fields.address') }}</label>
            <textarea id="customer-address" name="address" rows="2" maxlength="500"
                      class="form-control @error('address') is-invalid @enderror">{{ old('address', $customer->address) }}</textarea>
        </div>
        <div class="col-12">
            <label for="customer-notes" class="form-label">{{ __('catalog.fields.notes') }}</label>
            <textarea id="customer-notes" name="notes" rows="3" maxlength="2000"
                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $customer->notes) }}</textarea>
        </div>
        <div class="col-12">
            <input type="hidden" name="is_active" value="0">
            <div class="form-check">
                <input id="customer-active" name="is_active" type="checkbox" value="1"
                       class="form-check-input" @checked((string) old('is_active', $customer->is_active ? '1' : '0') === '1')>
                <label for="customer-active" class="form-check-label">{{ __('catalog.fields.active') }}</label>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary" type="submit">{{ __('catalog.actions.save') }}</button>
        <a class="btn btn-outline-secondary" href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}">
            {{ __('catalog.actions.cancel') }}
        </a>
    </div>
</form>
