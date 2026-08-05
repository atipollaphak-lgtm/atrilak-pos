# POS V3 and Invoice UX Sprint

## Scope

- Work only on `codex/next-sprint-20260804`.
- Keep POS V1/V2 and protected pricing, rounding, delivery-fee, profit, stock, numbering, and document snapshot rules unchanged.
- Use the configured non-production test database and do not commit, merge, push, or deploy.

## Design decisions

1. Treat `sale_date` as the server-assigned payment/sale date for POS V3.
2. Store `delivery_date` separately; pickup clears it and delivery may select it.
3. Keep the existing pricing engine. Make the product card use the same frontend pricing context as the cart, and verify the backend snapshot with a parity test.
4. Keep inline quantity and price editing. Remove only the cart unit-reset button and its restore listener/style; leave each cart row with the delete action only.
5. Reset payment UI/state when starting a new bill, expose notes, clarify customer/tax copy, and show non-blocking hold feedback.
6. Enlarge the POS V3 controls and typography for comfortable 100% browser use while constraining the grid and modal to avoid horizontal overflow.
7. Use one shared A4/A5 invoice/delivery-note layout: three-column footer, configurable multiline footer message, no recipient signature block, light horizontal row guides plus vertical column rules, wrapped product names, and larger balanced typography.

8. Owner acceptance preference: restore light horizontal dividers between product rows while retaining the vertical column rules, light header background, and highlighted grand total.

## Verification

- Run focused PHP and Node tests first and record the intentional red result before implementation.
- Run focused and broader relevant tests after implementation, plus PHP/JS syntax checks and `git diff --check`.
- Use a real connected browser session where available to exercise POS V3 and inspect A4/A5 preview at Scale 100%; report if the environment only exposes the in-app browser.
- Leave all changes uncommitted for Owner review.
