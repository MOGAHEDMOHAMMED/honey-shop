<form method="post" action="{{ $openingInventory ? route('opening-inventory.update', $openingInventory) : route('opening-inventory.store') }}"
      data-transaction-lines data-next-line-index="{{ count($lines) }}">
    @csrf
    @if ($openingInventory)
        @method('PUT')
    @endif
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6">
            <label for="effective-at" class="form-label">
                {{ __('catalog.fields.effective_at') }} <span class="required-mark">*</span>
            </label>
            <input id="effective-at" name="effective_at" type="date"
                   value="{{ old('effective_at', $openingInventory?->effective_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                   class="form-control @error('effective_at') is-invalid @enderror" required>
        </div>
        <div class="col-12">
            <label for="opening-notes" class="form-label">{{ __('catalog.fields.notes') }}</label>
            <textarea id="opening-notes" name="notes" rows="2" maxlength="2000"
                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $openingInventory?->businessTransaction?->notes) }}</textarea>
        </div>
    </div>

    <h2 class="h5 mb-3">{{ __('catalog.fields.inventory_lines') }}</h2>
    <div data-line-list>
        @foreach ($lines as $index => $line)
            @include('modules.shared._transaction-line', [
                'products' => $products,
                'index' => $index,
                'line' => $line,
                'showCost' => false,
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
            'canRemove' => true,
        ])
    </template>
    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="button" class="btn btn-outline-primary" data-line-add>
            {{ __('catalog.actions.add_line') }}
        </button>
        <button class="btn btn-primary" type="submit">{{ __('catalog.actions.save_draft') }}</button>
        <a class="btn btn-outline-secondary" href="{{ $openingInventory ? route('opening-inventory.show', $openingInventory) : route('opening-inventory.index') }}">
            {{ __('catalog.actions.cancel') }}
        </a>
    </div>
</form>
