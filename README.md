# Mini ERP — Full Stack Enterprise Resource Planning System

A portfolio and learning project built with **Laravel (PHP)**, **Vue 3**, **MariaDB/MySQL**, and a **Node.js/Express** notification microservice. It demonstrates a complete product-to-purchase-to-sale workflow, stock audit trails, authentication, and role-based access control.

## Project architecture

```text
Vue 3 + Vite (localhost:5173)
       |
       | Axios + Vite proxy, session cookies / CSRF
       v
Laravel REST API (localhost:8000)
       |                         |
       v                         v
MariaDB / MySQL          Node.js + Express (127.0.0.1:3001)
Products, orders,        Low-stock notifications
stock movements          (service-token protected)
```

The application is organized locally into three folders, suitable for three GitHub repositories:

| Application | Local folder | Suggested repository |
| --- | --- | --- |
| Laravel backend | `E:\xampp\htdocs\mini-erp` | `mini-erp-backend` |
| Vue frontend | `E:\xampp\htdocs\mini-erp-frontend` | `mini-erp-frontend` |
| Node notifications | `E:\xampp\htdocs\mini-erp-notifications` | `mini-erp-notifications` |

## Technologies

- **Backend:** PHP 8+, Laravel, Eloquent ORM, Form Requests, migrations, database transactions, Laravel Sanctum SPA authentication
- **Frontend:** Vue 3 Composition API, Vite, Axios, HTML, CSS
- **Database:** MariaDB / MySQL (developed locally with XAMPP)
- **Microservice:** Node.js, Express, dotenv
- **Development tools:** Composer, npm, PowerShell, Postman, Git and GitHub

## Features implemented (Phases 1–5)

### Phase 1 — Product management API
- Create, list, view, edit and delete products
- Unique SKU, selling price, cost price, reorder level and active status
- Search by product name or SKU; server-side pagination
- Laravel validation and JSON responses

### Phase 2 — Vue product management
- Product table with search and pagination
- Reusable product create/edit form and validation messages
- Delete confirmation and loading/success/error states

### Phase 3 — Notification microservice
- Express health endpoint and service-token authentication
- Low-stock notifications when stock is at or below the reorder level
- Laravel-to-Node integration and Vue alert panel
- Notification synchronization after stock changes

### Phase 4 — ERP business workflows and reporting
- Suppliers and purchase orders with **draft → received** workflow
- Sales orders with **draft → confirmed** workflow
- Receiving purchases increases stock; confirming sales decreases stock
- Stock movement records (`IN`/`OUT`) with before/after quantities and order references
- Atomic database transactions, row-level locking, duplicate-action prevention and insufficient-stock rejection
- Inventory summary, movement filters and dashboard statistics
- Vue purchase/sales order forms integrated with Laravel APIs

### Phase 5 — Authentication and permissions
- Laravel Sanctum cookie-based SPA authentication, CSRF protection, login/logout and current-user endpoint
- Admin and staff roles with server-enforced access restrictions
- Vue login screen, session restoration and role-aware product controls
- Authentication and authorization feature tests

## How the application works — end-to-end workflow

### 1. Start the application and sign in

Start **MariaDB (XAMPP)**, the **Laravel API**, the **Node.js notification service**, and the **Vue/Vite frontend**. Open `http://localhost:5173`. Vue displays the login page if there is no authenticated session. On login, Vue obtains a CSRF cookie, sends the email and password to Laravel (`POST /login`), and Laravel verifies the password against the hashed value in the `users` table. Laravel establishes a session and returns the user's role. Vue then loads the dashboard. On refresh, Vue calls `GET /api/auth/me` to restore the session; on logout, Laravel invalidates it.

**Authorization:** An **admin** can manage product records; **staff** can view products and use purchase and sales workflows. Laravel checks the role on protected API routes, so manually sending a forbidden request cannot bypass hidden Vue buttons.

### 2. Manage the product catalog

An admin creates a product with a unique SKU, selling price, cost price, reorder level, and active/inactive status. Vue sends the form to Laravel, which validates the input and stores the record in MariaDB. The product list supports search and server-side pagination. **After product creation, stock starts at zero** in the inventory-controlled workflow; subsequent stock changes come from receiving purchases or confirming sales, not from directly editing the product form.

### 3. Purchase workflow — stock comes IN

1. Open **Purchases** and create a purchase order for a supplier, date, and one or more products with quantities and unit costs.
2. Laravel saves the purchase order and its items with **`draft`** status. **Draft orders do not affect stock.**
3. Click **Receive**. Laravel begins a database transaction, locks the order and affected product rows, checks that the order is still a draft, and increases each product's stock.
4. Laravel writes an **`IN` stock movement** for every received item, recording quantity, stock before/after, and the purchase order reference; it then marks the order **`received`** and commits.
5. After the commit, Laravel sends the updated stock balances to Node.js to synchronize low-stock alerts. Receiving the same order again is rejected with HTTP **422**.

### 4. Sales workflow — stock goes OUT

1. Open **Sales** and create a sales order with customer name, date, products, quantities, and selling prices.
2. Laravel saves it as **`draft`**. **Draft sales do not deduct stock.**
3. Click **Confirm**. Inside a database transaction, Laravel locks the order and products, verifies the order is still a draft, and checks sufficient stock.
4. If stock is sufficient, Laravel deducts the quantities, writes **`OUT` stock movements** with before/after balances and the sales order reference, marks the order **`confirmed`**, and commits.
5. If stock is insufficient, Laravel responds with HTTP **422** and the transaction leaves stock unchanged. Confirming an already-confirmed order is also rejected. After a successful commit, Laravel synchronizes alerts with Node.js.

### 5. Inventory dashboard and movement history

The **Dashboard** requests `GET /api/inventory/summary` and `GET /api/inventory/movements` from Laravel. Laravel queries MariaDB to return product counts, available stock units, low-stock product counts, purchase/sales order counts, and the stock movement audit trail. The Products tab displays current balances; the movement history explains **why** those balances changed.

### 6. Low-stock notification workflow

Whenever a product is created/updated or its stock changes through a completed purchase or sale, Laravel's `NotificationService` sends the latest stock and reorder level to the protected Node.js endpoint `POST /notifications/low-stock`, using the private `x-service-token` header. Node.js maintains **one active alert per product** when `stock_quantity <= reorder_level`, and clears the alert when stock rises above the threshold. Vue reads alerts through Laravel's `GET /api/notifications` endpoint, so the browser does not need the private Node.js service token. Notification failures are logged without rolling back an already-committed purchase or sale. Alerts are currently held in Node.js memory and are cleared by a service restart.

### Example: one product through purchase and sales

Assume **Dell Laptop Pro** has an initial stock of **3** and reorder level **5**:

| Business action | Stock change | Resulting stock | Movement / alert |
| --- | ---: | ---: | --- |
| Starting balance | — | 3 | Low-stock alert active |
| Receive purchase order for 10 | +10 | 13 | `IN` movement; alert resolved |
| Confirm sales order for 1 | -1 | 12 | `OUT` movement; no alert |
| Confirm sales order for 4 | -4 | 8 | `OUT` movement; no alert |
| Attempt sale of 9 | Rejected | 8 | HTTP 422; no stock movement |

The demonstrated completed transactions yield **8 units** and **three stock movement records**. A new sale that takes the balance to **5 or below** activates a low-stock alert again.

### Request and data flow

```text
Employee (Admin / Staff)
        |
        v
Vue 3 UI (login, products, purchases, sales, dashboard)
        |  Axios, session cookie, CSRF
        v
Laravel API (validation + authentication + role checks)
        |
        +--> MariaDB: users, products, suppliers,
        |             purchase_orders/items, sales_orders/items,
        |             stock_movements
        |
        +--> DB transaction + row locks for receiving/confirming
        |       |
        |       +--> update stock + write IN/OUT movement + commit
        |
        +--> NotificationService (after commit)
                |
                v
        Node.js / Express (private service token)
                |
                +--> active low-stock alerts (in memory)

Vue dashboard <-- Laravel inventory reporting <-- MariaDB
Vue alerts    <-- Laravel notifications API  <-- Node.js
```

**Key business rule:** The order status transition, stock update, and stock movement record happen together in one transaction. This protects inventory consistency and prevents duplicate receiving, duplicate confirmation, and overselling.

## Role permissions

| Action | Admin | Staff |
| --- | --- | --- |
| View dashboard, products and inventory | Yes | Yes |
| Create, edit or delete products | Yes | No |
| Create and receive purchase orders | Yes | Yes |
| Create and confirm sales orders | Yes | Yes |
| View low-stock alerts | Yes | Yes |

**Local demo accounts:** `admin@example.test` (Admin) and `staff@example.test` (Staff), if created using the example setup. **Passwords are deliberately not published here.** Set them locally through Laravel and never commit them to GitHub.

## Requirements

- PHP 8+ with required Laravel extensions and Composer
- MariaDB or MySQL database server
- Node.js and npm
- Git (for cloning and publishing)

## Local installation

### 1. Backend (Laravel)

```powershell
cd E:\xampp\htdocs\mini-erp
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Create a MariaDB/MySQL database called `mini_erp`. Configure `.env` with your **own local** database settings and the following development values:

```dotenv
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_DATABASE=mini_erp
DB_USERNAME=YOUR_DB_USER
DB_PASSWORD=YOUR_DB_PASSWORD
SESSION_DRIVER=database
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
NOTIFICATION_SERVICE_URL=http://127.0.0.1:3001
NOTIFICATION_SERVICE_TOKEN=YOUR_PRIVATE_SHARED_SERVICE_TOKEN
```

Set `DB_HOST` and `DB_PORT` as required by your local MariaDB setup. Ensure the database and session migrations exist, then run:

```powershell
php artisan migrate
php artisan optimize:clear
php artisan serve --host=localhost --port=8000
```

Create admin and staff users locally with Laravel Tinker or a private seeder. Assign `role` values `admin` and `staff`; use unique, strong passwords for any deployment.

### 2. Notification service (Node.js)

```powershell
cd E:\xampp\htdocs\mini-erp-notifications
npm install
```

Create `.env` (do not commit it):

```dotenv
PORT=3001
SERVICE_TOKEN=YOUR_PRIVATE_SHARED_SERVICE_TOKEN
```

Use the **same** service token as Laravel's `NOTIFICATION_SERVICE_TOKEN`.

```powershell
node server.js
```

Check `http://127.0.0.1:3001/health`.

### 3. Frontend (Vue 3)

```powershell
cd E:\xampp\htdocs\mini-erp-frontend
npm install
npm run dev
```

Open **http://localhost:5173**. Use `localhost` consistently for Laravel and Vue so session cookies work. The Vite proxy should forward `/api`, `/sanctum`, `/login` and `/logout` to `http://localhost:8000`. Axios should use `withCredentials: true` and `withXSRFToken: true`.

## Main API endpoints

Authenticated ERP endpoints are under `/api`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/login` | Log in (session route) |
| POST | `/logout` | Log out (session route) |
| GET | `/api/auth/me` | Current authenticated user |
| GET / POST | `/api/products` | List / create products |
| GET / PUT / PATCH / DELETE | `/api/products/{product}` | Product detail / update / delete |
| GET | `/api/suppliers` | Supplier lookup |
| GET / POST | `/api/purchase-orders` | List / create purchase orders |
| GET | `/api/purchase-orders/{purchaseOrder}` | Purchase details |
| POST | `/api/purchase-orders/{purchaseOrder}/receive` | Receive purchase and increase stock |
| GET / POST | `/api/sales-orders` | List / create sales orders |
| GET | `/api/sales-orders/{salesOrder}` | Sales details |
| POST | `/api/sales-orders/{salesOrder}/confirm` | Confirm sale and decrease stock |
| GET | `/api/inventory/summary` | Inventory and order totals |
| GET | `/api/inventory/movements` | Stock movement history and filters |
| GET | `/api/notifications` | Low-stock notifications via Laravel |

Node.js service endpoints: `GET /health`, `POST /notifications/low-stock`, and `GET /notifications`. The notification endpoints require the private `x-service-token` header.

## Verification and testing

Completed local checks during development include:

- Product CRUD, search, pagination and validation
- Purchase receipt increases stock and cannot be received twice
- Sales confirmation reduces stock and cannot be confirmed twice
- Insufficient stock rejects a sale without modifying inventory
- Stock movement `IN` and `OUT` records agree with inventory balances
- Inventory summary and movement API results verified against SQL
- Admin and staff dashboards render; product edit controls are role-aware

Run Laravel feature tests against a **separate test database**:

```powershell
cd E:\xampp\htdocs\mini-erp
php artisan test --filter=AuthenticationTest
```

**Testing note:** The last shared automated authentication run reported **8 passed and 3 failed** because `/login` and `/logout` returned 404. Route configuration guidance was provided afterward, but a passing rerun has not been shown. Do not claim all automated tests pass until rerun and verified.

## GitHub publishing and security

1. Keep `.env`, service tokens, database passwords, database exports, local credentials, `vendor/`, `node_modules/`, and `dist/` out of Git.
2. Commit safe `.env.example` templates with placeholders only.
3. Review `git status` and `git diff --cached` before every push.
4. Do not publish PDFs or screenshots that disclose passwords or secrets.
5. Change local demo passwords before deployment; use HTTPS and secure session cookies in production.

Example for each project directory after creating its empty GitHub repository:

```powershell
git init
git branch -M main
git add .
git status
git commit -m "Initial commit: Mini ERP"
git remote add origin https://github.com/YOUR_USERNAME/REPOSITORY_NAME.git
git push -u origin main
```

Replace `YOUR_USERNAME` and `REPOSITORY_NAME` with actual values. If a remote already exists, inspect `git remote -v` instead of adding it again.

## Known limitations and next improvements

- Node.js alerts currently use in-memory storage; restart clears alerts. Persistent storage and reliable event delivery are future improvements.
- This is a local educational/portfolio implementation, not a production-hardened ERP.
- Future work: audit logging, stronger authorization policies, automated integration tests, deployment, and CI/CD.

## License

No license has been selected yet. Add a `LICENSE` file if you want to specify reuse permissions.
