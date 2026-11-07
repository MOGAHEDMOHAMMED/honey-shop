# Approved decisions and implementation boundaries

## Platform and data ownership

- The application is one Laravel 13/PHP 8.3+ modular monolith using PostgreSQL 17, Eloquent/Query Builder, Blade, Bootstrap 5 RTL, vanilla JavaScript, and Vite.
- Existing PostgreSQL SQL migrations are authoritative. Schema changes are forward-only SQL files, applied in order and recorded in `schema_migrations`; Laravel schema migrations are prohibited.
- Use the existing `app_users`, roles, and permissions for identity and access control.
- YER is the only supported business currency. No FX, tax/VAT, or currency conversion is approved.
- Category base units are Honey → `KG`, Ghee → `CAN`, Nuts → `KG`. Honey `GALLON` input converts at exactly 7 KG. No units table is permitted.
- Beekeepers are supplier records distinguished by the existing supplier type; they are not a separate entity.
- Acquisition cost is the complete landed product cost. Do not add a separate shipping/acquisition-cost allocation workflow.
- Do not recreate `product_prices`: current selling price and maximum discount ratio remain on `products`.

## Monetary arithmetic

- YER calculations and stored monetary results use two decimal places and exact decimal arithmetic. PHP float/double arithmetic is prohibited for financial calculations.
- Apply commercial `ROUND_HALF_UP` to two places.
- For a discounted sales line: `gross_amount = ROUND_HALF_UP(quantity × unit_price, 2)`; `net_amount = ROUND_HALF_UP(quantity × unit_price × (1 - discount_rate), 2)`; `discount_amount = gross_amount - net_amount`.
- Purchase line totals are `ROUND_HALF_UP(quantity × unit_cost, 2)`.
- Sum already-rounded line totals for document totals. Add delivery charge at two-place precision; do not apply unexplained second rounding or create a rounding adjustment line.
- UI and printed documents show YER and suppress only insignificant trailing zeros.

## Product selling-price history

- Preserve the current selling price and maximum discount rate on `products`; append price/discount change events in a separate immutable history record.
- Product creation appends an initial event. A price or discount change appends one event containing prior/new values and the authenticated actor, in the same transaction as the product write. Other edits create no price event.
- Price-history rows are never editable or deletable through the app; the database rejects update/delete.
- Price history does not replace price/discount snapshots on future approved sales lines.

## Purchase and opening inventory

- Draft opening inventory and purchases do not affect stock or supplier balances.
- Approval is atomic and idempotent: update transaction state, create source-linked inventory movements, allow existing triggers to update balances, and post supplier payables once.
- Never permit negative stock or bypass database triggers/constraints.
- Approved financial/inventory records are not silently edited or deleted. Supplier references and original supplier invoice numbers remain preserved; purchases use an internal shop reference, not the sales invoice sequence.
- Do not invent FIFO, weighted-average, or other inventory valuation. Use only acquisition-cost fields expressly supported by the schema; document any remaining costing gap.
- Opening inventory records quantities and approved input-unit conversions only. The current schema has no opening acquisition-cost field, so opening-stock valuation remains unresolved and no costing method is implied.
- A purchase with a zero total may be saved as a draft, but it cannot be approved. The existing supplier-ledger constraint requires a positive debit or credit, and approval must not create a zero-value payable.
- The purchase unit price is the complete landed acquisition cost. Purchase line totals are generated and rounded per line in PostgreSQL before being summed; PHP float/double arithmetic is prohibited.

## Sales invoice numbering (approved future implementation)

- Sales invoices use three independent category series: Honey `74`, Ghee `84`, Nuts `94`, followed by suffixes starting at `1` and incrementing by one (for example `741`, `742`, `743`; `841`, `842`, `843`; `941`, `942`, `943`).
- Invoice numbers are strings, globally unique, permanent, assigned only during approval, and never reused, including after cancellation. Drafts use internal identifiers.
- Never allocate with `MAX(invoice_number) + 1`; use a transactional, concurrency-safe database counter and a unique constraint.
- A single-category invoice selects its category series automatically. Approval of a mixed-category invoice requires an explicit series selection. That selection affects only the prefix, not allowed invoice products, and is persisted on the invoice.
- This milestone documents the rule; it does not implement sales invoices.

## Full cancellation of a paid external order (approved future implementation)

- Cancellation is allowed only after all shipped products have physically returned; partial returns use the sales-return workflow.
- Cancellation reverses the full invoice receivable, but refunds only actual collections less prior refunds. Unpaid/uncollected amounts are never refunded; aggregate refunds cannot exceed actual receipts.
- Record refunds as real customer payment/refund vouchers linked to the sale and cancellation. Preserve the receipt and sale history, update customer and financial-account ledgers, and do not erase courier expenses.
- Prefer the original collection account if it has sufficient funds; for representative collections use the custody account if funded, otherwise an available shop cash/bank account. Never overdraw or fabricate a refund.
- Return verification, stock restoration, ledger reversal, refund voucher/account movement, idempotency, and cancellation status must commit in one transaction. If a valid account lacks sufficient funds, roll back and leave cancellation unapproved.
- This milestone records the policy only; no cancellation or refund UI/workflow is implemented.

## Current implementation and future boundary

The application implements authentication, catalog and price history, supplier management, opening inventory, and purchase workflows. The rules above are approved even where their corresponding later module is not yet implemented. In particular, sales numbering, sales calculation snapshots, returns/refunds, vouchers, taxes, and financial collection interfaces must not be reported as implemented based on this documentation.

Remaining operational decisions are limited to matters not fixed above, such as production hosting, secret management, and TLS deployment.
