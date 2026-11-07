BEGIN;

CREATE OR REPLACE FUNCTION prevent_approved_original_line_mutation() RETURNS trigger
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
           AND (
               to_jsonb(NEW) - ARRAY[
                   'returned_quantity', 'base_quantity', 'line_total',
                   'effective_base_unit_cost'
               ]::text[]
           ) IS NOT DISTINCT FROM (
               to_jsonb(OLD) - ARRAY[
                   'returned_quantity', 'base_quantity', 'line_total',
                   'effective_base_unit_cost'
               ]::text[]
           )
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

INSERT INTO schema_migrations (version)
VALUES ('006_return_counter_trigger_fix');

COMMIT;
