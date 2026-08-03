# ATRILAK POS Receive Stock V2 Design

## Approved scope

Receive Stock V2 adds a safe workflow for receiving inventory from an active supplier or recording self-production. It supports preview, multiple product lines, product/unit search, average-cost recalculation, base-unit stock movement, idempotent confirmation, history filters, and receipt details.

The attached Owner specification is treated as the approved product design for this sprint; no additional business-rule approval is required unless implementation discovers a Production risk, an Average Cost rule change, or an unsafe historical Edit/Void decision.

## Architectural decision

Keep the existing `/purchases` resource and `PurchaseService` behavior intact for regression safety. Add a dedicated `ReceiveStockController`, `ReceiveStockService`, preview storage/validation contracts, and `receivings.*` routes for the V2 workflow. Both workflows persist to the existing Purchase/PurchaseItem domain, so stock and cost history remain in the same reporting model.

The new service will reuse `AverageCostService` for the authoritative weighted-average calculation and `StockLockService::lockProducts()` for ascending Product row locks. It will not call Pricing Engine or update selling price. When a non-base Product Unit is selected, entered quantity is converted to base quantity and entered unit cost is converted to base cost before Average Cost calculation; the Purchase Item keeps entered-unit values and conversion snapshots for audit/detail display.

## Persistence changes

Add one forward-safe migration:

- `purchases`: nullable `supplier_id` for self-production, `source`, supplier document number, status, creator, and unique nullable idempotency key.
- `purchase_items`: optional Product Unit and conversion snapshots, base quantity, average-cost before/after, stock before/after, and Stock Movement reference.

Existing rows remain untouched. Null source/status values on historical rows are presented as legacy supplier/posted records. Edit and delete of legacy receipts remain outside this sprint; V2 does not expose Edit/Void controls.

## Runtime flow

1. Manager opens `receivings.index`, filters history, or starts `receivings.create`.
2. Product search returns active products by name, product code, barcode, or ProductBarcode, including current stock, average cost, selling price, and usable units.
3. Preview request validates source/supplier, active Product/Supplier/Unit references, positive quantity/cost, duplicate Product lines, and conversion values. It calculates line totals and old/new Average Cost without writes, then stores a user-owned expiring preview token.
4. Confirm revalidates the token and all references, takes a per-idempotency lock, locks Product rows in ascending ID order inside one database transaction, creates Purchase/PurchaseItems, recalculates Average Cost with the existing service, updates stock, creates `IN` Stock Movements, records snapshots, and commits.
5. Repeated confirmation with the same idempotency key returns the existing receipt instead of creating another receipt. Any exception rolls back the complete receipt and leaves no partial movement or cost update.

## Protected rules and explicit non-goals

- Stock is changed only with a Stock Movement.
- Average Cost uses the existing `AverageCostService` precision/rounding.
- Selling Price and Price Lock are read-only during receiving.
- Pricing Engine is never applied automatically; Pricing Management detects cost changes through existing `pricing_reviewed_cost` behavior.
- Supplier source requires an active Supplier; production source does not.
- No new Product, Supplier, Unit, Tier Price, image, CSV, or Production deployment behavior.
- No V2 Edit/Void implementation. The existing legacy Edit/Delete flow is characterized and regression-tested; a safe reversal/retroactive Average Cost rule is required before any future V2 cancellation work.

## Verification strategy

- Unit/feature tests for validation, source/supplier rules, unit conversion, totals, Average Cost, snapshots, stock movement, selling-price invariance, rollback, idempotency, permissions, history filters, and detail rendering.
- Existing Purchase/Stock/Pricing/Product/Excel Import regression suites.
- Browser smoke on a dedicated SQLite test database for supplier receipt, self-production receipt, and rejected-error/duplicate-confirm scenarios.
- PHP syntax, Pint, `git diff --check`, and PostgreSQL test-database verification when an explicit non-Production test database is available. No Production migration or query will be run.
