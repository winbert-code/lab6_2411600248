# ByteStock — Computer Parts Inventory Management System

Laravel MVC implementation of Laboratory Exercise 6, extending the Lab 5
ByteStock project with the suggested **computer parts shop** theme instead of
the generic hardware-store example. Lab 5 itself is left unchanged.

## Theme & visual identity

- **Business:** ByteStock, an online/local shop selling CPUs, GPUs, motherboards,
  RAM, storage, PSUs, cases, cooling, peripherals, and networking gear.
- **Color scheme ("Green Rush"):** deep greens `#0E5113` / `#03730B`,
  vivid green `#1DBF04`, lime `#A7F205` / `#98D800`, and off-white
  `#F8F8F8`. Sampled from the supplied reference image — no navy/blue.
- The same palette is applied to the login/register screens, dashboard,
  inventory tables, forms, badges, and buttons through `public/css/app.css`.

## Stack

- Laravel 13 (PHP 8.3, Composer)
- MySQL via XAMPP (`computer_parts_inventory`)
- Bootstrap 5 + Bootstrap Icons (CDN)
- Chart.js (CDN) for the dashboard category chart
- Server-rendered Blade views; no npm build step required

## Quick start

1. Start Apache/MySQL in XAMPP and ensure database `computer_parts_inventory` exists:
   ```powershell
   C:\xampp2\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS computer_parts_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
2. Confirm `.env` database settings:
   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=computer_parts_inventory
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. Migrate and seed:
   ```powershell
   C:\xampp2\php\php.exe artisan migrate:fresh --seed
   ```
4. Run the app on port 8001 (port 8000 is kept for the Lab 5 project):
   ```powershell
   C:\xampp2\php\php.exe artisan serve --host=127.0.0.1 --port=8001
   ```
5. Open <http://127.0.0.1:8001> and log in with:

   | Email | Password |
   | --- | --- |
   | `winbert@gmail.com` | `winbert906` |
| `staff@bytestock.test` | `staff123` |

## Features mapped to the lab exercise

| Lab part | Implementation |
| --- | --- |
| Environment & DB config | Laravel 13 project, MySQL `.env`, migrations |
| Authentication | Laravel `Auth` facade with custom login/register Blade views, hashed passwords, `remember me`, throttle on POST routes, guest/auth middleware |
| Users & roles | Admin-only `UserController` and `users/*` views: add users as admin, manager, staff or user, edit roles and passwords, deactivate/restore accounts, and filter by role/status; inactive users cannot log in and you cannot deactivate yourself |
| Models & migrations | `User`, `Brand`, `Category`, `Product`, `InventoryTransaction`, `PurchaseOrder`, `PurchaseOrderItem`, `SalesOrder`, `SalesOrderItem`. Products belong to a brand and a category via foreign keys; unique SKU, casts, `active`/`inactive` status scopes, `lowStock`/`outOfStock` scopes, `stockStatus()` |
| Brands & categories | First-class `brands` and `categories` tables with their own pages under **Brands** and **Categories**; removing an item marks it inactive (never deletes it), and the restore button makes it available for stock again; inactive items are hidden from new-product dropdowns but kept for products already using them |
| CRUD controller | `app\Http\Controllers\ProductController.php` (resource controller with implicit route binding) |
| Validation | `StoreProductRequest` / `UpdateProductRequest` with custom messages, SKU uniqueness on create/update, normalized uppercase SKU, active brand/category validation |
| Navigation | Dashboard, Inventory (stock overview), Products (CRUD + deactivate/restore), Purchase Orders, Sales Orders, Receipts, Categories (CRUD + deactivate/restore), Brands (CRUD + deactivate/restore), Users (admin only), Activity Log, Transactions, Reports |
| Blade views | `layouts/app`, `layouts/guest`, dashboard, inventory, product index/create/edit/show, purchase/sales order index/form/show, sales receipt list/detail, category/brand/user CRUD views, activity log, reusable `components/stock-badge`, shared form partials |
| Routes | `routes/web.php`: guest auth routes + auth-protected dashboard, inventory, resource routes for products, categories, brands and users, plus purchase/sales orders, sales receipts, activity log, transactions, reports and notifications |
| Dashboard with real data | `DashboardController` computes product count, low/out-of-stock counts, total inventory value, recent products, category totals, and chart data |
| Seeder | `ProductSeeder` inserts 20 realistic computer parts across 10 categories, including low-stock and out-of-stock examples; `UserSeeder` adds a demo staff account; `StaffNotificationSeeder` puts one example low-stock alert in that account's bell; `OrderSeeder` adds 3 demo purchase orders and 6 demo sales orders (draft/pending, a staff-pending order, plus received/completed) and reuses matching demo stock movements so stock is not deducted twice |
| Theme integration | `public/css/app.css` CSS variables override Bootstrap defaults; same style on auth and app pages |
| Stock audit trail | `inventory_transactions` table logs initial stock and quantity adjustments, shown on the product detail page |
| Purchase orders | `PurchaseOrder` + `PurchaseOrderItem` with draft/received/cancelled statuses, multi-line items, edit/delete while draft, and **Receive stock** which writes one stock-in transaction per line through the observer so quantities and balances stay in sync; every staff/admin action sends a bell notification to the other role |
| Sales orders | `SalesOrder` + `SalesOrderItem` with pending/approved/completed/cancelled statuses, stock-aware item form that lists only in-stock products, aggregated stock validation before approval and fulfilment, and **Complete order** which writes stock-out transactions through the same observer; every sales order needs admin approval and out-of-stock orders are rejected; completed orders get a view-only sales receipt |
| Activity log (audit log) | `ActivityLog` model and `activity-logs.index` page (ported from Lab 5) record sign-ins, registrations and every create/update/deactivate/restore action across products, categories, brands and users, with search and module/action/user filters and a recent-activity panel on the dashboard |
| Inactive products | The delete action sets `status` to `inactive` instead of removing rows; products and inventory history stay in the database and can be restored |
| Testing | 29 automated tests (12 inventory-tracking + 10 order-management + 6 role-access + 1 staff notification) plus browser verification of login, dashboard statistics, inventory overview, product/category/brand CRUD, inactive brand/category restore, user roles, inactive-user login rejection, transaction logging, reports, order approval/receive/complete flows, view-only receipts and receipt lists, notification bells and deactivate/restore |

## Important files

```text
app/Models/User.php
app/Models/ActivityLog.php
app/Models/Product.php
app/Models/Brand.php
app/Models/Category.php
app/Models/InventoryTransaction.php
app/Models/PurchaseOrder.php
app/Models/PurchaseOrderItem.php
app/Models/SalesOrder.php
app/Models/SalesOrderItem.php
app/Http/Controllers/ProductController.php
app/Http/Controllers/PurchaseOrderController.php
app/Http/Controllers/SalesOrderController.php
app/Http/Controllers/ActivityLogController.php
app/Http/Controllers/InventoryController.php
app/Http/Controllers/BrandController.php
app/Http/Controllers/CategoryController.php
app/Http/Controllers/UserController.php
app/Http/Controllers/DashboardController.php
app/Http/Controllers/Auth/LoginController.php
app/Http/Controllers/Auth/RegisterController.php
app/Http/Requests/StoreProductRequest.php
app/Http/Requests/UpdateProductRequest.php
config/inventory.php
database/migrations/*_create_brands_table.php
database/migrations/*_create_categories_table.php
database/migrations/*_create_products_table.php
database/migrations/*_create_inventory_transactions_table.php
database/seeders/BrandSeeder.php
database/seeders/CategorySeeder.php
database/seeders/ProductSeeder.php
database/seeders/OrderSeeder.php
database/seeders/UserSeeder.php
resources/views/layouts/app.blade.php
resources/views/layouts/guest.blade.php
resources/views/dashboard/index.blade.php
resources/views/activity-logs/*.blade.php
resources/views/inventory/*.blade.php
resources/views/products/*.blade.php
resources/views/sales-orders/receipt.blade.php
resources/views/sales-orders/receipts.blade.php
resources/views/brands/*.blade.php
resources/views/categories/*.blade.php
resources/views/users/*.blade.php
resources/views/transactions/*.blade.php
resources/views/reports/*.blade.php
public/css/app.css
```

## Note on Breeze

The lab suggests Laravel Breeze with the Blade stack. This environment has no
Node/npm, which Breeze's default Tailwind/Vite build needs. The equivalent
functionality is implemented directly with Laravel's built-in authentication
facilities and custom Bootstrap/Blade views, so the same custom color scheme is
applied without a frontend build step.

## Laboratory Exercise 6 — real-time inventory tracking

This folder extends the Lab 5 project with the real-time inventory features.

### Lab 5 features updated in Lab 6

| Lab 5 feature | What changed in Lab 6 |
| --- | --- |
| Environment & database | Same Laravel 13 + MySQL setup and shared `computer_parts_inventory` database. Lab 6 runs on port **8001** so Lab 5 stays on **8000**; new migrations are guarded so the two projects can share the database safely. |
| Authentication | Same custom login/register views, hashed passwords, `remember me`, throttling and guest/auth middleware. Added the inactive-user login guard and now records every sign-in/sign-out in the Activity Log. |
| Users & roles | Lab 5's admin/staff accounts and role/status filters are kept. Lab 6 adds **manager** and plain **user** roles, makes user management admin-only, keeps the “cannot deactivate yourself” rule, and drives access through Gates instead of ad-hoc checks. |
| Models & migrations | `User`, `Brand`, `Category`, `Product`, `InventoryTransaction` and `ActivityLog` are carried over. Lab 6 adds `PurchaseOrder`, `PurchaseOrderItem`, `SalesOrder`, `SalesOrderItem` and the `notifications` table. |
| Brands & categories | Same first-class tables/pages, soft deactivate/restore and “inactive items hidden from new-product dropdowns but kept on existing products” behavior. Write actions are now limited to admin/manager by the `manage-inventory` Gate. |
| Products CRUD | Same resource controller, implicit binding, uppercase unique SKU and soft deactivate/restore. Validation now also rejects inactive brands/categories, and stock changes flow through observer-written transactions instead of direct quantity edits. |
| Validation | SKU uniqueness and custom messages are retained. Added active brand/category checks, aggregated stock checks before sales fulfilment, and an out-of-stock guard that excludes zero-quantity products from sales orders. |
| Navigation & layout | All Lab 5 links remain. Added Purchase Orders, Sales Orders, Transactions, Reports and the bell notification menu; Users is now admin-only, and staff get a role notice plus hidden stock actions. |
| Dashboard | Kept the live statistics cards, recent products and category chart. Added a low-stock panel, recent transaction feed and recent-activity panel; the Lab 5 brand summary was replaced by category totals and the transaction feed. Dashboard data is now also polled by API. |
| Blade views | Kept `layouts/app`, `layouts/guest`, dashboard, inventory, product/category/brand/user CRUD and activity-log views. Added order, transaction, report and notification views, plus reusable role-aware navbar actions. |
| Routes | Same guest/auth routing pattern. Added role middleware for users/catalog/stock/orders/reports, order approval and notification routes, and hardened malformed-URL handling with a fallback plus `NotFoundHttpException` renderer. |
| Stock audit trail | The Lab 5 `inventory_transactions` table is retained and expanded with `in`/`out`/`adjustment` types, reference, reason, running balance and audit scopes. Product history now shows the running balance and date filters. |
| Activity Log | Still the audit log for sign-ins, registrations and every create/update/deactivate/restore across products, categories, brands and users, with the same search/module/action/user filters and dashboard panel. Lab 6 drops Lab 5's page-visit/search logging and adds order create/update/receive/approve/complete/cancel entries. |
| Inactive records | Soft deactivate/restore is retained for products, brands and categories, and extended to user accounts with the inactive-login rejection. |
| Seeder & theme | Same 20-product seeder and Green Rush theme. Added demo staff user, purchase/sales orders, staff low-stock alert and order notifications. |
| Testing | Lab 5's browser checks were kept. Lab 6 adds 29 automated tests covering stock movements, orders, approvals, receipts, notifications and role permissions. |

### What was added

| Lab 6 part | Implementation |
| --- | --- |
| Transaction model & schema | `InventoryTransaction` with `in`, `out` and `adjustment` types, reference, reason, notes, `previous_quantity`, `balance_after`, indexes and audit scopes |
| Automatic stock updates | `App\Observers\InventoryTransactionObserver` registered with `#[ObservedBy]`; locks the product row, updates quantity, records running balance and rolls back on failure |
| Stock integrity | `DB::transaction()` plus `lockForUpdate()`; `InsufficientStockException` when a stock-out would exceed available stock; validation `after` hook in `StoreInventoryTransactionRequest` |
| Controller & views | `InventoryTransactionController` (index/create/store/show/destroy/adjust) with filtering, pagination, eager loading, admin-only delete and Blade views under `resources/views/transactions/` |
| Product model helpers | `latestTransaction()`, `isLowStock()`, `isOutOfStock()`, `stock status/color` accessors, `lowStock()`, `outOfStock()` and `inCategory()` scopes |
| Low stock notifications | Queued `LowStockNotification` (database + mail), `notifications` table, role-based recipients (admin/manager/staff) and hour-long dedupe; bell dropdown with unread count, mark-as-read and a seeded staff example |
| Order action notifications | `OrderActionNotification` plus `NotifiesOrderActions`; staff purchase-order and sales-order actions notify admins, and admin/manager actions notify staff in the same bell dropdown |
| Sales receipt | `GET /receipts` lists every completed sales order; `GET /sales-orders/{salesOrder}/receipt` renders a view-only receipt with customer, dates, preparer/approver, item lines and total paid. There is no print button or print CSS; buttons are hidden for pending or approved orders |
| Roles | `role` column on `users` with `admin`, `manage-users`, `manage-inventory`, `create-orders`, `fulfil-orders` and `export-reports` Gates; admin manages accounts and approves every sales order, admin/manager manage stock and fulfil orders, staff can draft orders, and plain users are read-only |
| Real-time dashboard | `GET /api/dashboard/stats`, `/api/products/low-stock`, `/api/transactions/recent` with API Resources; `public/js/dashboard.js` polls every 30 seconds and animates value changes |
| Adjustments | `GET/POST /transactions/adjust` calculates the difference between system and physical count and writes an adjustment transaction |
| Product history & reports | Running balance and date filter on the product page; `reports.index` with stock movement, low stock and valuation sections plus CSV export |
| Malformed URL handling | Root `server.php` router plus a `NotFoundHttpException` handler: slash/dot combos such as `//////.......//////` and random elements appended to a link return to the current page instead of a 404 |
| Tests | 29 automated tests covering inventory tracking, purchase/sales orders, admin approval, receipts, notifications and role permissions |

### Running Lab 6

```powershell
# Apply the Lab 6 migrations
C:\xampp2\php\php.exe artisan migrate --force

# Optional repeatable demo transactions + one queued alert
C:\xampp2\php\php.exe artisan db:seed --class=InventoryTransactionSeeder --force

# Add the example low-stock alert to the demo staff bell
C:\xampp2\php\php.exe artisan db:seed --class=StaffNotificationSeeder --force

# Process queued notifications (needed for the bell to populate)
C:\xampp2\php\php.exe artisan queue:work --stop-when-empty

# Run the test suite
C:\xampp2\php\php.exe artisan test
```

Start the app on a free port (port 8000 may already be used by the Lab 5 server):

```powershell
C:\xampp2\php\php.exe artisan serve --host=127.0.0.1 --port=8001
```

Then open <http://127.0.0.1:8001> and sign in with the credentials above.
The admin account can manage users, record movements, apply adjustments and delete audit rows.
Managers can manage the catalog, stock and order fulfilment. Staff can review records and
prepare purchase/sales orders, but cannot receive stock, complete orders, export reports or
manage accounts. Ordinary users have read-only access to inventory, transactions and reports.

### Design decisions

- **Oversell handling:** option A — throw an exception and roll back. Allowing
  negative inventory would corrupt valuation and reorder calculations, while
  capping at zero would silently lose part of the movement.
- **Observer timing:** the observer uses `created` because the audit row already
  exists at that point, so `previous_quantity` and `balance_after` can be
  written back to the same row inside one atomic transaction.
- **Real-time delivery:** AJAX polling was chosen over WebSockets as the lab
  directs. It is simple, needs no extra services, and is sufficient for a
  30-second dashboard refresh; WebSockets would be preferable for large-scale,
  sub-second updates.

## Pushing to GitHub

```powershell
git init
git add .
git commit -m "Lab 5: ByteStock computer parts inventory (Laravel MVC)"
git remote add origin <your-repository-url>
git push -u origin main
```
