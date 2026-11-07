# Honey Shop Management System

## Software Requirements Analysis & Development Plan

**Document Version:** 0.3  
**Project Type:** Web-Based Business Management System  
**Development Priority:** Deliver a reliable and practical first release with a simple, maintainable, and extensible architecture.

> **Current decisions:** This is the original requirements baseline. Later approved first-release decisions are recorded in [docs/DECISIONS.md](docs/DECISIONS.md) and take precedence where this document conflicts with them. In particular, the first release uses YER only, full-order cancellations require complete physical return, and accepted sales returns are saleable.

---

# 1. Project Overview

The goal is to develop a web-based management system for a retail business specializing in selling different types of honey, along with a limited selection of products such as ghee, almonds, and pistachios.

The business purchases products from several suppliers, including a group of trusted beekeepers who receive advance payments throughout the year and supply honey to the shop later.

The shop sells products directly to customers and also receives external orders through WhatsApp and direct communication.

For customers in Saudi Arabia, the business relies on representatives who coordinate with customers and may collect payments on behalf of the shop.

The first release uses Yemeni Rial (YER) only. Multi-currency transactions and exchange-rate conversion are deferred.

**Primary Objective:** Build a simple, reliable management system that organizes inventory, purchasing, sales, external orders, customer and supplier balances, cash movements, expenses, and business reporting.

The system should prioritize operational accuracy, ease of use, high responsiveness, and mobile accessibility.

# 2. Main System Modules

The system should include the following modules:

1. Dashboard.
    
2. Products and Categories.
    
3. Inventory Management.
    
4. Suppliers and Beekeepers.
    
5. Customers.
    
6. Purchases.
    
7. Sales and External Orders.
    
8. Sales Returns.
    
9. Representatives.
    
10. Delivery Providers.
    
11. Cash and Financial Accounts.
    
12. Receipt and Payment Vouchers.
    
13. Expenses.
    
14. Owner Withdrawals.
    
15. Reports.
    
16. Users and Permissions.
    
17. System Settings and Backups.
    

# 3. Products and Categories

The system must allow administrators to create, update, activate, and deactivate products without requiring changes to the application.

Initial product categories:

- Honey.
    
- Ghee.
    
- Nuts.
    

Categories should be configurable.

## 3.1 Product Fields

Each product should contain:

- Product ID.
    
- Product Name.
    
- Category.
    
- Base Inventory Unit.
    
- Selling Price per Base Unit.
    
- Maximum Allowed Discount.
    
- Minimum Stock Level (optional).
    
- Active/Inactive Status.
    
- Notes (optional).
    

## 3.2 Product Units and Measurement Rules

The system must use one base inventory unit for each product.

Initial rules:

|Product Category|Base Inventory Unit|Selling Price|
|---|---|---|
|Honey|Kilogram (kg)|Price per kg|
|Ghee|Can|Price per can|
|Nuts|Kilogram (kg)|Price per kg|

Honey-specific rules:

- The inventory unit is always kilograms.
    
- One honey gallon equals exactly 7 kilograms.
    
- Purchasing 20 gallons must add 140 kilograms to inventory.
    
- Selling prices are defined per kilogram.
    
- Fractional quantities must be supported, such as 0.5 kg or 1.25 kg.
    

The purchasing process must allow selecting a purchasing unit and applying its conversion factor.

The conversion factor used in each purchase must be stored with the purchase line so historical transactions remain unchanged if default conversion settings are modified later.

Ghee and nuts should normally be purchased using their respective base units.

Avoid building an unnecessarily complex general-purpose unit conversion system.

## 3.3 Selling Prices and Discounts

The product's default selling price must be stored in its base inventory unit.

Each sale line must preserve:

- Product.
    
- Entered quantity and unit.
    
- Conversion factor and resulting base-unit quantity.
    
- Unit selling price at the time of sale.
    
- Discount amount.
    
- Line total.
    

Changing the current product price must never modify historical invoices.

The system must prevent discounts from exceeding the configured maximum unless the user has explicit authorization to override the restriction.

# 4. Packaging Materials and Consumables

The shop does not sell packaging materials and does not want to track their inventory quantities.

Therefore, packaging materials must not be treated as inventory products.

Examples:

- Honey containers.
    
- Lids.
    
- Packaging boxes.
    
- Filling supplies.
    
- Other packaging consumables.
    

Their purchase costs must be registered as operating expenses under the appropriate expense category.

No stock movement or quantity tracking is required for these items.

# 5. Suppliers and Beekeepers

Beekeepers and other suppliers must be managed through one unified entity.

A beekeeper is simply a supplier with a specific supplier type.

Initial supplier types:

- Approved Beekeeper.
    
- Honey Trader.
    
- Other Supplier.
    

The structure should allow additional supplier types later.

## 5.1 Supplier Information

Each supplier should have:

- Supplier ID.
    
- Name.
    
- Phone Number.
    
- Region or Address.
    
- Supplier Type.
    
- Status.
    
- Notes.
    

The system must allow filtering and displaying approved beekeepers separately without maintaining a duplicate beekeeper entity.

## 5.2 Supplier Accounts

Each supplier must have a financial transaction history.

Supported transaction types:

- Purchase Invoice.
    
- Payment to Supplier.
    
- Advance Payment.
    
- Purchase Return.
    
- Opening Balance.
    
- Authorized Financial Adjustment.
    

The supplier balance must be calculated from its recorded transactions rather than manually maintained.

The system must distinguish between:

- Amount payable to the supplier.
    
- Advance balance held by the supplier on behalf of the shop.
    

Example:

1. The shop gives a beekeeper an advance of 100,000 YER.
    
2. The beekeeper later supplies honey worth 80,000 YER.
    
3. The remaining advance balance is 20,000 YER owed by the beekeeper to the shop.
    

A supplier statement must display transaction dates, descriptions, amounts, currencies, and resulting balances.

# 6. Customers and Customer Accounts

## 6.1 Customer Information

Each customer should have:

- Customer ID.
    
- Name.
    
- Phone Number.
    
- Country.
    
- City.
    
- Address.
    
- Status.
    
- Notes.
    

The customer module must support creating, editing, searching, and deactivating customer records.

## 6.2 Customer Financial Accounts

Customer balances must be calculated from financial transactions rather than manually updated fields.

Supported transaction types:

- Credit Sales.
    
- Customer Payments.
    
- Sales Returns.
    
- Customer Credit Balances.
    
- Opening Balances.
    
- Authorized Financial Adjustments.
    

Example:

|Transaction|Amount|Outstanding Balance|
|---|---|---|
|Sales Invoice|100,000|100,000|
|Receipt Voucher|60,000|40,000|
|Sales Return|10,000|30,000|

A customer credit balance represents money owed by the shop to the customer, for example, an advance payment or a refund amount retained as customer credit.

The system must provide:

- Customer Statement.
    
- Outstanding Balance.
    
- Total Payments.
    
- Open Invoices.
    
- Customer Credit Balance.
    

Financial adjustments must be restricted to authorized users and require a documented reason.

# 7. Inventory Management

Inventory management is a critical part of the system.

The application must use a permanent inventory movement history (Inventory Movement Ledger).

Stock quantities must not be modified arbitrarily. Every approved inventory operation must generate a corresponding inventory movement.

## 7.1 Inventory Movement Types

The initial movement types are:

1. Opening Balance.
    
2. Purchase.
    
3. Sale.
    
4. Sales Return.
    
5. Positive or Negative Stock Adjustment.
    

Packaging consumption must not generate inventory movements.

## 7.2 Inventory Movement Fields

Each movement must contain:

- Movement ID.
    
- Product ID.
    
- Transaction Date.
    
- Movement Type.
    
- Quantity.
    
- Base Inventory Unit.
    
- Quantity Added or Removed.
    
- Related Transaction Reference.
    
- User ID.
    
- Notes.
    

Incoming quantities increase stock, while outgoing quantities decrease stock.

Each movement must have a reference to the business transaction that generated it.

## 7.3 Current Inventory Balances

To ensure fast inventory queries, maintain a separate current-balance record for each product.

Required fields:

- Product ID.
    
- Current Quantity.
    

Whenever an approved purchase, sale, return, or adjustment occurs:

1. Validate the transaction.
    
2. Create the inventory movement.
    
3. Update the corresponding current inventory balance.
    
4. Complete all operations within one atomic database transaction.
    

The movement ledger remains the historical source of truth, while the current-balance records are used for fast inventory display.

The system should provide:

- Current inventory list.
    
- Product movement history.
    
- Inventory balance by product.
    
- Low-stock alerts based on configurable minimum quantities.
    

## 7.4 Stock Adjustments

A stock adjustment corrects differences between actual physical stock and the system's recorded balance.

Example:

- Recorded quantity: 50 kg.
    
- Actual quantity: 48.5 kg.
    
- Adjustment: -1.5 kg.
    

Adjustment records must include:

- Product.
    
- Previous quantity.
    
- Actual quantity.
    
- Difference.
    
- Reason.
    
- User.
    
- Date.
    

Only administrators or specifically authorized inventory users may approve stock adjustments.

Never modify the current balance directly without generating an adjustment movement.

## 7.5 Opening Inventory Balances

The system must provide a dedicated process for entering existing physical stock when the system is first deployed.

Opening quantities must be recorded as opening inventory movements.

# 8. Purchasing Management

The system must support purchasing products from beekeepers and other suppliers, including cash, credit, and partial-payment purchases.

## 8.1 Purchase Invoice

Fields:

- Purchase Invoice Number.
    
- Date.
    
- Supplier.
    
- Currency.
    
- Invoice Total.
    
- Paid Amount.
    
- Remaining Amount.
    
- Notes.
    
- Created By.
    

## 8.2 Purchase Invoice Details

Each purchase line must include:

- Product.
    
- Purchasing Unit.
    
- Conversion Factor, when applicable.
    
- Purchased Quantity.
    
- Purchase Unit Price.
    
- Line Total.
    
- Equivalent Quantity in Base Inventory Units.
    
- Effective Purchase Cost per Base Unit.
    

**Important cost rule:**

The entered purchase price represents the complete acquisition cost of the product, including all related purchasing and transportation expenses required to bring it to the shop.

Do not introduce additional purchasing cost allocation fields or add these costs again.

For example:

- Total acquisition cost: 500,000 YER.
    
- Purchased quantity: 100 kg.
    
- Effective acquisition cost: 5,000 YER per kg.
    

This cost must be retained for historical reporting.

## 8.3 Purchase Processing

When a purchase invoice is approved:

1. Validate all input data.
    
2. Convert purchased quantities into base inventory units.
    
3. Generate incoming inventory movements.
    
4. Update current stock balances.
    
5. Update the supplier account.
    
6. Record any payment.
    
7. Generate the corresponding payment voucher.
    
8. Update the appropriate financial account.
    
9. Calculate the remaining payable balance.
    

All related operations must succeed or fail together.

# 9. Sales Management

The system must support two sales channels:

1. Direct Shop Sales.
    
2. External Orders.
    

Both should use a unified sales structure with a field identifying the sales channel.

## 9.1 Sales Invoice Fields

- Invoice Number.
    
- Transaction Date.
    
- Customer.
    
- Sales Channel.
    
- Currency.
    
- Products Subtotal.
    
- Discounts.
    
- Delivery Charge Applied to Customer.
    
- Final Total.
    
- Paid Amount.
    
- Outstanding Amount.
    
- Created By.
    
- Notes.
    

## 9.2 Sales Invoice Details

Each line must contain:

- Product.
    
- Quantity in Base Inventory Units.
    
- Unit Selling Price.
    
- Discount.
    
- Line Total.
    

The system must support fractional quantities for products sold by weight.

## 9.3 Direct Sales

When a direct sale is approved:

1. Save the sales invoice and its details.
    
2. Generate outgoing inventory movements.
    
3. Update current inventory balances.
    
4. Record the customer's financial obligation.
    
5. Generate a receipt voucher for the amount received, when applicable.
    
6. Update the financial account receiving the payment.
    

The system must support:

- Full cash payment.
    
- Full credit sale.
    
- Partial payment.
    
- Subsequent collections.
    

## 9.4 External Orders

External orders are agreed upon outside the system, usually through WhatsApp or direct communication.

Unconfirmed inquiries and informal requests must not be registered in the system.

An order is entered only when the products physically leave the shop and are handed over for delivery.

When registered:

- Create the external sales record.
    
- Register the customer.
    
- Add the products and quantities.
    
- Record agreed selling prices.
    
- Assign the representative, when applicable.
    
- Assign the delivery provider.
    
- Record delivery charges.
    
- Deduct the products from inventory.
    
- Set the initial order status to **Shipped**.
    

### External Order Statuses

Only the following statuses are required:

- Shipped.
    
- Delivered.
    
- Cancelled.
    

Changing the status from Shipped to Delivered must not create another sale or deduct inventory again.

### Payment Status

Payment status must be independent of delivery status:

- Unpaid.
    
- Partially Paid.
    
- Fully Paid.
    

Customers may pay through:

- Direct bank deposit to the shop.
    
- Payment to the representative upon delivery.
    
- Other authorized payment methods.
    

All collections must be linked to the relevant customer and order when applicable.

## 9.5 Order Cancellation

When a shipped order is cancelled:

1. Create a cancellation record linked to the original order.
    
2. Confirm that the complete shipment has physically returned to the shop.
    
3. Reverse the original order's full inventory effect exactly once.
    
4. Update the customer's financial account.
    
5. Process a refund or customer credit as appropriate.
    
Full cancellation only; partial returns use the Sales Returns workflow. Changing an order status alone must never restore inventory.

# 10. Sales Returns

The shop accepts returns from customers.

The system must support partial and full sales returns linked to original invoices.

Return fields:

- Return ID.
    
- Original Sales Invoice.
    
- Return Date.
    
- Product.
    
- Returned Quantity.
    
- Return Reason.
    
- Created By.
    
- Notes.
    

When a return is approved:

1. Generate an incoming inventory movement for the accepted returned quantity.
    
2. Reduce the customer's outstanding balance or create a customer credit balance.
    
3. Generate a payment voucher if a refund is issued.
    
4. Preserve the original invoice and return history.
    

Every return line must reference the exact original sales line. The cumulative returned quantity must not exceed the quantity sold. All accepted returned products are saleable and are added back to available inventory.

# 11. Representatives

Representatives currently operate in Saudi Arabian cities.

The default country for representatives must be Saudi Arabia, but the structure must allow other countries in the future.

## 11.1 Representative Information

- Representative ID.
    
- Name.
    
- Phone Number.
    
- City.
    
- Country.
    
- Status.
    
- Notes.
    

External orders may be assigned to a representative.

## 11.2 Representative Financial Accounts

The system must track money collected by each representative and money transferred to the shop.

When a representative collects payment from a customer:

1. Record the customer payment.
    
2. Reduce the customer's outstanding balance.
    
3. Increase the representative's held funds.
    
4. Do not increase the shop's physical cash balance yet.
    

When the representative transfers the money to the shop:

1. Reduce the representative's held funds.
    
2. Increase the receiving cash or bank account.
    
3. Record the financial transfer.
    

Each representative must have a detailed account statement.

## 11.3 Representative Commissions

Commission rules have not yet been determined.

Do not implement an automatic commission percentage or assume a fixed commission.

The initial version may support recording a manually agreed commission amount when needed.

Automatic commission calculations should be deferred until the business rules are confirmed.

# 12. Delivery Providers

Delivery providers are individuals or organizations responsible for transporting orders.

The shop pays them for delivery services.

A full delivery-provider accounting module is unnecessary.

Maintain a simple directory containing:

- Delivery Provider ID.
    
- Name.
    
- Phone Number (optional).
    
- Status.
    

Users should select a delivery provider from a dropdown list when recording a sale or external order.

## 12.1 Delivery Costs

The sales invoice records who bears the delivery cost and the amount charged to the customer. Any part paid by the shop is recorded as a delivery expense linked to the sale. For shared costs, record the customer portion on the invoice and the shop portion as the expense. Do not duplicate the actual delivery cost across unrelated records or create a separate delivery-accounting subsystem.

# 13. Receipt and Payment Vouchers

The system must provide a dedicated financial voucher module.

Vouchers are used to document incoming and outgoing financial transactions.

## 13.1 Receipt Voucher

A receipt voucher documents money received by the shop or its authorized representatives.

Initial receipt types:

- Customer Collection.
    
- Money Received from Representative.
    
- Money Received from Supplier.
    
- Other Income.
    
- Authorized Financial Adjustment.
    

Fields:

- Voucher Number.
    
- Date.
    
- Receipt Type.
    
- Related Party, when applicable.
    
- Amount.
    
- Currency.
    
- Receiving Financial Account.
    
- Payment Method.
    
- Related Transaction Reference.
    
- Description.
    
- Created By.
    

## 13.2 Payment Voucher

A payment voucher documents money paid by the shop.

Initial payment types:

- Supplier Payment.
    
- Purchase Payment.
    
- Delivery Provider Payment.
    
- Operating Expenses.
    
- Packaging Material Expenses.
    
- Owner Withdrawals.
    
- Customer Refunds.
    
- Representative Commissions (future).
    

Fields:

- Voucher Number.
    
- Date.
    
- Payment Type.
    
- Related Party, when applicable.
    
- Amount.
    
- Currency.
    
- Paying Financial Account.
    
- Payment Method.
    
- Related Transaction Reference.
    
- Description.
    
- Created By.
    

## 13.3 Automatic Voucher Generation

The system must prevent duplicate financial entries.

When a user records a collection directly inside a sales invoice, the system should automatically generate a linked receipt voucher.

When a user records a supplier payment inside a purchase invoice, the system should automatically generate a linked payment voucher.

Users must not be required to create another manual voucher for the same payment.

Independent financial transactions may be recorded directly through the voucher module.

## 13.4 Transfers Between Financial Accounts

The system must support transfers between:

- Shop cash accounts.
    
- Bank accounts.
    
- Representative-held funds.
    

Transfers must not be treated as new income or expenses.

Both sides of the transfer must reference the same transfer transaction.

# 14. Cash and Financial Accounts

The system must provide financial account management for the shop's cash holdings, bank accounts, and representative-held funds. The first release has one primary shop cash account; representative custody accounts remain separate.

Account fields:

- Account ID.
    
- Account Name.
    
- Account Type.
    
- Currency.
    
- Status.
    

Account types:

- Cash Account.
    
- Bank Account.
    
- Representative Custody Account.
    

All first-release financial accounts operate in YER.

The system must calculate balances from recorded financial transactions.

Required information:

- Current Balance.
    
- Incoming Transactions.
    
- Outgoing Transactions.
    
- Transaction History.
    
- Date Filters.
    

Money held by representatives must remain separate from money physically available in the shop.

# 15. Currency

The first release supports Yemeni Rial (YER) only. Product prices, purchases, sales, customer and supplier balances, financial accounts, vouchers, expenses, and reports are denominated in YER. No currency selection, exchange rates, or currency-conversion workflow is required. Multi-currency support is deferred.

# 16. Expenses and Owner Withdrawals

## 16.1 Expense Management

The system must provide simple expense management.

Initial expense categories:

- Packaging Materials.
    
- Delivery Expenses.
    
- Operating Expenses.
    
- Other Expenses.
    

Administrators must be able to add and manage expense categories.

Expense fields:

- Expense ID.
    
- Date.
    
- Expense Category.
    
- Amount.
    
- Currency.
    
- Supplier, when applicable.
    
- Payment Method.
    
- Financial Account.
    
- Description.
    
- Created By.
    

Packaging material purchases must be recorded as expenses and must not generate inventory movements.

The application must prevent recording the same expense twice when the corresponding payment is also registered.

The initial version may prioritize expenses paid immediately, while allowing unpaid expenses when necessary.

## 16.2 Owner Withdrawals

The system must provide a separate module for personal withdrawals made by the shop owner or family members.

Fields:

- Withdrawal ID.
    
- Date.
    
- Person Making the Withdrawal.
    
- Amount.
    
- Currency.
    
- Financial Account.
    
- Description.
    
- Created By.
    

Owner withdrawals must reduce the relevant financial account but must not be classified as operating expenses.

Provide a report showing withdrawals by person and date.

# 17. Opening Balances and Initial Data Migration

The shop already has existing financial and inventory balances.

The system must provide dedicated opening-balance procedures for:

1. Inventory quantities.
    
2. Customer balances.
    
3. Supplier and beekeeper balances.
    
4. Representative-held balances.
    
5. Cash and bank balances.
    
6. Outstanding and credit balances by currency.
    

Opening balances must include:

- Effective Date.
    
- Related Entity.
    
- Amount or Quantity.
    
- Currency, where applicable.
    
- Notes.
    
- User.
    

Opening transactions must be distinguishable from regular business transactions.

Do not import historical transactions and also enter the same amounts as opening balances, as this would duplicate balances.

# 18. Reports and Dashboard

The initial reporting module must include:

1. Sales Report.
    
2. Purchase Report.
    
3. Current Inventory Report.
    
4. Product Inventory Movement Report.
    
5. Low-Stock Report.
    
6. Customer Statement.
    
7. Supplier and Beekeeper Statement.
    
8. Beekeeper Advance Payments Report.
    
9. Representative Balances Report.
    
10. Representative Account Statement.
    
11. Receipt Vouchers Report.
    
12. Payment Vouchers Report.
    
13. Cash and Bank Balances Report.
    
14. Expenses by Category.
    
15. Owner Withdrawals Report.
    
16. External Orders by Status.
    
17. Delivery Cost Report.
    

Reports must support appropriate filtering by:

- Date Range.
    
- Customer.
    
- Supplier.
    
- Product.
    
- Representative.
    
- User.
    
All report totals are in YER.

A reliable net-profit report is not required in the first release.

Future profit reporting should use an approved inventory valuation method, preferably weighted average cost, in YER.

## 18.1 Dashboard

The dashboard should display key operational indicators:

- Today's Sales.
    
- Today's Purchases.
    
- New External Shipments.
    
- Orders Awaiting Delivery.
    
- Current Inventory Alerts.
    
- Outstanding Customer Balances.
    
- Supplier Payables and Advances.
    
- Representative-held Funds.
    
- Cash and Bank Balances.
    
- Recent Transactions.
    

All financial indicators are denominated in YER.

Avoid loading large transaction histories when opening the dashboard.

# 19. Users, Roles, and Permissions

The system must require authenticated access.

Use a role-based permission model.

Initial roles:

## Administrator

- Full system access.
    
- User and permission management.
    
- System settings.
    
- Backup creation and restoration.
    
- All financial reports.
    
- Stock adjustments.
    
- Approval of sensitive transactions.
    

## Sales User

- Direct sales.
    
- External orders.
    
- Customer management within assigned permissions.
    
- Customer collections.
    
- Viewing permitted sales records.
    

## Inventory and Purchasing User

- Product management.
    
- Supplier management.
    
- Purchase registration.
    
- Inventory monitoring.
    
- Inventory movement history.
    
- Authorized inventory adjustments.
    

Roles and permissions should remain extensible.

Permissions must be enforced on the server side, not merely by hiding interface elements.

# 20. User Activity and Audit Trail

Every financial, inventory, and commercial operation must be associated with the authenticated user who performed it.

This particularly applies to:

- Sales.
    
- Purchases.
    
- Receipt Vouchers.
    
- Payment Vouchers.
    
- Sales Returns.
    
- Stock Adjustments.
    
- Order Status Changes.
    
- Expenses.
    
- Owner Withdrawals.
    

Transaction records should preserve:

- CreatedBy.
    
- CreatedAt.
    
- UpdatedBy.
    
- UpdatedAt.
    
- ApprovedBy, when applicable.
    

The system must maintain an audit trail for sensitive operations, modifications, approvals, and cancellations.

Approved financial and inventory transactions must never be permanently deleted.

Instead, use cancellation, reversal, or compensating transactions while preserving the original record and the reason for the change.

# 21. Performance Requirements

High responsiveness is a mandatory requirement.

## 21.1 Server-Side Pagination

Never load all records into the browser when opening a list page.

Use server-side pagination for:

- Customers.
    
- Suppliers.
    
- Products.
    
- Sales.
    
- Purchases.
    
- Vouchers.
    
- Inventory Movements.
    
- Expenses.
    

Default page size:

- 20 or 25 records.
    

Support:

- Page Navigation.
    
- Configurable Page Size.
    
- Search.
    
- Filtering.
    
- Sorting.
    

Search and filtering must be performed on the server side.

## 21.2 Database Query Optimization

- Create appropriate indexes.
    
- Avoid retrieving unnecessary columns.
    
- Avoid loading complete transaction histories for summary views.
    
- Prevent repeated database queries for individual records.
    
- Use current-balance records for inventory summaries.
    
- Optimize queries used by reports.
    
- Monitor slow operations and improve them.
    

## 21.3 Interface Responsiveness

- Load transaction details only when needed.
    
- Minimize unnecessary requests.
    
- Avoid loading large datasets.
    
- Keep responses compact.
    
- Display clear loading and error states.
    
- Prioritize fast common operations, especially sales and collections.
    

Do not introduce unnecessary caching or complex performance infrastructure without measurable need.

# 22. Reliability and Backup Strategy

Data protection is a core requirement.

## 22.1 Automatic Backups

- Generate automatic daily backups.
    
- Store backups in a location separate from the main application server.
    
- Encrypt backup files.
    
- Maintain daily, weekly, and monthly retention policies.
    

## 22.2 Manual Backups

Provide administrators with a dashboard action to create and download a backup.

The downloaded backup should be usable on an external device, including a mobile phone.

A backup must include all required application data, database records, and necessary attached files.

Backup files must be encrypted, and recovery credentials must be managed securely.

## 22.3 Restoration

Provide a controlled restoration process.

Before restoring:

- Validate the backup file.
    
- Warn the administrator that current data may be replaced.
    
- Require explicit confirmation.
    
- Preserve a recent backup of the current environment.
    
- Record the restoration operation and the user responsible.
    

Restoration must be tested periodically.

Creating a backup without verifying its restorability is insufficient.

# 23. User Experience and Visual Identity

The system must have a consistent and professional visual identity.

The design should include:

- Business Logo.
    
- Consistent Color Palette.
    
- Clear Typography.
    
- Consistent Icons.
    
- Standardized Buttons and Forms.
    
- Reusable Design Components.
    
- Consistent Status Indicators.
    

## 23.1 Mobile-First Responsive Design

Mobile phones have the highest design priority.

The system must work smoothly on:

1. Smartphones.
    
2. Tablets.
    
3. Laptops and Desktop Computers.
    

Mobile requirements:

- Simple navigation.
    
- Touch-friendly controls.
    
- Short and clear input forms.
    
- No unnecessary horizontal scrolling.
    
- Convert wide tables into responsive cards or simplified views.
    
- Fast access to sales and collection operations.
    
- Clear transaction summaries.
    
- Consistent Arabic right-to-left layout.
    

The interface should remain usable on small displays without compromising important business information.

# 24. Development and Architecture Guidelines

Organize the application into clear and maintainable functional modules.

Separate responsibilities for:

- User Interface.
    
- Business Operations.
    
- Business Rules.
    
- Data Management.
    
- Financial Processing.
    
- Inventory Processing.
    
- Security and Backup Operations.
    

Important requirements:

1. Inspect the existing project before making changes.
    
2. Preserve useful existing work and conventions.
    
3. Keep business rules separate from interface code.
    
4. Use dedicated business services for sales, purchases, stock, and financial operations.
    
5. Make related database changes atomic.
    
6. Validate all important operations on the server.
    
7. Enforce authorization on protected operations.
    
8. Protect user credentials and sensitive information.
    
9. Maintain clear audit records.
    
10. Avoid unnecessary architectural complexity.
    
11. Prefer simple, well-defined modules over tightly coupled components.
    
12. Keep the structure easy to understand, maintain, test, and extend.
    
13. Do not introduce external services or dependencies without a clear business justification.
    

# 25. Implementation Phases

Development must proceed incrementally.

Each phase must be completed and tested before starting the next.

## Phase 1: Project Foundation

- Inspect the existing project.
    
- Establish the application structure.
    
- Design the main data model and relationships.
    
- Set up the database.
    
- Implement authentication.
    
- Create the initial administrator account.
    
- Establish basic permissions.
    
- Implement the initial visual identity and responsive layout.
    

## Phase 2: Products and Inventory

- Product categories.
    
- Product management.
    
- Base inventory units.
    
- Purchasing unit conversions.
    
- Inventory movement ledger.
    
- Current inventory balances.
    
- Opening stock balances.
    
- Stock adjustments.
    
- Inventory reports.
    

Test stock calculations before proceeding.

## Phase 3: Suppliers and Purchasing

- Supplier management.
    
- Beekeeper classification.
    
- Supplier accounts.
    
- Advance payments.
    
- Purchase invoices.
    
- Purchase details.
    
- Partial and full payments.
    
- Automatic inventory movements.
    
- Supplier statements.
    

## Phase 4: Customers, Sales, and Returns

- Customer management.
    
- Customer accounts.
    
- Direct sales.
    
- External sales registration.
    
- Order status management.
    
- Inventory deduction.
    
- Sales returns.
    
- Order cancellations.
    
- Partial payments and collections.
    
- Customer statements.
    

## Phase 5: Financial Management

- Financial accounts.
    
- Receipt vouchers.
    
- Payment vouchers.
    
- Automatic voucher generation.
    
- Transfers between financial accounts.
    
- YER-denominated transactions.
    
- Expenses.
    
- Packaging material expenses.
    
- Owner withdrawals.
    
- Cash and bank balances.
    

## Phase 6: Representatives and Delivery

- Representative management.
    
- Default representative country: Saudi Arabia.
    
- Representative financial accounts.
    
- Customer collections through representatives.
    
- Representative handovers.
    
- Delivery provider directory.
    
- Delivery cost recording.
    
- Representative statements.
    

## Phase 7: Reports, Security, and Final Testing

- Implement required reports.
    
- Complete permission validation.
    
- Review audit records.
    
- Test performance.
    
- Implement backup and restoration.
    
- Test mobile, tablet, and desktop interfaces.
    
- Resolve outstanding defects.
    
- Prepare the first production release.
    

# 26. Acceptance Criteria and Essential Test Scenarios

The first release must pass the following scenarios.

## Scenario 1: Honey Purchase Unit Conversion

- Create a honey product with kilograms as its base unit.
    
- Purchase 20 gallons.
    
- Use a conversion factor of 7 kg per gallon.
    
- Verify that inventory increases by 140 kg.
    
- Sell 1.5 kg.
    
- Verify that the remaining stock is 138.5 kg.
    

## Scenario 2: Beekeeper Advance

- Register an approved beekeeper.
    
- Record an advance payment of 100,000 YER.
    
- Record a honey purchase worth 80,000 YER.
    
- Verify that the beekeeper has an outstanding advance balance of 20,000 YER.
    

## Scenario 3: Customer Balance

- Create a sales invoice worth 100,000 YER.
    
- Record a customer payment of 60,000 YER.
    
- Record a sales return worth 10,000 YER.
    
- Verify that the remaining customer balance is 30,000 YER.
    

## Scenario 4: Representative Collection

- Register a representative in Saudi Arabia.
    
- Record a collection of 200 YER from a customer through the representative.
    
- Verify that the customer balance decreases.
    
- Verify that the representative's held funds increase.
    
- Record the representative transferring the amount to the shop.
    
- Verify that the representative balance decreases and the receiving financial account increases.
    

## Scenario 5: External Order

- Register an external order when the products leave the shop.
    
- Set its status to Shipped.
    
- Verify that inventory is deducted.
    
- Change the status to Delivered.
    
- Verify that no additional inventory deduction occurs.
    
- Cancel another order only after physically receiving the complete shipment.
    
- Verify that the complete order inventory effect is reversed once.
    

## Scenario 6: Receipt and Payment Vouchers

- Record a customer receipt.
    
- Verify that the receiving financial account increases.
    
- Verify that the customer outstanding balance decreases.
    
- Record a delivery provider payment.
    
- Verify that the financial account decreases.
    
- Verify that the delivery expense is recorded exactly once.
    

## Scenario 7: YER-only transactions

- Verify product prices, purchases, sales, accounts, vouchers, and expenses are recorded in YER.
    
- Verify that non-YER financial transactions are rejected.
    

## Scenario 8: User Permissions

- Create a Sales User.
    
- Verify that the user can perform authorized sales operations.
    
- Verify that the user cannot manage system users or perform restricted administrative actions.
    
- Verify that every transaction records its creator.
    

## Scenario 9: Performance

- Create a large number of customer records.
    
- Open the customer listing page.
    
- Verify that only the requested page of records is loaded.
    
- Test searching, filtering, and sorting.
    
- Ensure that the application does not load the entire customer dataset into the browser.
    

## Scenario 10: Backup and Restoration

- Generate a manual backup.
    
- Download it to an external device.
    
- Restore it in a test environment.
    
- Verify the integrity of customers, products, sales, purchases, inventory, financial accounts, and transaction history.
    

# 27. Features Excluded from the First Release

To keep the initial release focused and achievable, defer:

- Full General Accounting and Double-Entry Bookkeeping.
    
- Payroll and Human Resources.
    
- Multi-Branch and Multi-Warehouse Management.
    
- Tax and Electronic Invoicing Integrations.
    
- Automated Representative Commission Calculations.
    
- Dedicated Customer or Representative Mobile Applications.
    
- Shipping Company Integrations.
    
- Automated WhatsApp Messaging.
    
- Packaging Material Inventory Tracking.
    
- Advanced Profit Analytics.
    
- Automated External Notifications.
    

These features may be introduced in future releases after the core business operations are stable.

---

# 28. Final Instructions to the Development Agent

The objective is to deliver a practical and reliable web-based business management system, not an unnecessarily complex enterprise platform.

Prioritize:

1. Correct inventory calculations.
    
2. Accurate customer, supplier, and representative balances.
    
3. Reliable financial transactions.
    
4. Consistent YER-only financial handling.
    
5. Fast and responsive interfaces.
    
6. Secure access and transaction auditing.
    
7. Reliable backup and restoration.
    
8. Mobile-first user experience.
    
9. Maintainable and extensible project structure.
    

**Implementation Rules:**

- Begin by reviewing the entire existing project.
    
- Do not start implementing every module at once.
    
- Complete the data model and core business rules before building extensive interfaces.
    
- Implement one functional phase at a time.
    
- Test every important business scenario.
    
- Do not create duplicate financial or inventory movements.
    
- Never silently change approved historical transactions.
    
- Do not invent unspecified business rules.
    
- Document unresolved assumptions and request clarification only when a decision materially affects business correctness.
    
- Avoid unnecessary features and dependencies.
    
- Deliver working functionality rather than placeholder screens.
    

**Final Goal:** A stable, fast, secure, mobile-friendly, and easy-to-use first release that accurately reflects the actual daily operations of the honey shop and provides a solid foundation for future development.