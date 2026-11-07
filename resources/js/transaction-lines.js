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

    for (const line of list.querySelectorAll('[data-transaction-line]')) {
        configureTransactionLine(line);
    }

    list.addEventListener('change', (event) => {
        if (event.target.matches('[data-line-product]')) {
            configureTransactionLine(event.target.closest('[data-transaction-line]'));
        }
    });

    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-line-remove]');
        if (removeButton && list.querySelectorAll('[data-transaction-line]').length > 1) {
            removeButton.closest('[data-transaction-line]').remove();
            updateRemoveButtons();
        }
    });

    addButton.addEventListener('click', () => {
        const index = Number(form.dataset.nextLineIndex);
        const markup = template.innerHTML.replaceAll('__INDEX__', String(index));
        list.insertAdjacentHTML('beforeend', markup);
        form.dataset.nextLineIndex = String(index + 1);
        configureTransactionLine(list.lastElementChild);
        updateRemoveButtons();
    });

    updateRemoveButtons();
}
