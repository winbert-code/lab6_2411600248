# ByteStock — Computer Parts Inventory Management System

Laravel MVC implementation of Laboratory Exercise 5, using the suggested theme
**computer parts shop** instead of the generic hardware-store example.

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

## Features mapped to the lab exercise

| Lab part | Implementation |
| --- | --- |
| Environment & DB config | Laravel 13 project, MySQL `.env`, migrations |
| Authentication | Laravel `Auth` facade with custom login/register Blade views, hashed passwords, `remember me`, throttle on POST routes, guest/auth middleware |
| Users & roles | `UserController` and `users/*` views: add users as admin, manager, staff or user, edit roles and passwords, deactivate/restore accounts, and filter by role/status; inactive users cannot log in and you cannot deactivate yourself |
| Models & migrations | `User`, `Brand`, `Category`, `Product`, `InventoryTransaction`. Products belong to a brand and a category via foreign keys; unique SKU, casts, `active`/`inactive` status scopes, `lowStock`/`outOfStock` scopes, `stockStatus()` |
| Brands & categories | First-class `brands` and `categories` tables with their own pages under **Brands** and **Categories**; removing an item marks it inactive (never deletes it), and the restore button makes it available for stock again; inactive items are hidden from new-product dropdowns but kept for products already using them |
| CRUD controller | `app\Http\Controllers\ProductController.php` (resource controller with implicit route binding) |
| Validation | `StoreProductRequest` / `UpdateProductRequest` with custom messages, SKU uniqueness on create/update, normalized uppercase SKU, active brand/category validation |
| Navigation | Dashboard, Inventory (stock overview), Products (CRUD + deactivate/restore), Categories (CRUD + deactivate/restore), Brands (CRUD + deactivate/restore), Users (roles + status), Transactions, Reports |
| Blade views | `layouts/app`, `layouts/guest`, dashboard, inventory, product index/create/edit/show, category/brand/user CRUD views, reusable `components/stock-badge`, shared form partials |
| Routes | `routes/web.php`: guest auth routes + auth-protected dashboard, inventory, resource routes for products, categories, brands and users, plus transactions, reports and notifications |
| Dashboard with real data | `DashboardController` computes product count, low/out-of-stock counts, total inventory value, recent products, category totals, and chart data |
| Seeder | `ProductSeeder` inserts 20 realistic computer parts across 10 categories, including low-stock and out-of-stock examples |
| Theme integration | `public/css/app.css` CSS variables override Bootstrap defaults; same style on auth and app pages |
| Stock audit trail | `inventory_transactions` table logs initial stock and quantity adjustments, shown on the product detail page |
| Inactive products | The delete action sets `status` to `inactive` instead of removing rows; products and inventory history stay in the database and can be restored |
| Testing | Verified login, dashboard statistics, inventory overview, product/category/brand CRUD, inactive brand/category restore, user roles, inactive-user login rejection, transaction logging, reports and deactivate/restore in a browser |

## Important files

```text
app/Models/User.php
app/Models/Product.php
app/Models/Brand.php
app/Models/Category.php
app/Models/InventoryTransaction.php
app/Http/Controllers/ProductController.php
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
database/seeders/UserSeeder.php
resources/views/layouts/app.blade.php
resources/views/layouts/guest.blade.php
resources/views/dashboard/index.blade.php
resources/views/inventory/*.blade.php
resources/views/products/*.blade.php
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

### What was added

| Lab 6 part | Implementation |
| --- | --- |
| Transaction model & schema | `InventoryTransaction` with `in`, `out` and `adjustment` types, reference, reason, notes, `previous_quantity`, `balance_after`, indexes and audit scopes |
| Automatic stock updates | `App\Observers\InventoryTransactionObserver` registered with `#[ObservedBy]`; locks the product row, updates quantity, records running balance and rolls back on failure |
| Stock integrity | `DB::transaction()` plus `lockForUpdate()`; `InsufficientStockException` when a stock-out would exceed available stock; validation `after` hook in `StoreInventoryTransactionRequest` |
| Controller & views | `InventoryTransactionController` (index/create/store/show/destroy/adjust) with filtering, pagination, eager loading, admin-only delete and Blade views under `resources/views/transactions/` |
| Product model helpers | `latestTransaction()`, `isLowStock()`, `isOutOfStock()`, `stock status/color` accessors, `lowStock()`, `outOfStock()` and `inCategory()` scopes |
| Low stock notifications | Queued `LowStockNotification` (database + mail), `notifications` table, role-based recipients (admin/manager) and hour-long dedupe; bell dropdown with unread count and mark-as-read |
| Roles | `role` column on `users`, `admin` and `manage-inventory` Gates; the first seeded account is promoted to admin |
| Real-time dashboard | `GET /api/dashboard/stats`, `/api/products/low-stock`, `/api/transactions/recent` with API Resources; `public/js/dashboard.js` polls every 30 seconds and animates value changes |
| Adjustments | `GET/POST /transactions/adjust` calculates the difference between system and physical count and writes an adjustment transaction |
| Product history & reports | Running balance and date filter on the product page; `reports.index` with stock movement, low stock and valuation sections plus CSV export |
| Tests | `tests/Feature/InventoryTrackingTest.php` — 10 inventory-specific tests (12 total with the skeleton tests) |

### Running Lab 6

```powershell
# Apply the Lab 6 migrations
C:\xampp2\php\php.exe artisan migrate --force

# Optional repeatable demo transactions + one queued alert
C:\xampp2\php\php.exe artisan db:seed --class=InventoryTransactionSeeder --force

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
The admin account can record movements, apply adjustments and delete audit rows;
ordinary users can browse the inventory, transactions and reports.

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
