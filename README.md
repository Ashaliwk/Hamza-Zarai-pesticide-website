# CropCare — Pesticide & Fertilizer Inventory Management

A complete PHP 8 + MySQL admin panel for managing pesticide, fertilizer, urea, spray,
seed and other agri-products — with full CRUD, inventory tracking, and
automatic profit/loss margin calculation. Built with Bootstrap 5.

## Features

- 🔐 Simple admin login (session-based auth)
- 📦 **Products** — full CRUD, category, unit, purchase/selling price, quantity, low-stock threshold
- 🏷️ **Categories** — predefined defaults (Urea, Fertilizers, Sprays, Seeds, Insecticides, Pesticides) + add your own custom categories
- 💰 **Sales** — record sales, auto-deducts stock, computes totals
- 🧾 **Purchases** — record purchases, auto-increases stock, updates cost price
- 📊 **Dashboard** — total products, total sales, total profit, low-stock alerts, top-products chart, recent sales feed
- 📈 Profit margin auto-calculated per product: `(selling - purchase) / purchase × 100`
- 🎨 Clean dark-green sidebar admin UI, fully responsive (Bootstrap 5)

## Requirements

- PHP 8.0+ with PDO MySQL extension
- MySQL 5.7+ / MariaDB 10.3+
- Any local server stack: XAMPP, WAMP, MAMP, Laragon, or `php -S`

## Setup

1. **Create the database.** Import the schema (creates the DB, tables and demo
   product/category/sales/purchase data):

   ```bash
   mysql -u root -p < database/schema.sql
   ```

   (or open phpMyAdmin → Import → select `database/schema.sql`)

2. **Configure the connection.** Edit `config/database.php` if your MySQL
   username/password differ from the defaults (`root` / empty password):

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'pesticide_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. **Run it.**

   - **XAMPP/WAMP:** copy this folder into `htdocs/pesticide-admin`, then visit
     `http://localhost/pesticide-admin/`
   - **PHP built-in server:** from this folder run:
     ```bash
     php -S localhost:8000
     ```
     then visit `http://localhost:8000/`

4. **Log in.** The first time the app runs it automatically creates a default
   admin account:

   - Email: `admin@pesticide.com`
   - Password: `admin123`

   You can change this later directly in the `admins` table (or ask me to add
   a "change password" screen).

## Project structure

```
pesticide-admin/
├── config/database.php        # DB connection + auto-seed admin
├── includes/                  # auth guard, header/footer, form partials
├── assets/css/style.css       # theme
├── database/schema.sql        # tables + demo data
├── login.php / logout.php / index.php
├── dashboard.php
├── products.php + products_action.php
├── categories.php + categories_action.php
├── sales.php + sales_action.php
└── purchases.php + purchases_action.php
```

## Notes

- All money values are shown in Rs (Pakistani Rupee) — change the `money()`
  helper in `includes/auth.php` if you'd like a different currency symbol.
- Deleting a sale/purchase automatically restores/adjusts stock quantity.
- A category can't be deleted while products are still assigned to it.
- Want more? I can add: multi-user roles, PDF/Excel export, low-stock email
  alerts, barcode/SKU scanning, or a REST API layer — just ask.
