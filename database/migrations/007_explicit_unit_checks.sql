BEGIN;

ALTER TABLE products
    ADD CONSTRAINT products_base_unit_allowed_check
        CHECK (base_unit IN ('KG', 'CAN'));

ALTER TABLE purchase_lines
    ADD CONSTRAINT purchase_lines_input_unit_allowed_check
        CHECK (input_unit IN ('KG', 'GALLON', 'CAN'));

ALTER TABLE sales_lines
    ADD CONSTRAINT sales_lines_input_unit_allowed_check
        CHECK (input_unit IN ('KG', 'GALLON', 'CAN'));

ALTER TABLE inventory_movements
    ADD CONSTRAINT inventory_movements_base_unit_allowed_check
        CHECK (base_unit IN ('KG', 'CAN'));

INSERT INTO schema_migrations (version)
VALUES ('007_explicit_unit_checks');

COMMIT;
