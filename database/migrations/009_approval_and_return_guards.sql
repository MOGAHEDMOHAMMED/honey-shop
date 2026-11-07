BEGIN;

CREATE FUNCTION require_draft_business_transaction() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    transaction_status text;
BEGIN
    SELECT status INTO transaction_status
    FROM business_transactions
    WHERE id = NEW.transaction_id;

    IF transaction_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Purchase and sales lines can only be added to draft transactions';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER purchase_lines_draft_only
    BEFORE INSERT ON purchase_lines
    FOR EACH ROW EXECUTE FUNCTION require_draft_business_transaction();
CREATE TRIGGER sales_lines_draft_only
    BEFORE INSERT ON sales_lines
    FOR EACH ROW EXECUTE FUNCTION require_draft_business_transaction();

CREATE FUNCTION require_approved_purchase_for_return() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    purchase_status text;
BEGIN
    SELECT bt.status INTO purchase_status
    FROM purchase_invoices pi
    JOIN business_transactions bt ON bt.id = pi.transaction_id
    WHERE pi.transaction_id = NEW.original_purchase_id
    FOR UPDATE OF pi;

    IF purchase_status IS DISTINCT FROM 'approved' THEN
        RAISE EXCEPTION 'Purchase returns require an approved original purchase';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER purchase_return_original_approved
    BEFORE INSERT ON purchase_return_lines
    FOR EACH ROW EXECUTE FUNCTION require_approved_purchase_for_return();

CREATE FUNCTION preserve_final_business_transaction() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF OLD.status IN ('approved', 'cancelled') THEN
        IF TG_OP = 'DELETE' THEN
            RAISE EXCEPTION 'Final business transactions cannot be deleted; record a reversal';
        END IF;

        IF NEW.status IS DISTINCT FROM OLD.status THEN
            RAISE EXCEPTION 'Final business transaction status cannot be changed; record a reversal';
        END IF;
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER business_transactions_final_state_guard
    BEFORE UPDATE OR DELETE ON business_transactions
    FOR EACH ROW EXECUTE FUNCTION preserve_final_business_transaction();

CREATE OR REPLACE FUNCTION post_sales_return_line() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    sale_line sales_lines%ROWTYPE;
    transaction_status text;
    original_transaction_status text;
    invoice_status text;
    product_unit base_inventory_unit;
BEGIN
    SELECT status INTO transaction_status
    FROM business_transactions
    WHERE id = NEW.transaction_id;
    IF transaction_status IS DISTINCT FROM 'approved' THEN
        RAISE EXCEPTION 'Sales return lines require an approved transaction';
    END IF;

    SELECT * INTO sale_line
    FROM sales_lines
    WHERE id = NEW.original_sale_line_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Original sales line % does not exist', NEW.original_sale_line_id;
    END IF;

    SELECT bt.status, si.order_status
    INTO original_transaction_status, invoice_status
    FROM sales_invoices si
    JOIN business_transactions bt ON bt.id = si.transaction_id
    WHERE si.transaction_id = sale_line.transaction_id
    FOR UPDATE OF si;

    IF original_transaction_status IS DISTINCT FROM 'approved'
       OR invoice_status = 'cancelled' THEN
        RAISE EXCEPTION 'Returns require an approved, uncancelled original sale';
    END IF;

    UPDATE sales_lines
    SET returned_quantity = returned_quantity + NEW.quantity
    WHERE id = NEW.original_sale_line_id
      AND returned_quantity + NEW.quantity <= base_quantity
    RETURNING * INTO sale_line;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Cumulative returned quantity exceeds the original sold quantity';
    END IF;

    SELECT base_unit INTO product_unit FROM products WHERE id = sale_line.product_id;
    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    ) VALUES (
        NEW.transaction_id, sale_line.product_id, 'sales_return',
        NEW.quantity, product_unit, NEW.id
    );

    INSERT INTO customer_account_entries (
        transaction_id, customer_id, currency_code, credit, description
    )
    SELECT NEW.transaction_id, sr.customer_id, 'YER', NEW.amount, 'Sales return'
    FROM sales_returns sr
    WHERE sr.transaction_id = NEW.transaction_id;

    RETURN NEW;
END;
$$;

INSERT INTO schema_migrations (version)
VALUES ('009_approval_and_return_guards');

COMMIT;
