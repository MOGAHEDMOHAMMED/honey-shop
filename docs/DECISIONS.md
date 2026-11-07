# Decisions and open questions

## Approved first-release decisions

| Area | Decision |
| --- | --- |
| Currency | YER only. No exchange-rate or multi-currency workflows. Existing SAR/USD reference rows remain inactive. |
| Product units | Honey and nuts use base unit `KG`; ghee uses `CAN`. No units table. |
| Entered units | `KG`, `GALLON`, `CAN`; one honey gallon is exactly 7 KG. Fractional KG is a decimal quantity, not another unit. |
| Prices | Product selling price is per base unit. A gallon of honey uses 7 times the per-KG price. Maximum discount is a percentage. Purchase unit cost includes the full cost of acquiring product and bringing it to the shop. |
| Inventory | Keep movement history and a current balance. Movements reference source details and cannot be posted twice from the same source. Negative stock is rejected. |
| Returns | Sales and purchase return lines reference their exact original lines. Cumulative returned quantity cannot exceed the original quantity. Accepted sales returns are saleable. |
| Order cancellation | Full external-order cancellation only, after confirmation the complete shipment returned. Partial returns use sales returns. A status change alone must not restock goods. |
| Damaged goods | Record as a separate transaction and post an outgoing inventory movement on approval. |
| Expenses | Store original, paid, and generated remaining amount. Expense payments are linked payment vouchers and post account movements atomically. |
| Delivery | The sales invoice holds the customer delivery charge and bearer. The shop-paid portion is a linked delivery expense, at most one per sale. No dedicated delivery accounting subsystem. |
| Accounts | One default YER Main Cashbox; bank accounts remain distinct. One YER custody account per representative; custody balances are not shop cash. |
| Complexity | No double-entry accounting, unnecessary ERP scope, or unapproved modules. |
| Existing applied migrations | Do not edit; use new forward migrations and preserve data. |

These decisions supersede conflicting original-plan language about multi-currency, return product conditions, partial order cancellations, and delivery-cost allocation.

## Architecture recommendation (pending approval)

No backend/frontend technology has been selected in project files, and no application code exists. Recommend ASP.NET Core on .NET 10 LTS with PostgreSQL through Npgsql as one modular application. The current workstation has .NET 10 SDKs; ASP.NET Core supplies dependency injection, configuration, logging, and health-check primitives. This recommendation is **not approval** to scaffold the application or add packages.

## Open decisions

1. Approve or replace the proposed backend stack before application scaffolding.
2. Select the UI approach (server-rendered or SPA) and confirm whether to build the Arabic RTL web UI in the same application.
3. Select an authentication implementation and production secret/hosting strategy before creating user flows.
4. Confirm invoice numbering policy, invoice total/rounding rules, and the exact calculation/entry process for shared delivery costs before implementing those services.
5. Confirm whether sales returns and purchase returns may be entered with multiple original lines for the same product in one return document; the schema currently identifies each original line exactly.
6. Define reversal/refund behavior for cancellation after the customer has already paid. The database records a customer credit but does not automatically issue a refund voucher.
