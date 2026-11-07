BEGIN;

DO $$
DECLARE
    existing_movements bigint;
BEGIN
    SELECT count(*) INTO existing_movements FROM inventory_movements;
    IF existing_movements > 0 THEN
        RAISE EXCEPTION
            'Migration 008 stopped: % existing inventory movements need source-line reconciliation before adding idempotency keys',
            existing_movements;
    END IF;
END;
$$;

ALTER TABLE inventory_movements
    ADD COLUMN source_id uuid NOT NULL;

CREATE UNIQUE INDEX inventory_movements_source_once_idx
    ON inventory_movements (movement_type, source_id);

CREATE FUNCTION validate_inventory_movement_source() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    source_product_id uuid;
    expected_delta numeric(18, 3);
BEGIN
    CASE NEW.movement_type
        WHEN 'opening_balance' THEN
            SELECT product_id, quantity INTO source_product_id, expected_delta
            FROM opening_inventory_lines
            WHERE id = NEW.source_id AND transaction_id = NEW.transaction_id;
        WHEN 'purchase' THEN
            SELECT product_id, base_quantity INTO source_product_id, expected_delta
            FROM purchase_lines
            WHERE id = NEW.source_id AND transaction_id = NEW.transaction_id;
        WHEN 'sale' THEN
            SELECT product_id, -base_quantity INTO source_product_id, expected_delta
            FROM sales_lines
            WHERE id = NEW.source_id AND transaction_id = NEW.transaction_id;
        WHEN 'sales_return' THEN
            SELECT sl.product_id, srl.quantity INTO source_product_id, expected_delta
            FROM sales_return_lines srl
            JOIN sales_lines sl ON sl.id = srl.original_sale_line_id
            WHERE srl.id = NEW.source_id AND srl.transaction_id = NEW.transaction_id;
        WHEN 'purchase_return' THEN
            SELECT pl.product_id, -prl.quantity INTO source_product_id, expected_delta
            FROM purchase_return_lines prl
            JOIN purchase_lines pl ON pl.id = prl.original_purchase_line_id
            WHERE prl.id = NEW.source_id AND prl.transaction_id = NEW.transaction_id;
        WHEN 'stock_adjustment' THEN
            SELECT product_id, difference INTO source_product_id, expected_delta
            FROM stock_adjustment_lines
            WHERE id = NEW.source_id AND transaction_id = NEW.transaction_id;
        WHEN 'order_cancellation' THEN
            SELECT sl.product_id, sl.base_quantity
            INTO source_product_id, expected_delta
            FROM sales_lines sl
            JOIN order_cancellations oc
              ON oc.original_sale_id = sl.transaction_id
            WHERE sl.id = NEW.source_id
              AND oc.transaction_id = NEW.transaction_id;
        WHEN 'damaged_goods' THEN
            SELECT product_id, -quantity INTO source_product_id, expected_delta
            FROM damaged_goods
            WHERE transaction_id = NEW.source_id
              AND transaction_id = NEW.transaction_id;
        ELSE
            RAISE EXCEPTION 'Unsupported inventory movement type %', NEW.movement_type;
    END CASE;

    IF source_product_id IS NULL THEN
        RAISE EXCEPTION 'Inventory movement source % does not match transaction % and type %',
            NEW.source_id, NEW.transaction_id, NEW.movement_type;
    END IF;

    IF source_product_id <> NEW.product_id OR expected_delta <> NEW.quantity_delta THEN
        RAISE EXCEPTION 'Inventory movement does not match its source product or quantity';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER inventory_movement_validate_source
    BEFORE INSERT ON inventory_movements
    FOR EACH ROW EXECUTE FUNCTION validate_inventory_movement_source();

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
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    ) VALUES (
        NEW.transaction_id, purchase_line.product_id, 'purchase_return',
        -NEW.quantity, product_unit, NEW.id
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

    PERFORM 1 FROM sales_lines
    WHERE transaction_id = NEW.original_sale_id
    FOR UPDATE;

    IF EXISTS (
        SELECT 1 FROM sales_lines
        WHERE transaction_id = NEW.original_sale_id AND returned_quantity > 0
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
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    )
    SELECT NEW.transaction_id, sl.product_id, 'order_cancellation',
           sl.base_quantity, p.base_unit, sl.id
    FROM sales_lines sl
    JOIN products p ON p.id = sl.product_id
    WHERE sl.transaction_id = NEW.original_sale_id;

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

CREATE OR REPLACE FUNCTION post_damaged_goods() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    transaction_status text;
    product_unit base_inventory_unit;
BEGIN
    SELECT status INTO transaction_status
    FROM business_transactions
    WHERE id = NEW.transaction_id;
    IF transaction_status IS DISTINCT FROM 'approved' THEN
        RAISE EXCEPTION 'Damaged-goods operations require an approved transaction';
    END IF;

    SELECT base_unit INTO product_unit FROM products WHERE id = NEW.product_id;
    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    ) VALUES (
        NEW.transaction_id, NEW.product_id, 'damaged_goods',
        -NEW.quantity, product_unit, NEW.transaction_id
    );
    RETURN NEW;
END;
$$;

INSERT INTO schema_migrations (version)
VALUES ('008_inventory_movement_idempotency');

COMMIT;
