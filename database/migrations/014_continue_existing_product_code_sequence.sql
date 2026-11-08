BEGIN;

SELECT setval(
    'product_code_number_seq',
    GREATEST(
        COALESCE((
            SELECT MAX(sku::bigint)
            FROM products
            WHERE sku ~ '^[0-9]+$'
        ), 0) + 1,
        1
    ),
    false
);

ALTER TABLE products
    ALTER COLUMN sku SET DEFAULT nextval('product_code_number_seq')::text;

INSERT INTO schema_migrations (version)
VALUES ('014_continue_existing_product_code_sequence');

COMMIT;
