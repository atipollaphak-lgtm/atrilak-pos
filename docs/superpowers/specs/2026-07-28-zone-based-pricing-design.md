# Zone-Based Pricing and Dynamic Delivery Fee

## Scope

Use the existing `delivery_zones` records and the selected customer delivery address as the only zone source. Add a decimal zone markup percentage, preserve legacy delivery columns for compatibility, and stop using fixed zone delivery fees in new sale logic.

## Rules

- Pickup uses the base/tier price and zero delivery fee.
- Delivery requires a customer address with an active zone.
- Resolve a sale-unit/tier base price, apply zone markup with decimal arithmetic, then apply the existing rounding policy.
- Recalculate authoritative line prices, product profit, delivery fee, totals, payment, and snapshots inside the sale transaction.
- Delivery fee is `max(0, minimum_profit - product_profit)` after discounts; it is never trusted from the browser.
- Sale history uses stored sale-item prices/costs and immutable zone header snapshots.

## Compatibility

No old migration is edited and no historical sale/address/zone data is deleted. `base_delivery_fee` and `free_delivery_min_amount` remain available for old records and compatibility, but are removed from the new pricing/delivery calculation and UI.

## Verification

Add unit/feature regression coverage for zone settings, pricing order, pickup/delivery switching, address/zone validation, dynamic delivery fees, backend tamper resistance, snapshots, documents, reports, and existing sale lifecycle behavior. Run scoped Laravel tests, Pint, JavaScript checks, route listing, and browser verification before shipping.
