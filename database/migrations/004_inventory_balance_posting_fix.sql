BEGIN;

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
    VALUES (NEW.product_id, 0, now())
    ON CONFLICT (product_id) DO NOTHING;

    UPDATE inventory_balances
    SET quantity = quantity + NEW.quantity_delta,
        updated_at = now()
    WHERE product_id = NEW.product_id;

    RETURN NEW;
END;
$$;

INSERT INTO schema_migrations (version)
VALUES ('004_inventory_balance_posting_fix');

COMMIT;
