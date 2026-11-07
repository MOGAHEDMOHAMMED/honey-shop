<form method="post" action="{{ $purchase ? route('purchases.update', $purchase) : route('purchases.store') }}"
      data-transaction-lines data-next-line-index="{{ count($lines) }}">
    @csrf
    @if ($purchase)
        @method('PUT')
    @endif
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <label for="supplier-id" class="form-label">
                {{ __('catalog.fields.supplier') }} <span class="required-mark">*</span>
            </label>
            <select id="supplier-id" name="supplier_id"
                    class="form-select @error('supplier_id') is-invalid @enderror" required>
                <option value="">{{ __('catalog.fields.choose_supplier') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}"
                            @selected((string) old('supplier_id', $purchase?->supplier_id) === (string) $supplier->id)>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="supplier-invoice-number" class="form-label">
                {{ __('catalog.fields.supplier_invoice_number') }} <span class="required-mark">*</span>
            </label>
            <input id="supplier-invoice-number" name="supplier_invoice_number"
                   value="{{ old('supplier_invoice_number', $purchase?->supplier_invoice_number) }}"
                   class="form-control @error('supplier_invoice_number') is-invalid @enderror"
                   maxlength="100" required>
        </div>
        <div class="col-12 col-md-6">
            <label for="document-date" class="form-label">
                {{ __('catalog.fields.document_date') }} <span class="required-mark">*</span>
            </label>
            <input id="document-date" name="document_date" type="date"
                   value="{{ old('document_date', $purchase?->businessTransaction?->occurred_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                   class="form-control @error('document_date') is-invalid @enderror" required>
        </div>
        <div class="col-12">
            <label for="purchase-notes" class="form-label">{{ __('catalog.fields.notes') }}</label>
            <textarea id="purchase-notes" name="notes" rows="2" maxlength="2000"
                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $purchase?->businessTransaction?->notes) }}</textarea>
        </div>
    </div>

    <h2 class="h5 mb-3">{{ __('catalog.fields.purchase_lines') }}</h2>
    <div data-line-list>
        @foreach ($lines as $index => $line)
            @include('modules.shared._transaction-line', [
                'products' => $products,
                'index' => $index,
                'line' => $line,
                'showCost' => true,
                'canRemove' => count($lines) > 1,
            ])
        @endforeach
    </div>
    <template data-line-template>
        @include('modules.shared._transaction-line', [
            'products' => $products,
            'index' => '__INDEX__',
            'line' => [],
            'showCost' => true,
            'canRemove' => true,
        ])
    </template>
    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="button" class="btn btn-outline-primary" data-line-add>
            {{ __('catalog.actions.add_line') }}
        </button>
        <button class="btn btn-primary" type="submit">{{ __('catalog.actions.save_draft') }}</button>
        <a class="btn btn-outline-secondary" href="{{ $purchase ? route('purchases.show', $purchase) : route('purchases.index') }}">
            {{ __('catalog.actions.cancel') }}
        </a>
    </div>
</form>
