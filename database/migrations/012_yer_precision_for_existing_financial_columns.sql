BEGIN;

DO $migration$
BEGIN
    IF EXISTS (SELECT 1 FROM account_transactions WHERE amount <> round(amount, 2))
       OR EXISTS (SELECT 1 FROM account_transfers WHERE amount <> round(amount, 2))
       OR EXISTS (
           SELECT 1 FROM customer_account_entries
           WHERE debit <> round(debit, 2) OR credit <> round(credit, 2)
       )
       OR EXISTS (
           SELECT 1 FROM customer_invoice_allocations
           WHERE amount_applied <> round(amount_applied, 2)
       )
       OR EXISTS (
           SELECT 1 FROM expenses
           WHERE amount <> round(amount, 2)
              OR paid_amount <> round(paid_amount, 2)
              OR remaining_amount <> round(remaining_amount, 2)
       )
       OR EXISTS (SELECT 1 FROM owner_withdrawals WHERE amount <> round(amount, 2))
       OR EXISTS (SELECT 1 FROM purchase_return_lines WHERE amount <> round(amount, 2))
       OR EXISTS (
           SELECT 1 FROM sales_invoices
           WHERE products_subtotal <> round(products_subtotal, 2)
              OR discounts_total <> round(discounts_total, 2)
              OR delivery_charge <> round(delivery_charge, 2)
              OR final_total <> round(final_total, 2)
       )
       OR EXISTS (
           SELECT 1 FROM sales_lines
           WHERE unit_price <> round(unit_price, 2)
              OR discount_amount <> round(discount_amount, 2)
              OR line_total <> round(line_total, 2)
       )
       OR EXISTS (SELECT 1 FROM sales_return_lines WHERE amount <> round(amount, 2))
       OR EXISTS (SELECT 1 FROM vouchers WHERE amount <> round(amount, 2)) THEN
        RAISE EXCEPTION 'Existing YER financial values contain sub-cent amounts; inspect and resolve them before applying two-place precision';
    END IF;
END;
$migration$;

ALTER TABLE account_transactions
    ALTER COLUMN amount TYPE numeric(19, 2) USING round(amount, 2);

ALTER TABLE account_transfers
    ALTER COLUMN amount TYPE numeric(19, 2) USING round(amount, 2);

ALTER TABLE customer_account_entries
    ALTER COLUMN debit TYPE numeric(19, 2) USING round(debit, 2),
    ALTER COLUMN credit TYPE numeric(19, 2) USING round(credit, 2);

ALTER TABLE customer_invoice_allocations
    ALTER COLUMN amount_applied TYPE numeric(19, 2) USING round(amount_applied, 2);

ALTER TABLE expenses
    DROP COLUMN remaining_amount;

ALTER TABLE expenses
    ALTER COLUMN amount TYPE numeric(19, 2) USING round(amount, 2),
    ALTER COLUMN paid_amount TYPE numeric(19, 2) USING round(paid_amount, 2);

ALTER TABLE expenses
    ADD COLUMN remaining_amount numeric(19, 2)
        GENERATED ALWAYS AS (amount - paid_amount) STORED;

ALTER TABLE owner_withdrawals
    ALTER COLUMN amount TYPE numeric(19, 2) USING round(amount, 2);

ALTER TABLE purchase_return_lines
    ALTER COLUMN amount TYPE numeric(19, 2) USING round(amount, 2);

ALTER TABLE sales_invoices
    ALTER COLUMN products_subtotal TYPE numeric(19, 2) USING round(products_subtotal, 2),
    ALTER COLUMN discounts_total TYPE numeric(19, 2) USING round(discounts_total, 2),
    ALTER COLUMN delivery_charge TYPE numeric(19, 2) USING round(delivery_charge, 2),
    ALTER COLUMN final_total TYPE numeric(19, 2) USING round(final_total, 2);

ALTER TABLE sales_lines
    DROP CONSTRAINT sales_lines_check,
    DROP COLUMN line_total,
    ALTER COLUMN unit_price TYPE numeric(19, 2) USING round(unit_price, 2),
    ALTER COLUMN discount_amount TYPE numeric(19, 2) USING round(discount_amount, 2),
    ADD COLUMN line_total numeric(19, 2)
        GENERATED ALWAYS AS (round(entered_quantity * unit_price, 2) - discount_amount) STORED,
    ADD CONSTRAINT sales_lines_discount_amount_not_above_gross_check
        CHECK (discount_amount <= round(entered_quantity * unit_price, 2));

ALTER TABLE sales_return_lines
    ALTER COLUMN amount TYPE numeric(19, 2) USING round(amount, 2);

ALTER TABLE vouchers
    ALTER COLUMN amount TYPE numeric(19, 2) USING round(amount, 2);

INSERT INTO schema_migrations (version)
VALUES ('012_yer_precision_for_existing_financial_columns');

COMMIT;
