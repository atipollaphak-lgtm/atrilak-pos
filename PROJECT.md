# ATRILAK POS

## Overview

ATRILAK POS is a Laravel 13 point-of-sale system for a single construction-material store.

Environment:

- Windows 11
- Laragon
- PHP 8.3
- PostgreSQL

Project path:

`C:\laragon\www\atrilak-pos`

## Main Modules

- Products
- Categories
- Units and Product Units
- Customers and Delivery Addresses
- Suppliers
- Purchases
- Sales POS V1
- Sales POS V2
- Stock and Stock Movements
- Pricing Engine
- Product Price Tiers
- Delivery Zones and Fees
- Technician Commission
- Quotations
- Commercial Documents
- Reports
- Backup
- Settings

## POS Applications

### POS V1

POS V1 is the legacy sales interface and must remain operational.

### POS V2

POS V2 is the main sales interface and supports:

- Card-based product selection
- Barcode input
- Customer selection
- Product Unit selection
- Quantity dialog
- Automatic invoice opening after a successful sale

Both POS versions use the same authoritative Sale, Stock, Pricing, Profit, and Commission flow. POS V1 uses the legacy/base-unit factor-1 flow when no Product Unit is supplied. POS V2 can supply and validate a Product Unit for stock conversion.

## Stock and Product Units

Stock is stored in each Product's base unit.

The standard conversion rule is:

`base_qty = sale_qty × conversion_rate`

Where:

- `sale_qty` is the quantity in the selected sale unit.
- `conversion_rate` means one sale unit equals that many base units.
- `base_qty` is the quantity deducted from base stock.

Sale Item snapshots preserve:

- Sale quantity
- Product Unit used
- Conversion rate used
- Base quantity deducted

New Sale Stock Movements record quantity, stock before, and stock after in base units.

Product Unit conversion must be validated before stock is checked or changed.

## Stock Consistency

Multi-step stock changes must be atomic.

Concurrent writers include:

- Sale create, update, and delete
- Purchase create, update, and delete
- Stock Count
- Manual stock adjustment
- Quotation conversion

Product locks use unique Product IDs in ascending order to reduce deadlock risk.

Stock Movement history must remain continuous and consistent with current Product stock.

## Pricing and Profit

Existing pricing behavior includes:

- Average Cost
- Auto Pricing
- Category overrides
- Product overrides
- Price Lock
- Scheduled Pricing
- Product Price Tiers
- ATRILAK rounding

ATRILAK rounding pipeline:

1. Satang rounding to `.50`
2. Baht rounding to `+5`

Sale totals, discounts, delivery fees, costs, and profits retain their established formulas.

## Tier Pricing

Product Price Tiers are currently attached to Product Units. Tier thresholds use sale quantity in the selected sale unit. Do not convert tier quantity to base stock quantity.

Steel is normally intended to remain at full price unless an explicitly configured Product Unit tier says otherwise.

## Delivery and Profit Guard

Delivery features include:

- Delivery Zones
- Customer Delivery Addresses
- Base Delivery Fee
- Minimum Profit

The current Profit Guard compares sale profit and delivery-related values against the Delivery Zone minimum profit. The exact handling of discount, pickup, free-delivery thresholds, and delivery fee is a protected business rule that requires Owner approval before any formula change.

Pickup currently results in no delivery fee in the established POS flow.

## Technician Commission

Commission rule priority is:

1. Product rule
2. Category rule
3. Default rule

Amount-per-unit Commission uses sale quantity.

Percentage Commission uses the established sale-total behavior.

## Quotations

Quotation conversion:

- Locks the Quotation before conversion
- Creates a Sale through the standard Sale flow
- Uses factor 1 because Quotation currently has no Product Unit snapshot
- Can create at most one Sale from the same Quotation
- Preserves the stored Quotation header total

## Commercial Documents

Supported documents:

- Delivery Note
- Tax Invoice
- Quotation

Tax Invoices show:

- Company Tax ID
- Customer Tax ID
- Branch information

Other document types do not display tax information unless explicitly required.

## Sale Validation

Invalid sale requests are rejected when they contain:

- An empty item list
- Mismatched parallel arrays
- Partially blank item rows
- Invalid Product, Customer, Technician, or Product Unit references
- Non-numeric quantity or price
- Zero or negative quantity
- Zero or negative price
- Quantity or price beyond supported precision
- Negative discount or delivery fee
- Invalid dates
- Malformed JSON

Fully blank trailing rows from legacy forms may be ignored.

Duplicate Product and Product Unit lines remain allowed according to the established Sale behavior.

Invalid requests must not create partial Sale, Sale Item, Stock Movement, or Commission records.

## Historical Data

Historical Sale, Stock Movement, Cost, Product Unit snapshot, and Commission records are treated as immutable unless a separately approved reconciliation or repair task is performed.

Legacy Sale Items without conversion snapshots must continue to restore stock using their original qty as factor 1.

Production data must never be modified by diagnostic or reconciliation tools.

## Current Goal

Prepare and maintain a stable production-ready POS, prioritizing:

1. Data integrity
2. Stock accuracy
3. Correct pricing and profit
4. Reliable commercial documents
5. Security and permissions
6. Maintainable UX

Performance improvements are welcome when they preserve established business behavior.

## Current Known Configuration Issues

- Product Unit ID 7 for อิฐบล็อก / ก้อน has conversion rate 1 and matches the Product unit, but `is_base_unit` is currently false. Owner review is required before enabling conversion in production.
- The ทรายหยาบ Product Units for ตัน and บุ้งกี๋ have not been confirmed. Their conversion rates must not be enabled until the physical relationship to the base unit is verified.
- Non-base Product Units require `conversion_confirmed_at` before POS V2 may use them.
- These configuration records must not be changed automatically by Codex.
