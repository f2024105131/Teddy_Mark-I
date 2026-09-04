# Laundry Management System

A web-based Laundry Management System that replaces manual, WhatsApp/phone-based
order tracking with a centralized platform for Customers, Staff, and Admin.

Built with **Plain PHP (custom MVC)** and **MySQL**.

---

## Tech Stack

- **Backend:** PHP 8.1+ (no framework — hand-rolled Router, Controller, Model)
- **Database:** MySQL 8.0+
- **Architecture:** MVC with a Service layer for business logic
- **Autoloading:** Composer (PSR-4)

---

## Project Status

This project is under active development. Current state:

| Layer | Status |
|---|---|
| Database schema (28 tables) |  Complete — `database/schema.sql` |
| Core connection layer (PDO, Auth, Model, Router) |  Complete |
| Order status state machine (`OrderStatusService`) |  Complete |
| Customer login flow |  Working end-to-end |
| Remaining controllers/views (~69 pages total) |  In progress |
| Billing, Notification, Refund services | Not started |

---

## Roles

| Role | Access |
|---|---|
| **Customer** | Book pickups, track orders, pay bills, submit complaints |
| **Staff** | Create orders, update order status, verify payments, manage deliveries |
| **Admin** | Full system access — pricing, staff, reports, audit logs, settings |

---

## Requirements

- PHP >= 8.1
- MySQL >= 8.0
- Composer
- Apache/Nginx with `mod_rewrite` enabled

---

## Setup Instructions

### 1. Clone and install dependencies
```bash
git clone <repo-url>
cd laundry-management-system
composer install
```

### 2. Configure environment
```bash
cp .env.example .env
```
Edit `.env` with your database credentials:


### 3. Create the database
```bash
mysql -u root -p < database/create_app_user.sql   # creates a restricted app-level DB user
mysql -u root -p laundry_management < database/schema.sql
mysql -u root -p laundry_management < database/seed.sql
```

### 4. Point your web server at `public/`
The document root must be `public/` — never the project root. This keeps
`app/`, `database/`, and `.env` outside what a browser can directly access.

### 5. Log in
Default admin account (created by `seed.sql`):

**Change this password immediately after first login.**

---

## Project Structure
laundry-management-system/
├── public/ # Web root — index.php, assets
├── app/
│ ├── Config/ # Database and app configuration
│ ├── Core/ # Router, Model, Database, Auth (the "framework")
│ ├── Middleware/ # RBAC checks
│ ├── Services/ # Business logic (OrderStatusService, BillingService, ...)
│ ├── Controllers/ # Customer/, Staff/, Admin/, Api/
│ ├── Models/ # One class per database table
│ └── Views/ # One file per page/screen
├── database/
│ ├── schema.sql # Full table structure
│ ├── create_app_user.sql # Restricted MySQL user for the app
│ └── seed.sql # Default data (services, slots, admin login)
├── routes/
│ └── web.php # URL → Controller mapping
└── storage/
└── uploads/payment_proofs/ # Outside public/ — served via authenticated route


---

## Database

28 tables covering: customer & staff accounts, item/service catalog, pickup &
delivery scheduling, orders & order items, billing & payments (with proof
uploads), complaints & refunds, receipts & invoices, notifications, staff
performance tracking, audit logs, and system configuration.

Full schema definition: [`database/schema.sql`](database/schema.sql)

---

## Order Status Lifecycle

Enforced server-side by `App\Services\OrderStatusService` — no status can
skip a stage, and item edits are automatically locked once an order enters
**Washing**:

pickup_requested → pickup_assigned → picked_up → received_at_laundry
→ washing → ironing → ready → out_for_delivery → delivered

(`cancelled` is reachable from any stage before `washing` starts.)

---

## Security Notes

- App connects to MySQL via a restricted user (`laundry_app`) with no
  schema-altering privileges — see `database/create_app_user.sql`
- Payment proof screenshots are stored outside `public/` and served only
  through an authenticated controller route
- All queries use PDO prepared statements — no raw string-interpolated SQL
- Passwords hashed with `password_hash()` (bcrypt)

---

## Contributing (Team Workflow)

1. Create a branch per feature: `git checkout -b feature/staff-order-creation`
2. Follow the existing folder conventions — one Controller method per page,
   one Model per table, shared logic goes in `Services/`
3. Never write raw SQL in a Controller — always go through a Model or Service
4. Open a PR referencing the relevant User Story ID (e.g. `US-047`)

---

## License

This is proprietary software developed for AMTN. All rights reserved.
Unauthorized copying, distribution, or use of this code is prohibited.