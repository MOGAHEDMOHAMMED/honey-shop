BEGIN;

ALTER TABLE business_transactions
    DROP CONSTRAINT business_transactions_check,
    DROP CONSTRAINT business_transactions_check1;

ALTER TABLE business_transactions
    ADD CONSTRAINT business_transactions_approval_fields_check
        CHECK (
            (status = 'approved' AND approved_at IS NOT NULL AND approved_by IS NOT NULL)
            OR (status <> 'approved' AND approved_at IS NULL AND approved_by IS NULL)
        ),
    ADD CONSTRAINT business_transactions_cancellation_fields_check
        CHECK (
            (status = 'cancelled'
                AND cancelled_at IS NOT NULL
                AND cancelled_by IS NOT NULL
                AND cancellation_reason IS NOT NULL)
            OR (status <> 'cancelled'
                AND cancelled_at IS NULL
                AND cancelled_by IS NULL
                AND cancellation_reason IS NULL)
        );

CREATE TABLE transaction_currency_conversions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES business_transactions(id),
    from_currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    to_currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    source_amount numeric(19, 4) NOT NULL CHECK (source_amount > 0),
    target_amount numeric(19, 4) NOT NULL CHECK (target_amount > 0),
    exchange_rate numeric(19, 8) NOT NULL CHECK (exchange_rate > 0),
    effective_at timestamptz NOT NULL,
    exchange_rate_id uuid REFERENCES exchange_rates(id),
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK (from_currency_code <> to_currency_code)
);

CREATE TABLE customer_invoice_allocations (
    receipt_transaction_id uuid NOT NULL REFERENCES vouchers(transaction_id),
    sales_invoice_transaction_id uuid NOT NULL REFERENCES sales_invoices(transaction_id),
    amount_applied numeric(19, 4) NOT NULL CHECK (amount_applied > 0),
    PRIMARY KEY (receipt_transaction_id, sales_invoice_transaction_id)
);

CREATE TABLE supplier_invoice_allocations (
    payment_transaction_id uuid NOT NULL REFERENCES vouchers(transaction_id),
    purchase_invoice_transaction_id uuid NOT NULL REFERENCES purchase_invoices(transaction_id),
    amount_applied numeric(19, 4) NOT NULL CHECK (amount_applied > 0),
    PRIMARY KEY (payment_transaction_id, purchase_invoice_transaction_id)
);

CREATE INDEX currency_conversions_transaction_idx
    ON transaction_currency_conversions (transaction_id);
CREATE INDEX customer_invoice_allocations_invoice_idx
    ON customer_invoice_allocations (sales_invoice_transaction_id);
CREATE INDEX supplier_invoice_allocations_invoice_idx
    ON supplier_invoice_allocations (purchase_invoice_transaction_id);

CREATE FUNCTION apply_inventory_movement() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    transaction_status text;
    product_unit text;
BEGIN
    SELECT status INTO transaction_status
    FROM business_transactions
    WHERE id = NEW.transaction_id;

    IF transaction_status IS DISTINCT FROM 'approved' THEN
        RAISE EXCEPTION 'Inventory movements require an approved business transaction';
    END IF;

    SELECT base_unit INTO product_unit
    FROM products
    WHERE id = NEW.product_id;

    IF product_unit IS DISTINCT FROM NEW.base_unit THEN
        RAISE EXCEPTION 'Inventory movement unit must match the product base unit';
    END IF;

    INSERT INTO inventory_balances (product_id, quantity, updated_at)
    VALUES (NEW.product_id, NEW.quantity_delta, now())
    ON CONFLICT (product_id) DO UPDATE
        SET quantity = inventory_balances.quantity + EXCLUDED.quantity,
            updated_at = now();

    RETURN NEW;
END;
$$;

CREATE TRIGGER inventory_movement_apply_balance
    AFTER INSERT ON inventory_movements
    FOR EACH ROW EXECUTE FUNCTION apply_inventory_movement();

CREATE FUNCTION reject_ledger_mutation() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION '% is append-only; record a reversal instead', TG_TABLE_NAME;
END;
$$;

CREATE TRIGGER inventory_movements_append_only
    BEFORE UPDATE OR DELETE ON inventory_movements
    FOR EACH ROW EXECUTE FUNCTION reject_ledger_mutation();

CREATE TRIGGER account_transactions_append_only
    BEFORE UPDATE OR DELETE ON account_transactions
    FOR EACH ROW EXECUTE FUNCTION reject_ledger_mutation();

CREATE TRIGGER customer_account_entries_append_only
    BEFORE UPDATE OR DELETE ON customer_account_entries
    FOR EACH ROW EXECUTE FUNCTION reject_ledger_mutation();

CREATE TRIGGER supplier_account_entries_append_only
    BEFORE UPDATE OR DELETE ON supplier_account_entries
    FOR EACH ROW EXECUTE FUNCTION reject_ledger_mutation();

INSERT INTO schema_migrations (version) VALUES ('002_ledger_and_currency_rules');

COMMIT;
