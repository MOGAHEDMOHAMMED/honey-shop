BEGIN;

DO $test$
DECLARE
    test_user uuid;
    supplier_id uuid;
    customer_id uuid;
    test_representative_id uuid;
    main_cash_id uuid;
    custody_id_first uuid;
    custody_id_second uuid;
    honey_id uuid;
    ghee_id uuid;
    nuts_id uuid;
    honey_category_id uuid;
    ghee_category_id uuid;
    nuts_category_id uuid;
    supplier_type_id uuid;
    opening_tx uuid;
    opening_honey_line_id uuid;
    opening_ghee_line_id uuid;
    opening_nuts_line_id uuid;
    purchase_tx uuid;
    purchase_id uuid;
    other_purchase_tx uuid;
    other_purchase_id uuid;
    other_purchase_line_id uuid;
    honey_purchase_line uuid;
    fractional_purchase_line uuid;
    ghee_line_id uuid;
    purchase_return_tx uuid;
    purchase_return_id uuid;
    sale_tx uuid;
    sale_id uuid;
    sale_line_id uuid;
    sale_gallon_line_id uuid;
    direct_sale_id uuid;
    direct_sale_line_id uuid;
    return_tx uuid;
    return_id uuid;
    cancellation_sale_tx uuid;
    cancellation_sale_id uuid;
    cancellation_sale_line_id uuid;
    cancellation_tx uuid;
    cancellation_id uuid;
    damage_tx uuid;
    delivery_shop_sale_id uuid;
    delivery_customer_sale_id uuid;
    delivery_shared_sale_id uuid;
    expense_tx uuid;
    expense_id uuid;
    voucher_tx uuid;
    rejected boolean;
    quantity_value numeric(18, 3);
    amount_value numeric(19, 4);
    status_value text;
BEGIN
    IF current_setting('server_version_num')::integer < 170000 THEN
        RAISE EXCEPTION 'Tests require PostgreSQL 17 or newer';
    END IF;

    IF (
        SELECT count(*)
        FROM schema_migrations
        WHERE version = ANY (ARRAY[
            '001_initial_schema',
            '002_ledger_and_currency_rules',
            '003_business_rules_simplification',
            '004_inventory_balance_posting_fix',
            '005_transaction_safe_return_limits',
            '006_return_counter_trigger_fix',
            '007_explicit_unit_checks',
            '008_inventory_movement_idempotency',
            '009_approval_and_return_guards'
        ])
    ) <> 9 THEN
        RAISE EXCEPTION 'Expected migrations 001 through 009 to be applied';
    END IF;

    IF (SELECT count(*) FROM currencies WHERE is_active) <> 1
       OR NOT EXISTS (
           SELECT 1 FROM currencies WHERE code = 'YER' AND is_active
       ) THEN
        RAISE EXCEPTION 'Only YER must be active';
    END IF;

    INSERT INTO app_users (username, display_name, password_hash)
    VALUES ('__business_rules_test__', 'Business Rules Test', 'test-only')
    RETURNING id INTO test_user;

    INSERT INTO supplier_types (name)
    VALUES ('__business_rules_test__')
    RETURNING id INTO supplier_type_id;
    INSERT INTO suppliers (supplier_type_id, name)
    VALUES (supplier_type_id, '__business_rules_test__')
    RETURNING id INTO supplier_id;
    INSERT INTO customers (name) VALUES ('__business_rules_test__')
    RETURNING id INTO customer_id;
    INSERT INTO representatives (name) VALUES ('__business_rules_test__')
    RETURNING id INTO test_representative_id;

    SELECT id INTO honey_category_id FROM product_categories WHERE lower(name) = 'honey';
    SELECT id INTO ghee_category_id FROM product_categories WHERE lower(name) = 'ghee';
    SELECT id INTO nuts_category_id FROM product_categories WHERE lower(name) = 'nuts';
    INSERT INTO products (name, category_id, base_unit, selling_price)
    VALUES ('__honey_test__', honey_category_id, 'KG', 100)
    RETURNING id INTO honey_id;
    INSERT INTO products (name, category_id, base_unit, selling_price)
    VALUES ('__ghee_test__', ghee_category_id, 'CAN', 500)
    RETURNING id INTO ghee_id;
    INSERT INTO products (name, category_id, base_unit, selling_price)
    VALUES ('__nuts_test__', nuts_category_id, 'KG', 200)
    RETURNING id INTO nuts_id;

    SELECT id INTO main_cash_id
    FROM financial_accounts WHERE is_default;
    IF main_cash_id IS NULL THEN
        RAISE EXCEPTION 'Main cashbox was not seeded';
    END IF;
    INSERT INTO financial_accounts (name, account_type, currency_code, is_default)
    VALUES ('Main Cashbox', 'cash', 'YER', true)
    ON CONFLICT (is_default) WHERE is_default DO NOTHING;
    IF (SELECT count(*) FROM financial_accounts WHERE is_default) <> 1 THEN
        RAISE EXCEPTION 'Main cashbox initialization is not idempotent';
    END IF;

    INSERT INTO financial_accounts (name, account_type, currency_code, representative_id)
    VALUES ('Test representative custody', 'representative_custody', 'YER', test_representative_id)
    ON CONFLICT (representative_id) WHERE account_type = 'representative_custody'
    DO UPDATE SET representative_id = EXCLUDED.representative_id
    RETURNING id INTO custody_id_first;
    INSERT INTO financial_accounts (name, account_type, currency_code, representative_id)
    VALUES ('Test representative custody', 'representative_custody', 'YER', test_representative_id)
    ON CONFLICT (representative_id) WHERE account_type = 'representative_custody'
    DO UPDATE SET representative_id = EXCLUDED.representative_id
    RETURNING id INTO custody_id_second;
    IF custody_id_first <> custody_id_second THEN
        RAISE EXCEPTION 'Representative custody account initialization is not idempotent';
    END IF;
    IF (SELECT account_type FROM financial_accounts WHERE id = custody_id_first) = 'cash' THEN
        RAISE EXCEPTION 'Representative funds must not be shop cash';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('receipt_voucher', 'approved', test_user, test_user, now())
    RETURNING id INTO voucher_tx;
    INSERT INTO vouchers (
        transaction_id, voucher_number, voucher_kind, voucher_type, currency_code,
        amount, financial_account_id, related_representative_id, payment_method
    ) VALUES (
        voucher_tx, '__representative_collection__', 'receipt', 'Customer Collection',
        'YER', 50, custody_id_first, test_representative_id, 'cash'
    );
    IF NOT EXISTS (
        SELECT 1 FROM account_transactions
        WHERE transaction_id = voucher_tx AND account_id = custody_id_first
          AND direction = 'in' AND amount = 50
    ) OR EXISTS (
        SELECT 1 FROM account_transactions
        WHERE transaction_id = voucher_tx AND account_id = main_cash_id
    ) THEN
        RAISE EXCEPTION 'Representative collection was mixed with shop cash';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('opening_inventory', 'approved', test_user, test_user, now())
    RETURNING id INTO opening_tx;
    INSERT INTO opening_inventory (transaction_id, effective_at)
    VALUES (opening_tx, now());
    INSERT INTO opening_inventory_lines (transaction_id, product_id, quantity)
    VALUES (opening_tx, honey_id, 500)
    RETURNING id INTO opening_honey_line_id;
    INSERT INTO opening_inventory_lines (transaction_id, product_id, quantity)
    VALUES (opening_tx, ghee_id, 20)
    RETURNING id INTO opening_ghee_line_id;
    INSERT INTO opening_inventory_lines (transaction_id, product_id, quantity)
    VALUES (opening_tx, nuts_id, 50)
    RETURNING id INTO opening_nuts_line_id;
    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    ) VALUES
        (opening_tx, honey_id, 'opening_balance', 500, 'KG', opening_honey_line_id),
        (opening_tx, ghee_id, 'opening_balance', 20, 'CAN', opening_ghee_line_id),
        (opening_tx, nuts_id, 'opening_balance', 50, 'KG', opening_nuts_line_id);

    rejected := false;
    BEGIN
        INSERT INTO inventory_movements (
            transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
        ) VALUES (opening_tx, honey_id, 'opening_balance', 500, 'KG', opening_honey_line_id);
    EXCEPTION WHEN unique_violation THEN
        rejected := true;
    END;
    IF NOT rejected OR (
        SELECT quantity FROM inventory_balances WHERE product_id = honey_id
    ) <> 500 THEN
        RAISE EXCEPTION 'Duplicate inventory posting was accepted or changed the balance';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('purchase', 'draft', test_user, NULL, NULL)
    RETURNING id INTO purchase_tx;
    INSERT INTO purchase_invoices (
        transaction_id, invoice_number, supplier_id, currency_code, invoice_total
    ) VALUES (purchase_tx, '__purchase_test__', supplier_id, 'YER', 14500)
    RETURNING transaction_id INTO purchase_id;
    INSERT INTO purchase_lines (
        transaction_id, product_id, input_unit, conversion_factor,
        entered_quantity, purchase_unit_price
    ) VALUES (purchase_id, honey_id, 'GALLON', 7, 20, 700)
    RETURNING id INTO honey_purchase_line;

    SELECT base_quantity INTO quantity_value
    FROM purchase_lines WHERE id = honey_purchase_line;
    IF quantity_value <> 140 THEN
        RAISE EXCEPTION '20 gallons should convert to 140 kg, got %', quantity_value;
    END IF;

    INSERT INTO purchase_lines (
        transaction_id, product_id, input_unit, conversion_factor,
        entered_quantity, purchase_unit_price
    ) VALUES (purchase_id, honey_id, 'KG', 1, 0.5, 100)
    RETURNING id INTO fractional_purchase_line;
    SELECT base_quantity INTO quantity_value
    FROM purchase_lines WHERE id = fractional_purchase_line;
    IF quantity_value <> 0.5 THEN
        RAISE EXCEPTION 'Fractional kilogram quantity was not preserved';
    END IF;

    INSERT INTO purchase_lines (
        transaction_id, product_id, input_unit, conversion_factor,
        entered_quantity, purchase_unit_price
    ) VALUES (purchase_id, ghee_id, 'CAN', 1, 2, 500);
    INSERT INTO purchase_lines (
        transaction_id, product_id, input_unit, conversion_factor,
        entered_quantity, purchase_unit_price
    ) VALUES (purchase_id, nuts_id, 'KG', 1, 1, 200);
    INSERT INTO purchase_lines (
        transaction_id, product_id, input_unit, conversion_factor,
        entered_quantity, purchase_unit_price
    ) VALUES (purchase_id, ghee_id, 'CAN', 1, 1, 500)
    RETURNING id INTO ghee_line_id;
    rejected := false;
    BEGIN
        INSERT INTO purchase_lines (
            transaction_id, product_id, input_unit, conversion_factor,
            entered_quantity, purchase_unit_price
        ) VALUES (purchase_id, ghee_id, 'GALLON', 7, 1, 3500);
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'GALLON was accepted for a ghee product';
    END IF;

    UPDATE business_transactions
    SET status = 'approved', approved_by = test_user, approved_at = now()
    WHERE id = purchase_id;

    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    )
    SELECT pl.transaction_id, pl.product_id, 'purchase', pl.base_quantity,
           p.base_unit, pl.id
    FROM purchase_lines pl
    JOIN products p ON p.id = pl.product_id
    WHERE pl.transaction_id = purchase_id;

    rejected := false;
    BEGIN
        UPDATE purchase_lines SET conversion_factor = 1
        WHERE id = honey_purchase_line;
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected OR (SELECT conversion_factor FROM purchase_lines
                        WHERE id = honey_purchase_line) <> 7 THEN
        RAISE EXCEPTION 'Approved purchase conversion factor was mutable';
    END IF;
    rejected := false;
    BEGIN
        INSERT INTO purchase_lines (
            transaction_id, product_id, input_unit, conversion_factor,
            entered_quantity, purchase_unit_price
        ) VALUES (purchase_id, honey_id, 'KG', 1, 1, 100);
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'A line was added after purchase approval';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('purchase', 'draft', test_user, NULL, NULL)
    RETURNING id INTO other_purchase_tx;
    INSERT INTO purchase_invoices (
        transaction_id, invoice_number, supplier_id, currency_code, invoice_total
    ) VALUES (other_purchase_tx, '__other_purchase_test__', supplier_id, 'YER', 100)
    RETURNING transaction_id INTO other_purchase_id;
    INSERT INTO purchase_lines (
        transaction_id, product_id, input_unit, conversion_factor,
        entered_quantity, purchase_unit_price
    ) VALUES (other_purchase_id, honey_id, 'KG', 1, 1, 100)
    RETURNING id INTO other_purchase_line_id;

    rejected := false;
    BEGIN
        UPDATE financial_accounts SET currency_code = 'SAR'
        WHERE id = main_cash_id;
    EXCEPTION WHEN check_violation THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'A non-YER account currency was accepted';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sale', 'draft', test_user, NULL, NULL)
    RETURNING id INTO sale_tx;
    rejected := false;
    BEGIN
        INSERT INTO sales_invoices (
            transaction_id, invoice_number, customer_id, sales_channel, currency_code,
            order_status, products_subtotal, final_total
        ) VALUES (
            sale_tx, '__non_yer_sale_test__', customer_id, 'external', 'SAR',
            'shipped', 100, 100
        );
    EXCEPTION WHEN check_violation THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'A non-YER sales invoice was accepted';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sale', 'draft', test_user, NULL, NULL)
    RETURNING id INTO sale_tx;
    INSERT INTO sales_invoices (
        transaction_id, invoice_number, customer_id, sales_channel, currency_code,
        order_status, products_subtotal, final_total, delivery_cost_bearer
    ) VALUES (
        sale_tx, '__sale_return_test__', customer_id, 'external', 'YER',
        'shipped', 1000, 1000, 'shop'
    ) RETURNING transaction_id INTO sale_id;
    INSERT INTO sales_lines (
        transaction_id, product_id, entered_quantity, input_unit,
        conversion_factor, unit_price, discount_amount
    ) VALUES (sale_id, honey_id, 10, 'KG', 1, 100, 0)
    RETURNING id INTO sale_line_id;
    INSERT INTO sales_lines (
        transaction_id, product_id, entered_quantity, input_unit,
        conversion_factor, unit_price, discount_amount
    ) VALUES (sale_id, honey_id, 1, 'GALLON', 7, 700, 0)
    RETURNING id INTO sale_gallon_line_id;
    SELECT base_quantity, unit_price INTO quantity_value, amount_value
    FROM sales_lines
    WHERE transaction_id = sale_id AND input_unit = 'GALLON';
    IF quantity_value <> 7 OR amount_value <> 700 THEN
        RAISE EXCEPTION 'Honey gallon sale did not preserve conversion and historical price';
    END IF;
    UPDATE business_transactions
    SET status = 'approved', approved_by = test_user, approved_at = now()
    WHERE id = sale_id;
    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    ) VALUES
        (sale_id, honey_id, 'sale', -10, 'KG', sale_line_id),
        (sale_id, honey_id, 'sale', -7, 'KG', sale_gallon_line_id);
    rejected := false;
    BEGIN
        INSERT INTO sales_lines (
            transaction_id, product_id, entered_quantity, input_unit,
            conversion_factor, unit_price, discount_amount
        ) VALUES (sale_id, honey_id, 1, 'KG', 1, 100, 0);
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'A sales line was added after approval';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sales_return', 'approved', test_user, test_user, now())
    RETURNING id INTO return_tx;
    INSERT INTO sales_returns (transaction_id, customer_id, original_sale_id, currency_code)
    VALUES (return_tx, customer_id, sale_id, 'YER')
    RETURNING transaction_id INTO return_id;
    INSERT INTO sales_return_lines (
        transaction_id, original_sale_id, original_sale_line_id, quantity, amount, reason
    ) VALUES (return_id, sale_id, sale_line_id, 3, 300, 'Test partial return');

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sales_return', 'approved', test_user, test_user, now())
    RETURNING id INTO return_tx;
    INSERT INTO sales_returns (transaction_id, customer_id, original_sale_id, currency_code)
    VALUES (return_tx, customer_id, sale_id, 'YER')
    RETURNING transaction_id INTO return_id;
    INSERT INTO sales_return_lines (
        transaction_id, original_sale_id, original_sale_line_id, quantity, amount, reason
    ) VALUES (return_id, sale_id, sale_line_id, 2, 200, 'Test second partial return');

    rejected := false;
    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sales_return', 'approved', test_user, test_user, now())
    RETURNING id INTO return_tx;
    INSERT INTO sales_returns (transaction_id, customer_id, original_sale_id, currency_code)
    VALUES (return_tx, customer_id, sale_id, 'YER')
    RETURNING transaction_id INTO return_id;
    BEGIN
        INSERT INTO sales_return_lines (
            transaction_id, original_sale_id, original_sale_line_id, quantity, amount, reason
        ) VALUES (return_id, sale_id, sale_line_id, 6, 600, 'Excess return test');
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'Cumulative sales returns exceeding sold quantity were accepted';
    END IF;

    IF (SELECT order_status FROM sales_invoices WHERE transaction_id = sale_id) <> 'shipped' THEN
        RAISE EXCEPTION 'Partial return incorrectly cancelled the whole order';
    END IF;

    rejected := false;
    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('order_cancellation', 'approved', test_user, test_user, now())
    RETURNING id INTO cancellation_tx;
    BEGIN
        INSERT INTO order_cancellations (
            transaction_id, original_sale_id, reason, shipment_returned
        ) VALUES (cancellation_tx, sale_id, 'Must not cancel a partially returned order', true);
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected OR EXISTS (
        SELECT 1 FROM order_cancellations WHERE original_sale_id = sale_id
    ) THEN
        RAISE EXCEPTION 'Order with partial returns was accepted for full cancellation';
    END IF;

    rejected := false;
    BEGIN
        UPDATE sales_invoices SET order_status = 'cancelled'
        WHERE transaction_id = sale_id;
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'Order status was cancelled without a full-cancellation record';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sale', 'draft', test_user, NULL, NULL)
    RETURNING id INTO cancellation_sale_tx;
    INSERT INTO sales_invoices (
        transaction_id, invoice_number, customer_id, sales_channel, currency_code,
        order_status, products_subtotal, final_total, delivery_cost_bearer
    ) VALUES (
        cancellation_sale_tx, '__sale_cancel_test__', customer_id, 'external', 'YER',
        'shipped', 200, 200, 'shop'
    ) RETURNING transaction_id INTO cancellation_sale_id;
    INSERT INTO sales_lines (
        transaction_id, product_id, entered_quantity, input_unit,
        conversion_factor, unit_price, discount_amount
    ) VALUES (cancellation_sale_id, honey_id, 2, 'KG', 1, 100, 0)
    RETURNING id INTO cancellation_sale_line_id;
    UPDATE business_transactions
    SET status = 'approved', approved_by = test_user, approved_at = now()
    WHERE id = cancellation_sale_id;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sales_return', 'approved', test_user, test_user, now())
    RETURNING id INTO return_tx;
    INSERT INTO sales_returns (transaction_id, customer_id, original_sale_id, currency_code)
    VALUES (return_tx, customer_id, sale_id, 'YER')
    RETURNING transaction_id INTO return_id;
    rejected := false;
    BEGIN
        INSERT INTO sales_return_lines (
            transaction_id, original_sale_id, original_sale_line_id, quantity, amount, reason
        ) VALUES (
            return_id, sale_id, cancellation_sale_line_id, 1, 100, 'Wrong invoice-line reference'
        );
    EXCEPTION WHEN foreign_key_violation THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'Sales return accepted a line from a different invoice';
    END IF;

    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    ) VALUES (cancellation_sale_id, honey_id, 'sale', -2, 'KG', cancellation_sale_line_id);

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('order_cancellation', 'approved', test_user, test_user, now())
    RETURNING id INTO cancellation_tx;
    INSERT INTO order_cancellations (
        transaction_id, original_sale_id, reason, shipment_returned
    ) VALUES (cancellation_tx, cancellation_sale_id, 'Complete shipment returned', true)
    RETURNING transaction_id INTO cancellation_id;

    SELECT quantity INTO quantity_value
    FROM inventory_balances WHERE product_id = honey_id;
    IF quantity_value <> 628.5 THEN
        RAISE EXCEPTION 'Order cancellation or returns posted inventory incorrectly: %', quantity_value;
    END IF;
    IF (SELECT order_status FROM sales_invoices
        WHERE transaction_id = cancellation_sale_id) <> 'cancelled' THEN
        RAISE EXCEPTION 'Full cancellation did not mark the external order cancelled';
    END IF;
    IF (SELECT count(*) FROM inventory_movements
        WHERE transaction_id = cancellation_id AND movement_type = 'order_cancellation') <> 1 THEN
        RAISE EXCEPTION 'Full cancellation inventory reversal was not posted exactly once';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM customer_account_entries
        WHERE transaction_id = cancellation_id AND credit = 200
    ) THEN
        RAISE EXCEPTION 'Cancellation did not reverse the customer obligation';
    END IF;
    rejected := false;
    BEGIN
        UPDATE business_transactions SET status = 'draft', approved_by = NULL, approved_at = NULL
        WHERE id = cancellation_id;
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected OR (SELECT status FROM business_transactions
                        WHERE id = cancellation_id) <> 'approved' THEN
        RAISE EXCEPTION 'Approved transaction was reverted instead of reversed';
    END IF;

    rejected := false;
    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('order_cancellation', 'approved', test_user, test_user, now())
    RETURNING id INTO cancellation_tx;
    BEGIN
        INSERT INTO order_cancellations (
            transaction_id, original_sale_id, reason, shipment_returned
        ) VALUES (cancellation_tx, cancellation_sale_id, 'Duplicate cancellation', true);
    EXCEPTION WHEN unique_violation THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'Duplicate full cancellation was accepted';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('purchase_return', 'approved', test_user, test_user, now())
    RETURNING id INTO purchase_return_tx;
    INSERT INTO purchase_returns (
        transaction_id, supplier_id, original_purchase_id, currency_code
    ) VALUES (purchase_return_tx, supplier_id, purchase_id, 'YER')
    RETURNING transaction_id INTO purchase_return_id;
    rejected := false;
    BEGIN
        INSERT INTO purchase_return_lines (
            transaction_id, original_purchase_id, original_purchase_line_id, quantity, amount
        ) VALUES (purchase_return_id, purchase_id, other_purchase_line_id, 1, 100);
    EXCEPTION WHEN foreign_key_violation THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'Purchase return accepted a line from a different invoice';
    END IF;
    INSERT INTO purchase_return_lines (
        transaction_id, original_purchase_id, original_purchase_line_id, quantity, amount
    ) VALUES (purchase_return_id, purchase_id, honey_purchase_line, 20, 2000);

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('purchase_return', 'approved', test_user, test_user, now())
    RETURNING id INTO purchase_return_tx;
    INSERT INTO purchase_returns (
        transaction_id, supplier_id, original_purchase_id, currency_code
    ) VALUES (purchase_return_tx, supplier_id, purchase_id, 'YER')
    RETURNING transaction_id INTO purchase_return_id;
    INSERT INTO purchase_return_lines (
        transaction_id, original_purchase_id, original_purchase_line_id, quantity, amount
    ) VALUES (purchase_return_id, purchase_id, honey_purchase_line, 30, 3000);

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('purchase_return', 'approved', test_user, test_user, now())
    RETURNING id INTO purchase_return_tx;
    INSERT INTO purchase_returns (
        transaction_id, supplier_id, original_purchase_id, currency_code
    ) VALUES (purchase_return_tx, supplier_id, purchase_id, 'YER')
    RETURNING transaction_id INTO purchase_return_id;
    rejected := false;
    BEGIN
        INSERT INTO purchase_return_lines (
            transaction_id, original_purchase_id, original_purchase_line_id, quantity, amount
        ) VALUES (purchase_return_id, purchase_id, honey_purchase_line, 91, 9100);
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'Cumulative purchase returns exceeding purchased quantity were accepted';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('damaged_goods', 'approved', test_user, test_user, now())
    RETURNING id INTO damage_tx;
    INSERT INTO damaged_goods (transaction_id, product_id, quantity, reason, recorded_by)
    VALUES (damage_tx, honey_id, 3, 'Test damage', test_user);
    IF (SELECT quantity FROM inventory_balances WHERE product_id = honey_id) <> 575.5 THEN
        RAISE EXCEPTION 'Damaged-goods operation did not reduce available stock';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM inventory_movements
        WHERE transaction_id = damage_tx AND movement_type = 'damaged_goods'
          AND quantity_delta = -3
    ) THEN
        RAISE EXCEPTION 'Damaged-goods inventory movement is missing';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sale', 'draft', test_user, NULL, NULL)
    RETURNING id INTO sale_tx;
    INSERT INTO sales_invoices (
        transaction_id, invoice_number, customer_id, sales_channel, currency_code,
        products_subtotal, final_total
    ) VALUES (
        sale_tx, '__negative_stock_test__', customer_id, 'direct', 'YER', 1000000, 1000000
    ) RETURNING transaction_id INTO sale_id;
    INSERT INTO sales_lines (
        transaction_id, product_id, entered_quantity, input_unit,
        conversion_factor, unit_price, discount_amount
    ) VALUES (sale_id, honey_id, 10000, 'KG', 1, 100, 0)
    RETURNING id INTO sale_line_id;
    UPDATE business_transactions
    SET status = 'approved', approved_by = test_user, approved_at = now()
    WHERE id = sale_id;
    rejected := false;
    BEGIN
        INSERT INTO inventory_movements (
            transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
        ) VALUES (sale_id, honey_id, 'sale', -10000, 'KG', sale_line_id);
    EXCEPTION WHEN check_violation THEN
        rejected := true;
    END;
    IF NOT rejected OR (
        SELECT quantity FROM inventory_balances WHERE product_id = honey_id
    ) <> 575.5 THEN
        RAISE EXCEPTION 'Negative inventory was accepted or changed the balance';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sale', 'draft', test_user, NULL, NULL)
    RETURNING id INTO sale_tx;
    INSERT INTO sales_invoices (
        transaction_id, invoice_number, customer_id, sales_channel, currency_code,
        products_subtotal, final_total
    ) VALUES (
        sale_tx, '__direct_sale_return_test__', customer_id, 'direct', 'YER', 100, 100
    ) RETURNING transaction_id INTO direct_sale_id;
    INSERT INTO sales_lines (
        transaction_id, product_id, entered_quantity, input_unit,
        conversion_factor, unit_price, discount_amount
    ) VALUES (direct_sale_id, honey_id, 1, 'KG', 1, 100, 0)
    RETURNING id INTO direct_sale_line_id;
    UPDATE business_transactions
    SET status = 'approved', approved_by = test_user, approved_at = now()
    WHERE id = direct_sale_id;
    INSERT INTO inventory_movements (
        transaction_id, product_id, movement_type, quantity_delta, base_unit, source_id
    ) VALUES (direct_sale_id, honey_id, 'sale', -1, 'KG', direct_sale_line_id);

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sales_return', 'approved', test_user, test_user, now())
    RETURNING id INTO return_tx;
    INSERT INTO sales_returns (transaction_id, customer_id, original_sale_id, currency_code)
    VALUES (return_tx, customer_id, direct_sale_id, 'YER')
    RETURNING transaction_id INTO return_id;
    INSERT INTO sales_return_lines (
        transaction_id, original_sale_id, original_sale_line_id, quantity, amount, reason
    ) VALUES (return_id, direct_sale_id, direct_sale_line_id, 1, 100, 'Direct-sale return');
    IF (SELECT quantity FROM inventory_balances WHERE product_id = honey_id) <> 575.5 THEN
        RAISE EXCEPTION 'A valid direct-sale return was not posted correctly';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sale', 'approved', test_user, test_user, now())
    RETURNING id INTO sale_tx;
    INSERT INTO sales_invoices (
        transaction_id, invoice_number, customer_id, sales_channel, currency_code,
        order_status, products_subtotal, final_total, delivery_cost_bearer, delivery_charge
    ) VALUES
        (sale_tx, '__delivery_shop__', customer_id, 'external', 'YER', 'shipped', 0, 0, 'shop', 0)
    RETURNING transaction_id INTO delivery_shop_sale_id;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sale', 'approved', test_user, test_user, now())
    RETURNING id INTO sale_tx;
    INSERT INTO sales_invoices (
        transaction_id, invoice_number, customer_id, sales_channel, currency_code,
        order_status, products_subtotal, final_total, delivery_cost_bearer, delivery_charge
    ) VALUES
        (sale_tx, '__delivery_customer__', customer_id, 'external', 'YER', 'shipped', 0, 30, 'customer', 30)
    RETURNING transaction_id INTO delivery_customer_sale_id;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('sale', 'approved', test_user, test_user, now())
    RETURNING id INTO sale_tx;
    INSERT INTO sales_invoices (
        transaction_id, invoice_number, customer_id, sales_channel, currency_code,
        order_status, products_subtotal, final_total, delivery_cost_bearer, delivery_charge
    ) VALUES
        (sale_tx, '__delivery_shared__', customer_id, 'external', 'YER', 'shipped', 0, 20, 'shared', 20)
    RETURNING transaction_id INTO delivery_shared_sale_id;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('expense', 'approved', test_user, test_user, now())
    RETURNING id INTO expense_tx;
    INSERT INTO expenses (
        transaction_id, category_id, amount, description, related_sale_id
    ) VALUES (
        expense_tx,
        (SELECT id FROM expenses_categories WHERE lower(name) = 'delivery expenses'),
        80, 'Shop share of delivery', delivery_shop_sale_id
    ) RETURNING transaction_id INTO expense_id;

    rejected := false;
    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('expense', 'approved', test_user, test_user, now())
    RETURNING id INTO expense_tx;
    BEGIN
        INSERT INTO expenses (
            transaction_id, category_id, amount, description, related_sale_id
        ) VALUES (
            expense_tx,
            (SELECT id FROM expenses_categories WHERE lower(name) = 'delivery expenses'),
            1, 'Duplicate shop delivery expense', delivery_shop_sale_id
        );
    EXCEPTION WHEN unique_violation THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'Duplicate delivery expense was accepted';
    END IF;

    rejected := false;
    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('expense', 'approved', test_user, test_user, now())
    RETURNING id INTO expense_tx;
    BEGIN
        INSERT INTO expenses (
            transaction_id, category_id, amount, description, related_sale_id
        ) VALUES (
            expense_tx,
            (SELECT id FROM expenses_categories WHERE lower(name) = 'delivery expenses'),
            30, 'Invalid customer delivery expense', delivery_customer_sale_id
        );
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected THEN
        RAISE EXCEPTION 'Customer-paid delivery incorrectly created a shop expense';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('expense', 'approved', test_user, test_user, now())
    RETURNING id INTO expense_tx;
    INSERT INTO expenses (
        transaction_id, category_id, amount, description, related_sale_id
    ) VALUES (
        expense_tx,
        (SELECT id FROM expenses_categories WHERE lower(name) = 'delivery expenses'),
        40, 'Shop share of shared delivery', delivery_shared_sale_id
    );
    SELECT delivery_charge INTO amount_value
    FROM sales_invoices WHERE transaction_id = delivery_shared_sale_id;
    IF amount_value <> 20 OR (
        SELECT amount FROM expenses WHERE related_sale_id = delivery_shared_sale_id
    ) <> 40 THEN
        RAISE EXCEPTION 'Shared delivery portions were not stored separately';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('expense', 'approved', test_user, test_user, now())
    RETURNING id INTO expense_tx;
    INSERT INTO expenses (transaction_id, category_id, amount, description)
    VALUES (
        expense_tx,
        (SELECT id FROM expenses_categories WHERE lower(name) = 'operating expenses'),
        1000, 'Test partially-paid expense'
    ) RETURNING transaction_id INTO expense_id;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('payment_voucher', 'approved', test_user, test_user, now())
    RETURNING id INTO voucher_tx;
    INSERT INTO vouchers (
        transaction_id, voucher_number, voucher_kind, voucher_type, currency_code,
        amount, financial_account_id, payment_method, expense_id
    ) VALUES (
        voucher_tx, '__expense_payment_1__', 'payment', 'Operating Expenses',
        'YER', 400, main_cash_id, 'cash', expense_id
    );

    SELECT paid_amount, remaining_amount
    INTO amount_value, quantity_value
    FROM expenses WHERE transaction_id = expense_id;
    IF amount_value <> 400 OR quantity_value <> 600 THEN
        RAISE EXCEPTION 'Partial expense payment totals are incorrect';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM account_transactions
        WHERE transaction_id = voucher_tx AND account_id = main_cash_id
          AND direction = 'out' AND amount = 400
    ) THEN
        RAISE EXCEPTION 'Expense voucher did not update the financial account ledger';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('payment_voucher', 'approved', test_user, test_user, now())
    RETURNING id INTO voucher_tx;
    INSERT INTO vouchers (
        transaction_id, voucher_number, voucher_kind, voucher_type, currency_code,
        amount, financial_account_id, payment_method, expense_id
    ) VALUES (
        voucher_tx, '__expense_payment_2__', 'payment', 'Operating Expenses',
        'YER', 600, main_cash_id, 'cash', expense_id
    );
    IF (SELECT remaining_amount FROM expenses WHERE transaction_id = expense_id) <> 0 THEN
        RAISE EXCEPTION 'Fully paid expense has a nonzero remaining amount';
    END IF;

    rejected := false;
    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('payment_voucher', 'approved', test_user, test_user, now())
    RETURNING id INTO voucher_tx;
    BEGIN
        INSERT INTO vouchers (
            transaction_id, voucher_number, voucher_kind, voucher_type, currency_code,
            amount, financial_account_id, payment_method, expense_id
        ) VALUES (
            voucher_tx, '__expense_overpayment__', 'payment', 'Operating Expenses',
            'YER', 1, main_cash_id, 'cash', expense_id
        );
    EXCEPTION WHEN raise_exception THEN
        rejected := true;
    END;
    IF NOT rejected OR (SELECT paid_amount FROM expenses
                        WHERE transaction_id = expense_id) <> 1000 THEN
        RAISE EXCEPTION 'Expense overpayment was accepted or changed paid amount';
    END IF;
    IF EXISTS (
        SELECT 1 FROM vouchers WHERE transaction_id = voucher_tx
    ) OR EXISTS (
        SELECT 1 FROM account_transactions WHERE transaction_id = voucher_tx
    ) THEN
        RAISE EXCEPTION 'Failed expense payment left a partial voucher or account posting';
    END IF;

    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('expense', 'approved', test_user, test_user, now())
    RETURNING id INTO expense_tx;
    INSERT INTO expenses (transaction_id, category_id, amount, description)
    VALUES (
        expense_tx,
        (SELECT id FROM expenses_categories WHERE lower(name) = 'operating expenses'),
        20, 'Test duplicate voucher application'
    ) RETURNING transaction_id INTO expense_id;

    rejected := false;
    INSERT INTO business_transactions (
        transaction_type, status, created_by, approved_by, approved_at
    ) VALUES ('payment_voucher', 'approved', test_user, test_user, now())
    RETURNING id INTO voucher_tx;
    INSERT INTO vouchers (
        transaction_id, voucher_number, voucher_kind, voucher_type, currency_code,
        amount, financial_account_id, payment_method, expense_id
    ) VALUES (
        voucher_tx, '__expense_payment_3__', 'payment', 'Operating Expenses',
        'YER', 10, main_cash_id, 'cash', expense_id
    );
    BEGIN
        INSERT INTO vouchers (
            transaction_id, voucher_number, voucher_kind, voucher_type, currency_code,
            amount, financial_account_id, payment_method, expense_id
        ) VALUES (
            voucher_tx, '__expense_payment_3_duplicate__', 'payment', 'Operating Expenses',
            'YER', 10, main_cash_id, 'cash', expense_id
        );
    EXCEPTION WHEN unique_violation THEN
        rejected := true;
    END;
    IF NOT rejected OR (SELECT paid_amount FROM expenses
                        WHERE transaction_id = expense_id) <> 10 THEN
        RAISE EXCEPTION 'Duplicate voucher application was accepted';
    END IF;
END;
$test$;

ROLLBACK;
