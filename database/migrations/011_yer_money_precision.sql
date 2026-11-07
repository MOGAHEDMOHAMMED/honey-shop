BEGIN;

DO $migration$
BEGIN
    IF EXISTS (
        SELECT 1 FROM products
        WHERE selling_price <> round(selling_price, 2)
    ) THEN
        RAISE EXCEPTION 'Product prices contain fractions below one halala; inspect before applying two-place YER precision';
    END IF;

    IF EXISTS (
        SELECT 1 FROM purchase_lines
        WHERE purchase_unit_price <> round(purchase_unit_price, 2)
    ) THEN
        RAISE EXCEPTION 'Purchase unit prices contain fractions below one halala; inspect before applying two-place YER precision';
    END IF;

    IF EXISTS (
        SELECT 1 FROM purchase_invoices
        WHERE invoice_total <> round(invoice_total, 2)
    ) THEN
        RAISE EXCEPTION 'Purchase invoice totals contain fractions below one halala; inspect before applying two-place YER precision';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM supplier_account_entries
        WHERE debit <> round(debit, 2)
           OR credit <> round(credit, 2)
    ) THEN
        RAISE EXCEPTION 'Supplier ledger entries contain fractions below one halala; inspect before applying two-place YER precision';
    END IF;

    IF EXISTS (
        SELECT 1 FROM supplier_invoice_allocations
        WHERE amount_applied <> round(amount_applied, 2)
    ) THEN
        RAISE EXCEPTION 'Supplier payment allocations contain fractions below one halala; inspect before applying two-place YER precision';
    END IF;
END;
$migration$;

ALTER TABLE products
    ALTER COLUMN selling_price TYPE numeric(19, 2)
    USING round(selling_price, 2);

ALTER TABLE purchase_lines
    DROP COLUMN line_total,
    DROP COLUMN effective_base_unit_cost;

ALTER TABLE purchase_lines
    ALTER COLUMN purchase_unit_price TYPE numeric(19, 2)
    USING round(purchase_unit_price, 2);

ALTER TABLE purchase_lines
    ADD COLUMN line_total numeric(19, 2)
        GENERATED ALWAYS AS (round(entered_quantity * purchase_unit_price, 2)) STORED,
    ADD COLUMN effective_base_unit_cost numeric(19, 2)
        GENERATED ALWAYS AS (round(purchase_unit_price / conversion_factor, 2)) STORED;

ALTER TABLE purchase_invoices
    ALTER COLUMN invoice_total TYPE numeric(19, 2)
    USING round(invoice_total, 2);

ALTER TABLE supplier_account_entries
    ALTER COLUMN debit TYPE numeric(19, 2)
    USING round(debit, 2),
    ALTER COLUMN credit TYPE numeric(19, 2)
    USING round(credit, 2);

ALTER TABLE supplier_invoice_allocations
    ALTER COLUMN amount_applied TYPE numeric(19, 2)
    USING round(amount_applied, 2);

ALTER TABLE product_price_history
    ALTER COLUMN previous_selling_price TYPE numeric(19, 2)
        USING round(previous_selling_price, 2),
    ALTER COLUMN new_selling_price TYPE numeric(19, 2)
        USING round(new_selling_price, 2);

INSERT INTO schema_migrations (version)
VALUES ('011_yer_money_precision');

COMMIT;
