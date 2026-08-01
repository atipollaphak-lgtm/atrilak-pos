# ATRILAK POS Go-Live Preflight

วันที่: 2026-08-01  
Project: `C:\laragon\www\atrilak-pos`  
Test command: `php artisan test --env=testing tests/Feature/GoLive/GoLivePreflightTest.php`

## Environment identity

- Application environment: `testing`
- Database connection: `pgsql`
- Test database: `atrilak_pos_final_test_20260729`
- PostgreSQL server: `127.0.0.1:5432`
- Production database was not used for test commands
- No migration, reset, seed, restore or business-data write was performed during preflight

## Schema characterization

The project does not have a separate `payments` table. Payment data is stored on `sales` using `payment_method`, `cash_amount`, `promptpay_amount`, `received_amount` and `change_amount`. Preflight therefore checks the actual schema rather than assuming a table name from the generic sprint checklist.

Required Test Database tables checked:

- `products`
- `sales`
- `sale_items`
- `purchase_items`
- `stock_movements`
- `daily_payment_closings`
- `daily_payment_closing_sales`
- `settings`

Read-only baseline counts from the Test Database:

- `users`, `roles`, `products`, `product_units`, `categories`, `customers`, `suppliers`, `purchases`, `purchase_items`, `sales`, `sale_items`, `stock_movements`, `daily_payment_closings`, `daily_payment_closing_sales`, `settings`: all `0`
- `migrations`: `101`
- Total tables: `49`

## Result

Preflight passed after aligning the check with the actual schema. The approved Test Database is reachable and has the required business tables and payment columns. Subsequent commands must continue to pass `--env=testing` explicitly and must not target the default `.env` database.
