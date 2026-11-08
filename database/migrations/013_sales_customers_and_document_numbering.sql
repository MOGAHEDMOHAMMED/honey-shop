BEGIN;

CREATE SEQUENCE product_code_number_seq;
SELECT setval(
    'product_code_number_seq',
    GREATEST(
        COALESCE((
            SELECT MAX(substring(sku FROM '^PRD-([0-9]+)$')::bigint)
            FROM products
            WHERE sku ~ '^PRD-[0-9]+$'
        ), 0) + 1,
        1
    ),
    false
);
ALTER TABLE products
    ALTER COLUMN sku SET DEFAULT (
        'PRD-' || lpad(nextval('product_code_number_seq')::text, 6, '0')
    );

CREATE SEQUENCE purchase_invoice_number_seq;
SELECT setval(
    'purchase_invoice_number_seq',
    GREATEST(
        COALESCE((
            SELECT MAX(substring(invoice_number FROM '^PUR-([0-9]+)$')::bigint)
            FROM purchase_invoices
            WHERE invoice_number ~ '^PUR-[0-9]+$'
        ), 0) + 1,
        1
    ),
    false
);
ALTER TABLE purchase_invoices
    ALTER COLUMN invoice_number SET DEFAULT (
        'PUR-' || lpad(nextval('purchase_invoice_number_seq')::text, 6, '0')
    );

CREATE TABLE sales_invoice_counters (
    series_category text PRIMARY KEY
        CHECK (series_category IN ('honey', 'ghee', 'nuts')),
    prefix text NOT NULL UNIQUE,
    last_issued_suffix bigint NOT NULL DEFAULT 0
        CHECK (last_issued_suffix >= 0)
);

INSERT INTO sales_invoice_counters (series_category, prefix, last_issued_suffix)
SELECT categories.series_category,
       categories.prefix,
       COALESCE(MAX(substring(invoice.invoice_number FROM length(categories.prefix) + 1)::bigint), 0)
FROM (VALUES ('honey', '74'), ('ghee', '84'), ('nuts', '94'))
    AS categories(series_category, prefix)
LEFT JOIN sales_invoices AS invoice
    ON invoice.invoice_number ~ ('^' || categories.prefix || '[0-9]+$')
GROUP BY categories.series_category, categories.prefix;

ALTER TABLE sales_invoices
    ADD COLUMN series_category text,
    ADD CONSTRAINT sales_invoices_series_category_check
        CHECK (series_category IS NULL OR series_category IN ('honey', 'ghee', 'nuts'));

ALTER TABLE sales_invoices
    DROP CONSTRAINT sales_invoices_order_status_check,
    ADD CONSTRAINT sales_invoices_order_status_check
        CHECK (order_status IN ('pending_shipment', 'shipped', 'delivered', 'cancelled'));

ALTER TABLE sales_lines
    ADD COLUMN discount_rate numeric(7, 6) NOT NULL DEFAULT 0
        CHECK (discount_rate BETWEEN 0 AND 1);

ALTER TABLE customer_account_entries
    ADD COLUMN source_sale_transaction_id uuid
        REFERENCES sales_invoices(transaction_id),
    ADD CONSTRAINT customer_account_entries_sale_source_check
        CHECK (
            source_sale_transaction_id IS NULL
            OR source_sale_transaction_id = transaction_id
        );

CREATE UNIQUE INDEX customer_account_entries_sale_source_key
    ON customer_account_entries (source_sale_transaction_id)
    WHERE source_sale_transaction_id IS NOT NULL;

CREATE FUNCTION prevent_issued_sales_invoice_number_mutation()
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

    IF parent_status = 'approved'
       AND (TG_OP = 'DELETE' OR OLD.invoice_number IS DISTINCT FROM NEW.invoice_number) THEN
        RAISE EXCEPTION 'Issued sales invoice numbers are permanent';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;

    RETURN NEW;
END;
$function$;

CREATE TRIGGER sales_invoices_issued_number_immutable
    BEFORE UPDATE OF invoice_number OR DELETE ON sales_invoices
    FOR EACH ROW EXECUTE FUNCTION prevent_issued_sales_invoice_number_mutation();

CREATE INDEX customers_name_search_idx ON customers (lower(name));

INSERT INTO schema_migrations (version)
VALUES ('013_sales_customers_and_document_numbering');

COMMIT;
