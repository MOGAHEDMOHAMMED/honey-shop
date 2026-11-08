<form method="post" action="{{ $sale ? route('sales.update', $sale) : route('sales.store') }}"
      data-transaction-lines data-sales-form data-next-line-index="{{ count($lines) }}">
    @csrf
    @if ($sale)
        @method('PUT')
    @endif
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <label for="sale-customer" class="form-label">{{ __('catalog.fields.customer') }} <span class="required-mark">*</span></label>
            <select id="sale-customer" name="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                <option value="">{{ __('catalog.fields.choose_customer') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) old('customer_id', $sale?->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="sales-channel" class="form-label">{{ __('catalog.fields.sales_channel') }} <span class="required-mark">*</span></label>
            <select id="sales-channel" name="sales_channel" class="form-select" required>
                <option value="direct" @selected(old('sales_channel', $sale?->sales_channel ?? 'direct') === 'direct')>{{ __('catalog.fields.direct_sale') }}</option>
                <option value="external" @selected(old('sales_channel', $sale?->sales_channel) === 'external')>{{ __('catalog.fields.external_sale') }}</option>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="sale-date" class="form-label">{{ __('catalog.fields.document_date') }} <span class="required-mark">*</span></label>
            <input id="sale-date" name="document_date" type="date"
                   value="{{ old('document_date', $sale?->businessTransaction?->occurred_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                   class="form-control" required>
        </div>
        <div class="col-12 col-md-6" data-external-fields>
            <label for="delivery-charge" class="form-label">{{ __('catalog.fields.delivery_charge') }}</label>
            <div class="input-group">
                <input id="delivery-charge" name="delivery_charge" type="number" min="0" step="0.01"
                       value="{{ old('delivery_charge', $sale?->delivery_charge ?? '0.00') }}" class="form-control">
                <span class="input-group-text">{{ __('catalog.currency') }}</span>
            </div>
        </div>
        <div class="col-12 col-md-6" data-external-fields>
            <label for="delivery-bearer" class="form-label">{{ __('catalog.fields.delivery_cost_bearer') }}</label>
            <select id="delivery-bearer" name="delivery_cost_bearer" class="form-select">
                @foreach (['shop', 'customer', 'shared'] as $bearer)
                    <option value="{{ $bearer }}" @selected(old('delivery_cost_bearer', $sale?->delivery_cost_bearer ?? 'customer') === $bearer)>
                        {{ __('catalog.fields.delivery_'.$bearer) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6" data-external-fields>
            <label for="delivery-provider" class="form-label">{{ __('catalog.fields.delivery_provider') }}</label>
            <select id="delivery-provider" name="delivery_provider_id" class="form-select">
                <option value="">{{ __('catalog.fields.no_delivery_provider') }}</option>
                @foreach ($providers as $provider)
                    <option value="{{ $provider->id }}" @selected((string) old('delivery_provider_id', $sale?->delivery_provider_id) === (string) $provider->id)>{{ $provider->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6" data-external-fields>
            <label for="representative" class="form-label">{{ __('catalog.fields.representative') }}</label>
            <select id="representative" name="representative_id" class="form-select">
                <option value="">{{ __('catalog.fields.no_representative') }}</option>
                @foreach ($representatives as $representative)
                    <option value="{{ $representative->id }}" @selected((string) old('representative_id', $sale?->representative_id) === (string) $representative->id)>{{ $representative->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <label for="sale-notes" class="form-label">{{ __('catalog.fields.notes') }}</label>
            <textarea id="sale-notes" name="notes" rows="2" maxlength="2000" class="form-control">{{ old('notes', $sale?->businessTransaction?->notes) }}</textarea>
        </div>
    </div>
    <div class="alert alert-info" data-external-fields>{{ __('catalog.messages.external_sale_shipment_note') }}</div>
    <h2 class="h5 mb-3">{{ __('catalog.fields.sale_lines') }}</h2>
    <div data-line-list>
        @foreach ($lines as $index => $line)
            @include('modules.shared._transaction-line', [
                'products' => $products,
                'index' => $index,
                'line' => $line,
                'showCost' => false,
                'showSaleFields' => true,
                'canRemove' => count($lines) > 1,
            ])
        @endforeach
    </div>
    <template data-line-template>
        @include('modules.shared._transaction-line', [
            'products' => $products,
            'index' => '__INDEX__',
            'line' => [],
            'showCost' => false,
            'showSaleFields' => true,
            'canRemove' => true,
        ])
    </template>
    <div class="text-start mt-3">
        <span class="fw-semibold">{{ __('catalog.fields.invoice_total') }}:</span>
        <output data-document-total data-currency="{{ __('catalog.currency') }}" class="fw-bold" dir="ltr">0 {{ __('catalog.currency') }}</output>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="button" class="btn btn-outline-primary" data-line-add>{{ __('catalog.actions.add_line') }}</button>
        <button class="btn btn-primary" type="submit">{{ __('catalog.actions.save_draft') }}</button>
        <a class="btn btn-outline-secondary" href="{{ $sale ? route('sales.show', $sale) : route('sales.index') }}">{{ __('catalog.actions.cancel') }}</a>
    </div>
</form>
