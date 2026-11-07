BEGIN;

CREATE TABLE schema_migrations (
    version text PRIMARY KEY,
    applied_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE currencies (
    code varchar(3) PRIMARY KEY,
    name text NOT NULL
);

INSERT INTO currencies (code, name) VALUES
    ('YER', 'Yemeni Rial'),
    ('SAR', 'Saudi Riyal'),
    ('USD', 'US Dollar')
ON CONFLICT (code) DO NOTHING;

CREATE TABLE app_users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    username text NOT NULL UNIQUE,
    display_name text NOT NULL,
    password_hash text NOT NULL,
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE roles (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL UNIQUE,
    description text
);

CREATE TABLE permissions (
    code text PRIMARY KEY,
    description text NOT NULL
);

CREATE TABLE user_roles (
    user_id uuid NOT NULL REFERENCES app_users(id),
    role_id uuid NOT NULL REFERENCES roles(id),
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE role_permissions (
    role_id uuid NOT NULL REFERENCES roles(id),
    permission_code text NOT NULL REFERENCES permissions(code),
    PRIMARY KEY (role_id, permission_code)
);

INSERT INTO roles (name, description) VALUES
    ('Administrator', 'Full system access'),
    ('Sales User', 'Sales, external orders, and customer collections'),
    ('Inventory and Purchasing User', 'Products, suppliers, purchases, and inventory')
ON CONFLICT (name) DO NOTHING;

CREATE TABLE business_transactions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_type text NOT NULL,
    status text NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft', 'approved', 'cancelled')),
    occurred_at timestamptz NOT NULL DEFAULT now(),
    created_by uuid NOT NULL REFERENCES app_users(id),
    approved_by uuid REFERENCES app_users(id),
    approved_at timestamptz,
    cancelled_by uuid REFERENCES app_users(id),
    cancelled_at timestamptz,
    cancellation_reason text,
    notes text,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK ((status = 'approved') = (approved_at IS NOT NULL AND approved_by IS NOT NULL)),
    CHECK ((status = 'cancelled') = (cancelled_at IS NOT NULL AND cancelled_by IS NOT NULL)),
    CHECK (status <> 'cancelled' OR cancellation_reason IS NOT NULL)
);

CREATE TABLE product_categories (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL UNIQUE,
    is_active boolean NOT NULL DEFAULT true
);

INSERT INTO product_categories (name) VALUES ('Honey'), ('Ghee'), ('Nuts')
ON CONFLICT (name) DO NOTHING;

CREATE TABLE products (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    sku text UNIQUE,
    name text NOT NULL,
    category_id uuid NOT NULL REFERENCES product_categories(id),
    base_unit text NOT NULL CHECK (base_unit IN ('kg', 'can', 'unit')),
    minimum_stock numeric(18, 3) CHECK (minimum_stock IS NULL OR minimum_stock >= 0),
    is_active boolean NOT NULL DEFAULT true,
    notes text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE product_prices (
    product_id uuid NOT NULL REFERENCES products(id),
    currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    unit_price numeric(19, 4) NOT NULL CHECK (unit_price >= 0),
    max_discount_rate numeric(7, 6) NOT NULL DEFAULT 0
        CHECK (max_discount_rate BETWEEN 0 AND 1),
    updated_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (product_id, currency_code)
);

CREATE TABLE supplier_types (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL UNIQUE,
    is_active boolean NOT NULL DEFAULT true
);

INSERT INTO supplier_types (name) VALUES
    ('Approved Beekeeper'),
    ('Honey Trader'),
    ('Other Supplier')
ON CONFLICT (name) DO NOTHING;

CREATE TABLE suppliers (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    supplier_type_id uuid NOT NULL REFERENCES supplier_types(id),
    name text NOT NULL,
    phone text,
    address text,
    is_active boolean NOT NULL DEFAULT true,
    notes text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE customers (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL,
    phone text,
    country text,
    city text,
    address text,
    is_active boolean NOT NULL DEFAULT true,
    notes text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE representatives (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL,
    phone text,
    city text,
    country text NOT NULL DEFAULT 'Saudi Arabia',
    is_active boolean NOT NULL DEFAULT true,
    notes text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE delivery_providers (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL,
    phone text,
    is_active boolean NOT NULL DEFAULT true
);

CREATE TABLE financial_accounts (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL,
    account_type text NOT NULL
        CHECK (account_type IN ('cash', 'bank', 'representative_custody')),
    currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    representative_id uuid REFERENCES representatives(id),
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (id, currency_code),
    CHECK (
        (account_type = 'representative_custody' AND representative_id IS NOT NULL)
        OR (account_type <> 'representative_custody' AND representative_id IS NULL)
    )
);

CREATE TABLE exchange_rates (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    from_currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    to_currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    rate numeric(19, 8) NOT NULL CHECK (rate > 0),
    effective_at timestamptz NOT NULL,
    created_by uuid NOT NULL REFERENCES app_users(id),
    CHECK (from_currency_code <> to_currency_code),
    UNIQUE (from_currency_code, to_currency_code, effective_at)
);

CREATE TABLE purchase_invoices (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    invoice_number text NOT NULL UNIQUE,
    supplier_id uuid NOT NULL REFERENCES suppliers(id),
    currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    invoice_total numeric(19, 4) NOT NULL CHECK (invoice_total >= 0)
);

CREATE TABLE purchase_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES purchase_invoices(transaction_id),
    product_id uuid NOT NULL REFERENCES products(id),
    purchasing_unit text NOT NULL,
    conversion_factor numeric(18, 6) NOT NULL DEFAULT 1 CHECK (conversion_factor > 0),
    purchased_quantity numeric(18, 3) NOT NULL CHECK (purchased_quantity > 0),
    purchase_unit_price numeric(19, 4) NOT NULL CHECK (purchase_unit_price >= 0),
    line_total numeric(19, 4) GENERATED ALWAYS AS
        (purchased_quantity * purchase_unit_price) STORED,
    base_quantity numeric(18, 3) GENERATED ALWAYS AS
        (purchased_quantity * conversion_factor) STORED,
    effective_base_unit_cost numeric(19, 4) GENERATED ALWAYS AS
        (purchase_unit_price / conversion_factor) STORED
);

CREATE TABLE sales_invoices (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    invoice_number text NOT NULL UNIQUE,
    customer_id uuid NOT NULL REFERENCES customers(id),
    sales_channel text NOT NULL CHECK (sales_channel IN ('direct', 'external')),
    currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    representative_id uuid REFERENCES representatives(id),
    delivery_provider_id uuid REFERENCES delivery_providers(id),
    order_status text CHECK (order_status IN ('shipped', 'delivered', 'cancelled')),
    products_subtotal numeric(19, 4) NOT NULL DEFAULT 0 CHECK (products_subtotal >= 0),
    discounts_total numeric(19, 4) NOT NULL DEFAULT 0 CHECK (discounts_total >= 0),
    delivery_charge numeric(19, 4) NOT NULL DEFAULT 0 CHECK (delivery_charge >= 0),
    final_total numeric(19, 4) NOT NULL CHECK (final_total >= 0),
    actual_delivery_cost numeric(19, 4) CHECK (actual_delivery_cost IS NULL OR actual_delivery_cost >= 0),
    delivery_cost_bearer text
        CHECK (delivery_cost_bearer IN ('shop', 'customer', 'shared')),
    CHECK (
        (sales_channel = 'external' AND order_status IS NOT NULL)
        OR (sales_channel = 'direct' AND order_status IS NULL)
    )
);

CREATE TABLE sales_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES sales_invoices(transaction_id),
    product_id uuid NOT NULL REFERENCES products(id),
    quantity numeric(18, 3) NOT NULL CHECK (quantity > 0),
    unit_price numeric(19, 4) NOT NULL CHECK (unit_price >= 0),
    discount_amount numeric(19, 4) NOT NULL DEFAULT 0 CHECK (discount_amount >= 0),
    line_total numeric(19, 4) GENERATED ALWAYS AS
        (quantity * unit_price - discount_amount) STORED,
    CHECK (discount_amount <= quantity * unit_price)
);

CREATE TABLE purchase_returns (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    supplier_id uuid NOT NULL REFERENCES suppliers(id),
    original_purchase_id uuid NOT NULL REFERENCES purchase_invoices(transaction_id),
    currency_code varchar(3) NOT NULL REFERENCES currencies(code)
);

CREATE TABLE purchase_return_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES purchase_returns(transaction_id),
    product_id uuid NOT NULL REFERENCES products(id),
    quantity numeric(18, 3) NOT NULL CHECK (quantity > 0),
    amount numeric(19, 4) NOT NULL CHECK (amount >= 0)
);

CREATE TABLE sales_returns (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    customer_id uuid NOT NULL REFERENCES customers(id),
    original_sale_id uuid NOT NULL REFERENCES sales_invoices(transaction_id),
    currency_code varchar(3) NOT NULL REFERENCES currencies(code)
);

CREATE TABLE sales_return_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES sales_returns(transaction_id),
    original_sale_line_id uuid NOT NULL REFERENCES sales_lines(id),
    quantity numeric(18, 3) NOT NULL CHECK (quantity > 0),
    product_condition text NOT NULL
        CHECK (product_condition IN ('resalable', 'damaged', 'lost', 'unsaleable')),
    amount numeric(19, 4) NOT NULL CHECK (amount >= 0),
    reason text NOT NULL
);

CREATE TABLE order_cancellations (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    original_sale_id uuid NOT NULL UNIQUE REFERENCES sales_invoices(transaction_id),
    reason text NOT NULL
);

CREATE TABLE order_cancellation_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES order_cancellations(transaction_id),
    original_sale_line_id uuid NOT NULL REFERENCES sales_lines(id),
    quantity_returned numeric(18, 3) NOT NULL DEFAULT 0 CHECK (quantity_returned >= 0),
    product_condition text NOT NULL
        CHECK (product_condition IN ('resalable', 'damaged', 'lost', 'unsaleable'))
);

CREATE TABLE stock_adjustments (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    reason text NOT NULL
);

CREATE TABLE stock_adjustment_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES stock_adjustments(transaction_id),
    product_id uuid NOT NULL REFERENCES products(id),
    previous_quantity numeric(18, 3) NOT NULL,
    actual_quantity numeric(18, 3) NOT NULL CHECK (actual_quantity >= 0),
    difference numeric(18, 3) GENERATED ALWAYS AS
        (actual_quantity - previous_quantity) STORED,
    CHECK (previous_quantity >= 0)
);

CREATE TABLE opening_inventory (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    effective_at timestamptz NOT NULL
);

CREATE TABLE opening_inventory_lines (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES opening_inventory(transaction_id),
    product_id uuid NOT NULL REFERENCES products(id),
    quantity numeric(18, 3) NOT NULL CHECK (quantity >= 0),
    UNIQUE (transaction_id, product_id)
);

CREATE TABLE inventory_movements (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES business_transactions(id),
    product_id uuid NOT NULL REFERENCES products(id),
    movement_type text NOT NULL CHECK (
        movement_type IN (
            'opening_balance', 'purchase', 'sale', 'sales_return',
            'purchase_return', 'stock_adjustment', 'order_cancellation'
        )
    ),
    quantity_delta numeric(18, 3) NOT NULL CHECK (quantity_delta <> 0),
    base_unit text NOT NULL CHECK (base_unit IN ('kg', 'can', 'unit')),
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE inventory_balances (
    product_id uuid PRIMARY KEY REFERENCES products(id),
    quantity numeric(18, 3) NOT NULL DEFAULT 0 CHECK (quantity >= 0),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE vouchers (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    voucher_number text NOT NULL UNIQUE,
    voucher_kind text NOT NULL CHECK (voucher_kind IN ('receipt', 'payment')),
    voucher_type text NOT NULL,
    currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    amount numeric(19, 4) NOT NULL CHECK (amount > 0),
    financial_account_id uuid NOT NULL,
    related_customer_id uuid REFERENCES customers(id),
    related_supplier_id uuid REFERENCES suppliers(id),
    related_representative_id uuid REFERENCES representatives(id),
    payment_method text NOT NULL,
    description text,
    FOREIGN KEY (financial_account_id, currency_code)
        REFERENCES financial_accounts(id, currency_code),
    CHECK (
        (related_customer_id IS NOT NULL)::integer +
        (related_supplier_id IS NOT NULL)::integer +
        (related_representative_id IS NOT NULL)::integer <= 1
    )
);

CREATE TABLE account_transfers (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    source_account_id uuid NOT NULL,
    destination_account_id uuid NOT NULL,
    currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    amount numeric(19, 4) NOT NULL CHECK (amount > 0),
    CHECK (source_account_id <> destination_account_id),
    FOREIGN KEY (source_account_id, currency_code)
        REFERENCES financial_accounts(id, currency_code),
    FOREIGN KEY (destination_account_id, currency_code)
        REFERENCES financial_accounts(id, currency_code)
);

CREATE TABLE account_transactions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES business_transactions(id),
    account_id uuid NOT NULL REFERENCES financial_accounts(id),
    direction text NOT NULL CHECK (direction IN ('in', 'out')),
    amount numeric(19, 4) NOT NULL CHECK (amount > 0),
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE customer_account_entries (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES business_transactions(id),
    customer_id uuid NOT NULL REFERENCES customers(id),
    currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    debit numeric(19, 4) NOT NULL DEFAULT 0 CHECK (debit >= 0),
    credit numeric(19, 4) NOT NULL DEFAULT 0 CHECK (credit >= 0),
    description text,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK ((debit > 0) <> (credit > 0))
);

CREATE TABLE supplier_account_entries (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id uuid NOT NULL REFERENCES business_transactions(id),
    supplier_id uuid NOT NULL REFERENCES suppliers(id),
    currency_code varchar(3) NOT NULL REFERENCES currencies(code),
    debit numeric(19, 4) NOT NULL DEFAULT 0 CHECK (debit >= 0),
    credit numeric(19, 4) NOT NULL DEFAULT 0 CHECK (credit >= 0),
    description text,
    created_at timestamptz NOT NULL DEFAULT now(),
    CHECK ((debit > 0) <> (credit > 0))
);

CREATE TABLE expenses_categories (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL UNIQUE,
    is_active boolean NOT NULL DEFAULT true
);

INSERT INTO expenses_categories (name) VALUES
    ('Packaging Materials'),
    ('Delivery Expenses'),
    ('Operating Expenses'),
    ('Other Expenses')
ON CONFLICT (name) DO NOTHING;

CREATE TABLE expenses (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    category_id uuid NOT NULL REFERENCES expenses_categories(id),
    supplier_id uuid REFERENCES suppliers(id),
    financial_account_id uuid NOT NULL REFERENCES financial_accounts(id),
    amount numeric(19, 4) NOT NULL CHECK (amount > 0),
    description text NOT NULL
);

CREATE TABLE owner_withdrawals (
    transaction_id uuid PRIMARY KEY REFERENCES business_transactions(id),
    person_name text NOT NULL,
    financial_account_id uuid NOT NULL REFERENCES financial_accounts(id),
    amount numeric(19, 4) NOT NULL CHECK (amount > 0),
    description text
);

CREATE TABLE audit_log (
    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    actor_user_id uuid REFERENCES app_users(id),
    action text NOT NULL,
    entity_type text NOT NULL,
    entity_id uuid,
    occurred_at timestamptz NOT NULL DEFAULT now(),
    details jsonb NOT NULL DEFAULT '{}'::jsonb
);

CREATE INDEX business_transactions_type_date_idx
    ON business_transactions (transaction_type, occurred_at DESC);
CREATE INDEX products_category_active_idx
    ON products (category_id, is_active);
CREATE INDEX suppliers_type_active_idx
    ON suppliers (supplier_type_id, is_active);
CREATE INDEX customers_name_idx ON customers (name);
CREATE INDEX suppliers_name_idx ON suppliers (name);
CREATE INDEX purchase_invoices_supplier_date_idx
    ON purchase_invoices (supplier_id, transaction_id);
CREATE INDEX purchase_lines_product_idx ON purchase_lines (product_id);
CREATE INDEX sales_invoices_customer_idx ON sales_invoices (customer_id, transaction_id);
CREATE INDEX sales_invoices_channel_status_idx
    ON sales_invoices (sales_channel, order_status);
CREATE INDEX sales_lines_product_idx ON sales_lines (product_id);
CREATE INDEX inventory_movements_product_date_idx
    ON inventory_movements (product_id, created_at DESC);
CREATE INDEX inventory_movements_transaction_idx
    ON inventory_movements (transaction_id);
CREATE INDEX account_transactions_account_date_idx
    ON account_transactions (account_id, created_at DESC);
CREATE INDEX customer_entries_statement_idx
    ON customer_account_entries (customer_id, currency_code, created_at);
CREATE INDEX supplier_entries_statement_idx
    ON supplier_account_entries (supplier_id, currency_code, created_at);
CREATE INDEX vouchers_date_kind_idx ON vouchers (voucher_kind, transaction_id);
CREATE INDEX audit_log_entity_date_idx
    ON audit_log (entity_type, entity_id, occurred_at DESC);

INSERT INTO schema_migrations (version) VALUES ('001_initial_schema');

COMMIT;
