BEGIN;

DO $migration$
BEGIN
    IF to_regclass('public.product_price_history') IS NOT NULL THEN
        RAISE EXCEPTION 'product_price_history already exists; inspect migration history before continuing';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM suppliers
        GROUP BY supplier_type_id, lower(btrim(name))
        HAVING count(*) > 1
    ) THEN
        RAISE EXCEPTION 'Duplicate supplier names exist within a supplier type; resolve them before adding the unique index';
    END IF;

END;
$migration$;

CREATE UNIQUE INDEX suppliers_type_normalized_name_key
    ON suppliers (supplier_type_id, lower(btrim(name)));

ALTER TABLE purchase_invoices
    ADD COLUMN supplier_invoice_number text;

ALTER TABLE purchase_invoices
    ADD CONSTRAINT purchase_invoices_supplier_invoice_number_check
    CHECK (
        supplier_invoice_number IS NULL
        OR length(btrim(supplier_invoice_number)) BETWEEN 1 AND 100
    );

ALTER TABLE supplier_account_entries
    ADD COLUMN source_purchase_transaction_id uuid
        REFERENCES purchase_invoices(transaction_id);

ALTER TABLE supplier_account_entries
    ADD CONSTRAINT supplier_account_entries_purchase_source_check
    CHECK (
        source_purchase_transaction_id IS NULL
        OR source_purchase_transaction_id = transaction_id
    );

CREATE UNIQUE INDEX supplier_account_entries_purchase_source_key
    ON supplier_account_entries (source_purchase_transaction_id)
    WHERE source_purchase_transaction_id IS NOT NULL;

ALTER TABLE opening_inventory_lines
    ADD COLUMN entered_quantity numeric(18, 3),
    ADD COLUMN input_unit transaction_input_unit,
    ADD COLUMN conversion_factor numeric(18, 6);

UPDATE opening_inventory_lines AS line
SET entered_quantity = line.quantity,
    input_unit = product.base_unit::text::transaction_input_unit,
    conversion_factor = 1
FROM products AS product
WHERE product.id = line.product_id;

ALTER TABLE opening_inventory_lines
    ALTER COLUMN entered_quantity SET NOT NULL,
    ALTER COLUMN input_unit SET NOT NULL,
    ALTER COLUMN conversion_factor SET NOT NULL;

ALTER TABLE opening_inventory_lines
    ADD CONSTRAINT opening_inventory_lines_entered_quantity_check
        CHECK (entered_quantity >= 0),
    ADD CONSTRAINT opening_inventory_lines_conversion_factor_check
        CHECK (
            (input_unit = 'GALLON' AND conversion_factor = 7)
            OR (input_unit <> 'GALLON' AND conversion_factor = 1)
        ),
    ADD CONSTRAINT opening_inventory_lines_base_quantity_check
        CHECK (quantity = round(entered_quantity * conversion_factor, 3));

CREATE TRIGGER opening_inventory_lines_validate_unit
    BEFORE INSERT OR UPDATE ON opening_inventory_lines
    FOR EACH ROW EXECUTE FUNCTION validate_product_input_unit();

CREATE TRIGGER opening_inventory_lines_require_draft
    BEFORE INSERT ON opening_inventory_lines
    FOR EACH ROW EXECUTE FUNCTION require_draft_business_transaction();

CREATE FUNCTION prevent_final_opening_inventory_line_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    parent_status text;
BEGIN
    SELECT status
    INTO parent_status
    FROM business_transactions
    WHERE id = OLD.transaction_id;

    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Lines of final opening inventory documents are immutable';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER opening_inventory_lines_final_immutable
    BEFORE UPDATE OR DELETE ON opening_inventory_lines
    FOR EACH ROW EXECUTE FUNCTION prevent_final_opening_inventory_line_mutation();

CREATE FUNCTION prevent_duplicate_opening_inventory_approval()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.transaction_type = 'opening_inventory' AND NEW.status <> 'draft' THEN
            RAISE EXCEPTION 'Opening inventory transactions must be created as drafts';
        END IF;

        RETURN NEW;
    END IF;

    IF NEW.transaction_type = 'opening_inventory'
       AND NEW.status = 'approved'
       AND OLD.status = 'draft' THEN
        PERFORM pg_advisory_xact_lock(hashtext('honey_shop.opening_inventory.approval'));

        IF EXISTS (
            SELECT 1
            FROM opening_inventory AS opening
            JOIN business_transactions AS transaction
              ON transaction.id = opening.transaction_id
            WHERE transaction.transaction_type = 'opening_inventory'
              AND transaction.status = 'approved'
              AND transaction.id <> NEW.id
        ) THEN
            RAISE EXCEPTION 'Opening inventory has already been approved';
        END IF;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER business_transactions_opening_inventory_guard
    BEFORE INSERT OR UPDATE OF status ON business_transactions
    FOR EACH ROW EXECUTE FUNCTION prevent_duplicate_opening_inventory_approval();

CREATE FUNCTION prevent_final_opening_inventory_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    parent_status text;
BEGIN
    SELECT status
    INTO parent_status
    FROM business_transactions
    WHERE id = OLD.transaction_id;

    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Final opening inventory documents are immutable';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER opening_inventory_final_immutable
    BEFORE UPDATE OR DELETE ON opening_inventory
    FOR EACH ROW EXECUTE FUNCTION prevent_final_opening_inventory_mutation();

CREATE FUNCTION prevent_approved_purchase_invoice_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
DECLARE
    parent_status text;
BEGIN
    SELECT status
    INTO parent_status
    FROM business_transactions
    WHERE id = OLD.transaction_id;

    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Approved purchase invoices are immutable';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER purchase_invoices_final_immutable
    BEFORE UPDATE OR DELETE ON purchase_invoices
    FOR EACH ROW EXECUTE FUNCTION prevent_approved_purchase_invoice_mutation();

CREATE TABLE product_price_history (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id uuid NOT NULL REFERENCES products(id),
    previous_selling_price numeric(19, 4),
    new_selling_price numeric(19, 4) NOT NULL,
    previous_max_discount_rate numeric(7, 6),
    new_max_discount_rate numeric(7, 6) NOT NULL,
    actor_user_id uuid REFERENCES app_users(id),
    reason text,
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT product_price_history_initial_pair_check CHECK (
        (previous_selling_price IS NULL) = (previous_max_discount_rate IS NULL)
    ),
    CONSTRAINT product_price_history_price_check CHECK (
        (previous_selling_price IS NULL OR previous_selling_price >= 0)
        AND new_selling_price >= 0
    ),
    CONSTRAINT product_price_history_discount_check CHECK (
        (previous_max_discount_rate IS NULL OR previous_max_discount_rate BETWEEN 0 AND 1)
        AND new_max_discount_rate BETWEEN 0 AND 1
    ),
    CONSTRAINT product_price_history_reason_length_check CHECK (
        reason IS NULL OR length(reason) <= 500
    )
);

CREATE INDEX product_price_history_product_date_idx
    ON product_price_history (product_id, created_at DESC, id DESC);

CREATE FUNCTION reject_product_price_history_mutation()
RETURNS trigger
LANGUAGE plpgsql
AS $function$
BEGIN
    RAISE EXCEPTION 'Product price history is append-only';
    RETURN NULL;
END;
$function$;

CREATE TRIGGER product_price_history_append_only
    BEFORE UPDATE OR DELETE ON product_price_history
    FOR EACH ROW EXECUTE FUNCTION reject_product_price_history_mutation();

INSERT INTO product_price_history (
    product_id,
    previous_selling_price,
    new_selling_price,
    previous_max_discount_rate,
    new_max_discount_rate,
    reason
)
SELECT
    product.id,
    NULL,
    product.selling_price,
    NULL,
    product.max_discount_rate,
    'Baseline snapshot when price history was introduced'
FROM products AS product;

INSERT INTO schema_migrations (version)
VALUES ('010_supplier_purchase_support_and_price_history');

COMMIT;
