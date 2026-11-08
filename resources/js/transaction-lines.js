const unitOptionsByCategory = {
    honey: ['KG', 'GALLON'],
    ghee: ['CAN'],
    nuts: ['KG'],
};

function configureTransactionLine(line) {
    const product = line.querySelector('[data-line-product]');
    const unit = line.querySelector('[data-line-unit]');
    const category = product.selectedOptions[0]?.dataset.category;
    const allowedUnits = unitOptionsByCategory[category] ?? [];

    for (const option of unit.options) {
        option.disabled = option.value !== '' && !allowedUnits.includes(option.value);
        option.hidden = option.disabled;
    }

    if (unit.value && !allowedUnits.includes(unit.value)) {
        unit.value = '';
    }
    const discount = line.querySelector('[data-line-discount]');
    if (discount) {
        discount.max = product.selectedOptions[0]?.dataset.maxDiscount ?? '100';
    }
    const salePrice = line.querySelector('[data-line-price]');
    if (salePrice && !salePrice.value && product.value) {
        salePrice.value = product.selectedOptions[0]?.dataset.sellingPrice ?? '';
    }
    updateLineTotal(line);
}

function scaled(value, scale) {
    const text = String(value ?? '').trim();
    if (!/^\d+(?:\.\d*)?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    return BigInt(whole) * (10n ** BigInt(scale))
        + BigInt((fraction + '0'.repeat(scale)).slice(0, scale) || '0');
}

function formatCents(cents) {
    const whole = cents / 100n;
    const fraction = String(cents % 100n).padStart(2, '0').replace(/0+$/, '');
    return `${whole}${fraction ? `.${fraction}` : ''}`;
}

function updateLineTotal(line) {
    const quantity = scaled(line.querySelector('[data-line-quantity]')?.value, 3);
    const price = scaled(
        line.querySelector('[data-line-cost]')?.value
            ?? line.querySelector('[data-line-price]')?.value,
        2,
    );
    const discount = scaled(line.querySelector('[data-line-discount]')?.value || '0', 4);
    const output = line.querySelector('[data-line-total]');
    if (!output) return 0n;
    if (quantity === null || price === null || discount === null) {
        output.textContent = '0';
        return 0n;
    }
    const gross = (quantity * price + 500n) / 1000n;
    const netDenominator = 1000000000n;
    const net = (quantity * price * (1000000n - discount) + netDenominator / 2n)
        / netDenominator;
    output.textContent = formatCents(net);
    return net;
}

for (const form of document.querySelectorAll('[data-transaction-lines]')) {
    const list = form.querySelector('[data-line-list]');
    const template = form.querySelector('template[data-line-template]');
    const addButton = form.querySelector('[data-line-add]');

    const updateRemoveButtons = () => {
        const lines = list.querySelectorAll('[data-transaction-line]');
        for (const line of lines) {
            line.querySelector('[data-line-remove]').disabled = lines.length === 1;
        }
    };

    const updateDocumentTotal = () => {
        let total = [...list.querySelectorAll('[data-transaction-line]')]
            .reduce((sum, line) => sum + updateLineTotal(line), 0n);
        const channel = form.querySelector('[name="sales_channel"]');
        const bearer = form.querySelector('[name="delivery_cost_bearer"]');
        const delivery = scaled(form.querySelector('[name="delivery_charge"]')?.value, 2);
        if (channel?.value === 'external' && bearer?.value !== 'shop' && delivery !== null) {
            total += delivery;
        }
        const output = form.querySelector('[data-document-total]');
        if (output) {
            output.textContent = `${formatCents(total)} ${output.dataset.currency ?? ''}`.trim();
        }
    };

    const channel = form.querySelector('[name="sales_channel"]');
    if (channel) {
        const externalFields = form.querySelectorAll('[data-external-fields]');
        const updateChannelFields = () => {
            const external = channel.value === 'external';
            for (const field of externalFields) {
                field.hidden = !external;
            }
            updateDocumentTotal();
        };
        channel.addEventListener('change', updateChannelFields);
        updateChannelFields();
        form.querySelector('[name="delivery_cost_bearer"]')
            ?.addEventListener('change', updateDocumentTotal);
        form.querySelector('[name="delivery_charge"]')
            ?.addEventListener('input', updateDocumentTotal);
    }

    for (const line of list.querySelectorAll('[data-transaction-line]')) {
        configureTransactionLine(line);
    }

    list.addEventListener('input', (event) => {
        if (event.target.matches('[data-line-quantity], [data-line-cost], [data-line-price], [data-line-discount]')) {
            updateDocumentTotal();
        }
    });

    list.addEventListener('change', (event) => {
        if (event.target.matches('[data-line-product]')) {
            const line = event.target.closest('[data-transaction-line]');
            configureTransactionLine(line);
            const price = line.querySelector('[data-line-price]');
            if (price) price.value = event.target.selectedOptions[0]?.dataset.sellingPrice ?? '';
        }
        updateDocumentTotal();
    });

    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-line-remove]');
        if (removeButton && list.querySelectorAll('[data-transaction-line]').length > 1) {
            removeButton.closest('[data-transaction-line]').remove();
            updateRemoveButtons();
            updateDocumentTotal();
        }
    });

    addButton.addEventListener('click', () => {
        const index = Number(form.dataset.nextLineIndex);
        const markup = template.innerHTML.replaceAll('__INDEX__', String(index));
        list.insertAdjacentHTML('beforeend', markup);
        form.dataset.nextLineIndex = String(index + 1);
        configureTransactionLine(list.lastElementChild);
        updateRemoveButtons();
        updateDocumentTotal();
    });

    updateRemoveButtons();
    updateDocumentTotal();
}
