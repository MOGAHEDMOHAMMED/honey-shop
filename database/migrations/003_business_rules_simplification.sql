BEGIN;

DO $$
DECLARE
    conflict_count bigint;
BEGIN
    SELECT count(*) INTO conflict_count
    FROM product_prices
    WHERE currency_code <> 'YER';
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % non-YER product prices cannot be represented by the single-price YER product model',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM products
    WHERE base_unit NOT IN ('kg', 'can');
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % products use unsupported base units; resolve them before migration',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM purchase_lines
    WHERE lower(purchasing_unit) NOT IN ('kg', 'gallon', 'can');
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % purchase lines use unsupported input units',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM sales_lines sl
    JOIN products p ON p.id = sl.product_id
    WHERE p.base_unit NOT IN ('kg', 'can');
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % sales lines cannot be mapped to the revised units',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count FROM order_cancellations;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % existing cancellations lack confirmed full-shipment return data',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count FROM order_cancellation_lines;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % cancellation lines cannot be discarded; migrate them before retrying',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM sales_return_lines
    WHERE product_condition <> 'resalable';
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % non-saleable sales-return lines conflict with the revised returns model',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM sales_invoices
    WHERE actual_delivery_cost IS NOT NULL
       OR (delivery_charge > 0 AND delivery_cost_bearer IS NULL);
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % sales invoices contain delivery-cost data requiring review',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count FROM expenses;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % expenses have legacy payment-account fields; reconcile them before migration',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count FROM exchange_rates;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % exchange rates would be removed; archive or resolve them before migration',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count FROM transaction_currency_conversions;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % currency conversions would be removed; archive or resolve them before migration',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM financial_accounts WHERE currency_code <> 'YER';
    conflict_count := conflict_count + (
        SELECT count(*) FROM purchase_invoices WHERE currency_code <> 'YER'
    ) + (
        SELECT count(*) FROM sales_invoices WHERE currency_code <> 'YER'
    ) + (
        SELECT count(*) FROM purchase_returns WHERE currency_code <> 'YER'
    ) + (
        SELECT count(*) FROM sales_returns WHERE currency_code <> 'YER'
    ) + (
        SELECT count(*) FROM vouchers WHERE currency_code <> 'YER'
    ) + (
        SELECT count(*) FROM account_transfers WHERE currency_code <> 'YER'
    ) + (
        SELECT count(*) FROM customer_account_entries WHERE currency_code <> 'YER'
    ) + (
        SELECT count(*) FROM supplier_account_entries WHERE currency_code <> 'YER'
    );
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % existing financial records use a currency other than YER',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM purchase_return_lines prl
    JOIN purchase_returns pr ON pr.transaction_id = prl.transaction_id
    WHERE (
        SELECT count(*)
        FROM purchase_lines pl
        WHERE pl.transaction_id = pr.original_purchase_id
          AND pl.product_id = prl.product_id
    ) <> 1;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % purchase-return lines do not identify exactly one original purchase line',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM sales_returns sr
    JOIN sales_return_lines srl ON srl.transaction_id = sr.transaction_id
    LEFT JOIN sales_lines sl ON sl.id = srl.original_sale_line_id
    WHERE sl.id IS NULL OR sl.transaction_id <> sr.original_sale_id;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: % sales-return lines do not match their original invoice',
            conflict_count;
    END IF;

    SELECT count(*) INTO conflict_count
    FROM (
        SELECT pr.original_purchase_id, pl.id
        FROM purchase_return_lines prl
        JOIN purchase_returns pr ON pr.transaction_id = prl.transaction_id
        JOIN purchase_lines pl
          ON pl.transaction_id = pr.original_purchase_id
         AND pl.product_id = prl.product_id
        GROUP BY pr.original_purchase_id, pl.id
        HAVING sum(prl.quantity) > max(pl.base_quantity)
    ) returned;
    IF conflict_count > 0 THEN
        RAISE EXCEPTION
            'Migration 003 stopped: existing purchase-return quantities exceed purchased quantities';
    END IF;
END;
$$;

CREATE TYPE base_inventory_unit AS ENUM ('KG', 'CAN');
CREATE TYPE transaction_input_unit AS ENUM ('KG', 'GALLON', 'CAN');

ALTER TABLE currencies
    ADD COLUMN is_active boolean NOT NULL DEFAULT false;
UPDATE currencies SET is_active = (code = 'YER');
ALTER TABLE currencies
    ADD CONSTRAINT currencies_first_release_active_check
        CHECK (is_active = (code = 'YER'));

ALTER TABLE products
    DROP CONSTRAINT products_base_unit_check,
    ADD COLUMN selling_price numeric(19, 4) NOT NULL DEFAULT 0
        CHECK (selling_price >= 0),
    ADD COLUMN max_discount_rate numeric(7, 6) NOT NULL DEFAULT 0
        CHECK (max_discount_rate BETWEEN 0 AND 1);

UPDATE products p
SET selling_price = pp.unit_price,
    max_discount_rate = pp.max_discount_rate
FROM product_prices pp
WHERE pp.product_id = p.id
  AND pp.currency_code = 'YER';

ALTER TABLE products
    ALTER COLUMN base_unit TYPE base_inventory_unit
        USING upper(base_unit)::base_inventory_unit;

ALTER TABLE purchase_lines
    RENAME COLUMN purchasing_unit TO input_unit;
ALTER TABLE purchase_lines
    RENAME COLUMN purchased_quantity TO entered_quantity;
ALTER TABLE purchase_lines
    ALTER COLUMN input_unit TYPE transaction_input_unit
        USING upper(input_unit)::transaction_input_unit,
    ADD CONSTRAINT purchase_lines_input_conversion_value_check
        CHECK (
            (input_unit = 'GALLON' AND conversion_factor = 7)
            OR (input_unit <> 'GALLON' AND conversion_factor = 1)
        );

ALTER TABLE sales_lines DROP COLUMN line_total;
ALTER TABLE sales_lines RENAME COLUMN quantity TO entered_quantity;
ALTER TABLE sales_lines
    ADD COLUMN input_unit transaction_input_unit,
    ADD COLUMN conversion_factor numeric(18, 6) NOT NULL DEFAULT 1
        CHECK (conversion_factor > 0);

UPDATE sales_lines sl
SET input_unit = p.base_unit::text::transaction_input_unit
FROM products p
WHERE p.id = sl.product_id;

ALTER TABLE sales_lines
    ALTER COLUMN input_unit SET NOT NULL,
    ADD COLUMN base_quantity numeric(18, 3) GENERATED ALWAYS AS
        (entered_quantity * conversion_factor) STORED,
    ADD COLUMN line_total numeric(19, 4) GENERATED ALWAYS AS
        (entered_quantity * unit_price - discount_amount) STORED,
    ADD CONSTRAINT sales_lines_input_conversion_value_check
        CHECK (
            (input_unit = 'GALLON' AND conversion_factor = 7)
            OR (input_unit <> 'GALLON' AND conversion_factor = 1)
        );

ALTER TABLE inventory_movements
    DROP CONSTRAINT inventory_movements_base_unit_check,
    ALTER COLUMN base_unit TYPE base_inventory_unit
        USING upper(base_unit)::base_inventory_unit,
    DROP CONSTRAINT inventory_movements_movement_type_check,
    ADD CONSTRAINT inventory_movements_movement_type_check
        CHECK (
            movement_type IN (
                'opening_balance', 'purchase', 'sale', 'sales_return',
                'purchase_return', 'stock_adjustment', 'order_cancellation',
                'damaged_goods'
            )
        );

CREATE OR REPLACE FUNCTION apply_inventory_movement() RETURNS trigger
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

ALTER TABLE sales_invoices
    DROP COLUMN actual_delivery_cost,
    ALTER COLUMN delivery_cost_bearer SET DEFAULT 'shop',
    ALTER COLUMN delivery_cost_bearer SET NOT NULL,
    ALTER COLUMN currency_code SET DEFAULT 'YER',
    ADD CONSTRAINT sales_invoices_shop_delivery_charge_check
        CHECK (delivery_cost_bearer <> 'shop' OR delivery_charge = 0);

ALTER TABLE purchase_invoices
    ALTER COLUMN currency_code SET DEFAULT 'YER';

ALTER TABLE financial_accounts
    ADD COLUMN is_default boolean NOT NULL DEFAULT false,
    ADD CONSTRAINT financial_accounts_default_cash_check
        CHECK (
            NOT is_default
            OR (account_type = 'cash' AND representative_id IS NULL)
        ),
    ADD CONSTRAINT financial_accounts_yer_only_check
        CHECK (currency_code = 'YER');

CREATE UNIQUE INDEX financial_accounts_one_default_cash_idx
    ON financial_accounts (is_default) WHERE is_default;
CREATE UNIQUE INDEX financial_accounts_one_rep_custody_idx
    ON financial_accounts (representative_id)
    WHERE account_type = 'representative_custody';

ALTER TABLE purchase_invoices
    ADD CONSTRAINT purchase_invoices_yer_only_check
        CHECK (currency_code = 'YER');
ALTER TABLE sales_invoices
    ADD CONSTRAINT sales_invoices_yer_only_check
        CHECK (currency_code = 'YER');
ALTER TABLE purchase_returns
    ADD CONSTRAINT purchase_returns_yer_only_check
        CHECK (currency_code = 'YER');
ALTER TABLE sales_returns
    ADD CONSTRAINT sales_returns_yer_only_check
        CHECK (currency_code = 'YER');
ALTER TABLE vouchers
    ADD CONSTRAINT vouchers_yer_only_check
        CHECK (currency_code = 'YER');
ALTER TABLE account_transfers
    ADD CONSTRAINT account_transfers_yer_only_check
        CHECK (currency_code = 'YER');
ALTER TABLE customer_account_entries
    ADD CONSTRAINT customer_account_entries_yer_only_check
        CHECK (currency_code = 'YER');
ALTER TABLE supplier_account_entries
    ADD CONSTRAINT supplier_account_entries_yer_only_check
        CHECK (currency_code = 'YER');

ALTER TABLE purchase_lines
    ADD CONSTRAINT purchase_lines_id_transaction_unique
        UNIQUE (id, transaction_id);
ALTER TABLE sales_lines
    ADD CONSTRAINT sales_lines_id_transaction_unique
        UNIQUE (id, transaction_id);

ALTER TABLE purchase_return_lines
    ADD COLUMN original_purchase_line_id uuid,
    ADD COLUMN original_purchase_id uuid;

UPDATE purchase_return_lines prl
SET original_purchase_line_id = pl.id,
    original_purchase_id = pr.original_purchase_id
FROM purchase_returns pr
JOIN purchase_lines pl
  ON pl.transaction_id = pr.original_purchase_id
WHERE pr.transaction_id = prl.transaction_id
  AND pl.product_id = prl.product_id;

ALTER TABLE purchase_returns
    ADD CONSTRAINT purchase_returns_id_original_unique
        UNIQUE (transaction_id, original_purchase_id);
ALTER TABLE purchase_return_lines
    ALTER COLUMN original_purchase_line_id SET NOT NULL,
    ALTER COLUMN original_purchase_id SET NOT NULL,
    ADD CONSTRAINT purchase_return_lines_header_fk
        FOREIGN KEY (transaction_id, original_purchase_id)
        REFERENCES purchase_returns (transaction_id, original_purchase_id),
    ADD CONSTRAINT purchase_return_lines_original_line_fk
        FOREIGN KEY (original_purchase_line_id, original_purchase_id)
        REFERENCES purchase_lines (id, transaction_id);

ALTER TABLE purchase_return_lines
    DROP COLUMN product_id;

ALTER TABLE sales_returns
    ADD CONSTRAINT sales_returns_id_original_unique
        UNIQUE (transaction_id, original_sale_id);
ALTER TABLE sales_return_lines
    ADD COLUMN original_sale_id uuid;

UPDATE sales_return_lines srl
SET original_sale_id = sr.original_sale_id
FROM sales_returns sr
WHERE sr.transaction_id = srl.transaction_id;

ALTER TABLE sales_return_lines
    ALTER COLUMN original_sale_id SET NOT NULL,
    ADD CONSTRAINT sales_return_lines_header_fk
        FOREIGN KEY (transaction_id, original_sale_id)
        REFERENCES sales_returns (transaction_id, original_sale_id),
    ADD CONSTRAINT sales_return_lines_original_line_fk
        FOREIGN KEY (original_sale_line_id, original_sale_id)
        REFERENCES sales_lines (id, transaction_id),
    ADD CONSTRAINT sales_return_lines_one_line_per_return
        UNIQUE (transaction_id, original_sale_line_id);

ALTER TABLE sales_return_lines
    DROP COLUMN product_condition;

ALTER TABLE order_cancellations
    ADD COLUMN cancelled_at timestamptz NOT NULL DEFAULT now(),
    ADD COLUMN shipment_returned boolean NOT NULL DEFAULT false,
    ADD CONSTRAINT order_cancellations_shipment_returned_check
        CHECK (shipment_returned);

ALTER TABLE expenses
    DROP CONSTRAINT expenses_financial_account_id_fkey,
    DROP COLUMN financial_account_id,
    ADD COLUMN paid_amount numeric(19, 4) NOT NULL DEFAULT 0
        CHECK (paid_amount >= 0 AND paid_amount <= amount),
    ADD COLUMN remaining_amount numeric(19, 4) GENERATED ALWAYS AS
        (amount - paid_amount) STORED,
    ADD COLUMN related_sale_id uuid REFERENCES sales_invoices(transaction_id);

CREATE UNIQUE INDEX expenses_one_delivery_expense_per_sale_idx
    ON expenses (related_sale_id) WHERE related_sale_id IS NOT NULL;

ALTER TABLE vouchers
    ADD COLUMN expense_id uuid REFERENCES expenses(transaction_id),
    ADD CONSTRAINT vouchers_expense_payment_only_check
        CHECK (expense_id IS NULL OR voucher_kind = 'payment');

CREATE UNIQUE INDEX account_transactions_one_posting_per_account_idx
    ON account_transactions (transaction_id, account_id);

CREATE TABLE damaged_goods (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    product_id uuid NOT NULL REFERENCES products(id),
    quantity numeric(18, 3) NOT NULL CHECK (quantity > 0),
    damaged_at timestamptz NOT NULL DEFAULT now(),
    reason text NOT NULL,
    recorded_by uuid NOT NULL REFERENCES app_users(id)
);

ALTER TABLE sales_return_lines
    ALTER COLUMN quantity TYPE numeric(18, 3);

CREATE FUNCTION validate_product_input_unit() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    product_base_unit base_inventory_unit;
    product_category text;
    allowed boolean;
BEGIN
    SELECT p.base_unit, lower(btrim(pc.name))
    INTO product_base_unit, product_category
    FROM products p
    JOIN product_categories pc ON pc.id = p.category_id
    WHERE p.id = NEW.product_id;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'Product % does not exist', NEW.product_id;
    END IF;

    IF product_category = 'honey' THEN
        allowed := product_base_unit = 'KG'
            AND NEW.input_unit IN ('KG', 'GALLON');
    ELSIF product_category = 'ghee' THEN
        allowed := product_base_unit = 'CAN'
            AND NEW.input_unit = 'CAN';
    ELSIF product_category = 'nuts' THEN
        allowed := product_base_unit = 'KG'
            AND NEW.input_unit = 'KG';
    ELSE
        allowed := NEW.input_unit::text = product_base_unit::text;
    END IF;

    IF NOT allowed THEN
        RAISE EXCEPTION 'Input unit % is not allowed for product category % with base unit %',
            NEW.input_unit, product_category, product_base_unit;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER purchase_lines_validate_unit
    BEFORE INSERT OR UPDATE OF product_id, input_unit ON purchase_lines
    FOR EACH ROW EXECUTE FUNCTION validate_product_input_unit();
CREATE TRIGGER sales_lines_validate_unit
    BEFORE INSERT OR UPDATE OF product_id, input_unit ON sales_lines
    FOR EACH ROW EXECUTE FUNCTION validate_product_input_unit();

CREATE FUNCTION prevent_approved_line_mutation() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    parent_status text;
    parent_id uuid;
BEGIN
    parent_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.transaction_id ELSE NEW.transaction_id END;

    SELECT status INTO parent_status
    FROM business_transactions
    WHERE id = parent_id;

    IF parent_status = 'approved' THEN
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
    FOR EACH ROW EXECUTE FUNCTION prevent_approved_line_mutation();
CREATE TRIGGER sales_lines_approved_immutable
    BEFORE UPDATE OR DELETE ON sales_lines
    FOR EACH ROW EXECUTE FUNCTION prevent_approved_line_mutation();
CREATE TRIGGER purchase_return_lines_approved_immutable
    BEFORE UPDATE OR DELETE ON purchase_return_lines
    FOR EACH ROW EXECUTE FUNCTION prevent_approved_line_mutation();
CREATE TRIGGER sales_return_lines_approved_immutable
    BEFORE UPDATE OR DELETE ON sales_return_lines
    FOR EACH ROW EXECUTE FUNCTION prevent_approved_line_mutation();

CREATE FUNCTION post_sales_return_line() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    sale_line sales_lines%ROWTYPE;
    transaction_status text;
    previously_returned numeric(18, 3);
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
    WHERE id = NEW.original_sale_line_id
    FOR UPDATE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Original sales line % does not exist', NEW.original_sale_line_id;
    END IF;

    SELECT COALESCE(sum(quantity), 0) INTO previously_returned
    FROM sales_return_lines srl
    JOIN business_transactions bt ON bt.id = srl.transaction_id
    WHERE srl.original_sale_line_id = NEW.original_sale_line_id
      AND bt.status = 'approved';

    IF previously_returned > sale_line.base_quantity THEN
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

CREATE TRIGGER sales_return_line_posting
    AFTER INSERT ON sales_return_lines
    FOR EACH ROW EXECUTE FUNCTION post_sales_return_line();

CREATE FUNCTION post_purchase_return_line() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    purchase_line purchase_lines%ROWTYPE;
    purchase_transaction_id uuid;
    transaction_status text;
    previously_returned numeric(18, 3);
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

    SELECT * INTO purchase_line
    FROM purchase_lines
    WHERE id = NEW.original_purchase_line_id
      AND transaction_id = purchase_transaction_id
    FOR UPDATE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Original purchase line does not belong to this purchase return';
    END IF;

    SELECT COALESCE(sum(prl.quantity), 0) INTO previously_returned
    FROM purchase_return_lines prl
    JOIN business_transactions bt ON bt.id = prl.transaction_id
    WHERE prl.original_purchase_line_id = NEW.original_purchase_line_id
      AND bt.status = 'approved';

    IF previously_returned > purchase_line.base_quantity THEN
        RAISE EXCEPTION 'Cumulative returned quantity exceeds the original purchased quantity';
    END IF;

    SELECT p.base_unit INTO product_unit
    FROM products p WHERE p.id = purchase_line.product_id;
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

CREATE TRIGGER purchase_return_line_posting
    AFTER INSERT ON purchase_return_lines
    FOR EACH ROW EXECUTE FUNCTION post_purchase_return_line();

CREATE FUNCTION post_order_cancellation() RETURNS trigger
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

    IF EXISTS (
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

CREATE TRIGGER order_cancellation_posting
    AFTER INSERT ON order_cancellations
    FOR EACH ROW EXECUTE FUNCTION post_order_cancellation();

CREATE FUNCTION protect_cancelled_order_status() RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.order_status = 'cancelled' AND OLD.order_status IS DISTINCT FROM 'cancelled'
       AND NOT EXISTS (
           SELECT 1 FROM order_cancellations oc
           WHERE oc.original_sale_id = OLD.transaction_id
       ) THEN
        RAISE EXCEPTION 'Use a full order-cancellation transaction; do not cancel by changing status';
    END IF;
    IF OLD.order_status = 'cancelled' AND NEW.order_status <> 'cancelled' THEN
        RAISE EXCEPTION 'A cancelled order cannot be reactivated';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER sales_invoice_cancellation_guard
    BEFORE UPDATE OF order_status ON sales_invoices
    FOR EACH ROW EXECUTE FUNCTION protect_cancelled_order_status();

CREATE FUNCTION post_damaged_goods() RETURNS trigger
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
        transaction_id, product_id, movement_type, quantity_delta, base_unit
    ) VALUES (
        NEW.transaction_id, NEW.product_id, 'damaged_goods',
        -NEW.quantity, product_unit
    );
    RETURN NEW;
END;
$$;

CREATE TRIGGER damaged_goods_inventory_posting
    AFTER INSERT ON damaged_goods
    FOR EACH ROW EXECUTE FUNCTION post_damaged_goods();

CREATE FUNCTION validate_related_delivery_expense() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    category_name text;
    delivery_bearer text;
BEGIN
    IF NEW.related_sale_id IS NULL THEN
        RETURN NEW;
    END IF;

    SELECT lower(btrim(name)) INTO category_name
    FROM expenses_categories WHERE id = NEW.category_id;
    IF category_name <> 'delivery expenses' THEN
        RAISE EXCEPTION 'A related sales invoice may only be set for a delivery expense';
    END IF;

    SELECT delivery_cost_bearer INTO delivery_bearer
    FROM sales_invoices WHERE transaction_id = NEW.related_sale_id;
    IF delivery_bearer = 'customer' THEN
        RAISE EXCEPTION 'Customer-paid delivery must not create a shop delivery expense';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER expenses_delivery_rule
    BEFORE INSERT OR UPDATE OF category_id, related_sale_id ON expenses
    FOR EACH ROW EXECUTE FUNCTION validate_related_delivery_expense();

CREATE FUNCTION post_voucher() RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    transaction_status text;
    expense_updated integer;
BEGIN
    SELECT status INTO transaction_status
    FROM business_transactions
    WHERE id = NEW.transaction_id;
    IF transaction_status IS DISTINCT FROM 'approved' THEN
        RAISE EXCEPTION 'Vouchers must belong to approved transactions';
    END IF;

    INSERT INTO account_transactions (
        transaction_id, account_id, direction, amount
    ) VALUES (
        NEW.transaction_id, NEW.financial_account_id,
        CASE WHEN NEW.voucher_kind = 'receipt' THEN 'in' ELSE 'out' END,
        NEW.amount
    );

    IF NEW.expense_id IS NOT NULL THEN
        UPDATE expenses
        SET paid_amount = paid_amount + NEW.amount
        WHERE transaction_id = NEW.expense_id
          AND paid_amount + NEW.amount <= amount;
        GET DIAGNOSTICS expense_updated = ROW_COUNT;
        IF expense_updated <> 1 THEN
            RAISE EXCEPTION 'Expense payment exceeds its remaining amount or expense does not exist';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER vouchers_posting
    AFTER INSERT ON vouchers
    FOR EACH ROW EXECUTE FUNCTION post_voucher();

CREATE TRIGGER vouchers_append_only
    BEFORE UPDATE OR DELETE ON vouchers
    FOR EACH ROW EXECUTE FUNCTION reject_ledger_mutation();

INSERT INTO financial_accounts (name, account_type, currency_code, is_default)
VALUES ('Main Cashbox', 'cash', 'YER', true)
ON CONFLICT (is_default) WHERE is_default DO NOTHING;

INSERT INTO schema_migrations (version)
VALUES ('003_business_rules_simplification');

DROP TABLE product_prices;
DROP TABLE transaction_currency_conversions;
DROP TABLE exchange_rates;
DROP TABLE order_cancellation_lines;

COMMIT;
