BEGIN;

DO $$
DECLARE
    conflict_count bigint;
BEGIN
    SELECT count(*) INTO conflict_count
    FROM (
        SELECT srl.original_sale_line_id
        FROM sales_return_lines srl
        JOIN business_transactions bt ON bt.id = srl.transaction_id
        JOIN sales_lines sl ON sl.id = srl.original_sale_line_id
        GROUP BY srl.original_sale_line_id, sl.base_quantity
        HAVING sum(srl.quantity) > sl.base_quantity
    ) excessive_returns;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 005 stopped: % original sales lines already have excessive cumulative returns',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM (
        SELECT prl.original_purchase_line_id
        FROM purchase_return_lines prl
        JOIN business_transactions bt ON bt.id = prl.transaction_id
        JOIN purchase_lines pl ON pl.id = prl.original_purchase_line_id
        GROUP BY prl.original_purchase_line_id, pl.base_quantity
        HAVING sum(prl.quantity) > pl.base_quantity
    ) excessive_returns;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 005 stopped: % original purchase lines already have excessive cumulative returns',
            conflict_count;
    END IF;
END;
$$;

DROP TRIGGER purchase_lines_approved_immutable ON purchase_lines;
DROP TRIGGER sales_lines_approved_immutable ON sales_lines;

ALTER TABLE sales_lines
    ADD COLUMN returned_quantity numeric(18, 3) NOT NULL DEFAULT 0
        CHECK (returned_quantity >= 0 AND returned_quantity <= base_quantity);
UPDATE sales_lines sl
SET returned_quantity = returned.quantity
FROM (
    SELECT original_sale_line_id, sum(quantity) AS quantity
    FROM sales_return_lines
    GROUP BY original_sale_line_id
) returned
WHERE returned.original_sale_line_id = sl.id;

ALTER TABLE purchase_lines
    ADD COLUMN returned_quantity numeric(18, 3) NOT NULL DEFAULT 0
        CHECK (returned_quantity >= 0 AND returned_quantity <= base_quantity);
UPDATE purchase_lines pl
SET returned_quantity = returned.quantity
FROM (
    SELECT original_purchase_line_id, sum(quantity) AS quantity
    FROM purchase_return_lines
    GROUP BY original_purchase_line_id
) returned
WHERE returned.original_purchase_line_id = pl.id;

CREATE FUNCTION prevent_approved_original_line_mutation() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    parent_status text;
BEGIN
    SELECT status INTO parent_status
    FROM business_transactions
    WHERE id = OLD.transaction_id;

    IF parent_status = 'approved' THEN
        IF TG_OP = 'UPDATE'
           AND (to_jsonb(NEW) - 'returned_quantity')
               IS NOT DISTINCT FROM (to_jsonb(OLD) - 'returned_quantity')
           AND NEW.returned_quantity >= OLD.returned_quantity THEN
            RETURN NEW;
        END IF;
        RAISE EXCEPTION 'Lines of approved transactions are immutable; create a return or reversal';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER purchase_lines_approved_immutable
    BEFORE UPDATE OR DELETE ON purchase_lines
    FOR EACH ROW EXECUTE FUNCTION prevent_approved_original_line_mutation();
CREATE TRIGGER sales_lines_approved_immutable
    BEFORE UPDATE OR DELETE ON sales_lines
    FOR EACH ROW EXECUTE FUNCTION prevent_approved_original_line_mutation();

CREATE OR REPLACE FUNCTION post_sales_return_line() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    sale_line sales_lines%ROWTYPE;
    transaction_status text;
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

    SELECT order_status INTO invoice_status
    FROM sales_invoices
    WHERE transaction_id = sale_line.transaction_id
    FOR UPDATE;
    IF invoice_status IS NULL OR invoice_status = 'cancelled' THEN
        RAISE EXCEPTION 'Returns cannot be recorded against a cancelled order';
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
        transaction_id, product_id, movement_type, quantity_delta, base_unit
    ) VALUES (
        NEW.transaction_id, sale_line.product_id, 'sales_return',
        NEW.quantity, product_unit
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

CREATE OR REPLACE FUNCTION post_purchase_return_line() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    purchase_line purchase_lines%ROWTYPE;
    purchase_transaction_id uuid;
    transaction_status text;
    product_unit base_inventory_unit;
BEGIN
    SELECT status INTO transaction_status
    FROM business_transactions
    WHERE id = NEW.transaction_id;
    IF transaction_status IS DISTINCT FROM 'approved' THEN
        RAISE EXCEPTION 'Purchase return lines require an approved transaction';
    END IF;

    SELECT original_purchase_id INTO purchase_transaction_id
    FROM purchase_returns
    WHERE transaction_id = NEW.transaction_id;

    UPDATE purchase_lines
    SET returned_quantity = returned_quantity + NEW.quantity
    WHERE id = NEW.original_purchase_line_id
      AND transaction_id = purchase_transaction_id
      AND returned_quantity + NEW.quantity <= base_quantity
    RETURNING * INTO purchase_line;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Cumulative purchase-return quantity exceeds the original purchased quantity or line';
    END IF;

    SELECT base_unit INTO product_unit
    FROM products WHERE id = purchase_line.product_id;
    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit
    ) VALUES (
        NEW.transaction_id, purchase_line.product_id, 'purchase_return',
        -NEW.quantity, product_unit
    );

    INSERT INTO supplier_account_entries (
        transaction_id, supplier_id, currency_code, debit, description
    )
    SELECT NEW.transaction_id, pr.supplier_id, 'YER', NEW.amount, 'Purchase return'
    FROM purchase_returns pr
    WHERE pr.transaction_id = NEW.transaction_id;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION post_order_cancellation() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    sale sales_invoices%ROWTYPE;
    cancellation_status text;
    original_status text;
BEGIN
    SELECT status INTO cancellation_status
    FROM business_transactions
    WHERE id = NEW.transaction_id;
    IF cancellation_status IS DISTINCT FROM 'approved' THEN
        RAISE EXCEPTION 'Order cancellation requires an approved transaction';
    END IF;
    IF NOT NEW.shipment_returned THEN
        RAISE EXCEPTION 'The complete shipment must be confirmed returned before cancellation';
    END IF;

    SELECT * INTO sale
    FROM sales_invoices
    WHERE transaction_id = NEW.original_sale_id
    FOR UPDATE;
    IF NOT FOUND OR sale.sales_channel <> 'external' THEN
        RAISE EXCEPTION 'Cancellation must reference an external sales invoice';
    END IF;

    SELECT status INTO original_status
    FROM business_transactions
    WHERE id = sale.transaction_id;
    IF original_status IS DISTINCT FROM 'approved' OR sale.order_status = 'cancelled' THEN
        RAISE EXCEPTION 'Only an approved, uncancelled external order can be cancelled';
    END IF;

    PERFORM 1
    FROM sales_lines
    WHERE transaction_id = NEW.original_sale_id
    FOR UPDATE;

    IF EXISTS (
        SELECT 1 FROM sales_lines
        WHERE transaction_id = NEW.original_sale_id
          AND returned_quantity > 0
    ) OR EXISTS (
        SELECT 1
        FROM sales_returns sr
        JOIN sales_return_lines srl ON srl.transaction_id = sr.transaction_id
        WHERE sr.original_sale_id = NEW.original_sale_id
    ) THEN
        RAISE EXCEPTION 'An order with a partial sales return cannot be cancelled as a whole';
    END IF;

    IF EXISTS (
        SELECT expected.product_id
        FROM (
            SELECT product_id, sum(base_quantity) AS quantity
            FROM sales_lines
            WHERE transaction_id = NEW.original_sale_id
            GROUP BY product_id
        ) expected
        LEFT JOIN (
            SELECT product_id, sum(quantity_delta) AS quantity
            FROM inventory_movements
            WHERE transaction_id = NEW.original_sale_id
              AND movement_type = 'sale'
            GROUP BY product_id
        ) posted USING (product_id)
        WHERE COALESCE(posted.quantity, 0) <> -expected.quantity
    ) THEN
        RAISE EXCEPTION 'Original order inventory postings do not match its full shipment';
    END IF;

    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit
    )
    SELECT NEW.transaction_id, sl.product_id, 'order_cancellation',
           sum(sl.base_quantity), p.base_unit
    FROM sales_lines sl
    JOIN products p ON p.id = sl.product_id
    WHERE sl.transaction_id = NEW.original_sale_id
    GROUP BY sl.product_id, p.base_unit;

    UPDATE sales_invoices
    SET order_status = 'cancelled'
    WHERE transaction_id = NEW.original_sale_id;

    INSERT INTO customer_account_entries (
        transaction_id, customer_id, currency_code, credit, description
    ) VALUES (
        NEW.transaction_id, sale.customer_id, 'YER', sale.final_total,
        'Full external order cancellation'
    );

    RETURN NEW;
END;
$$;

INSERT INTO schema_migrations (version)
VALUES ('005_transaction_safe_return_limits');

COMMIT;
