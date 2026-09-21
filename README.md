# Laravel Multi-Tenant SaaS Platform

An enterprise-grade, single-database **Multi-Tenant Software-as-a-Service (SaaS) Platform** developed in **Laravel 12** and **PHP 8.2+**. This independent portfolio project demonstrates senior backend development skills: custom tenancy engines, scoped Eloquent queries, quota enforcement services, REST APIs, and automated test coverage.

---

## Architecture Overview

- **Tenancy Model**: Single Database with Scoped Tenancy via `tenant_id` foreign keys and composite indexing.
- **Tenant Isolation**:
  - `TenantContext`: Service container singleton managing the active tenant lifecycle.
  - `TenantScope`: Global Eloquent scope applying `WHERE tenant_id = ?` automatically.
  - `BelongsToTenant`: Trait handling auto-scoping, automatic `tenant_id` assignment on creation, and tenant spoofing defense.
  - `withoutTenancy()` / `withoutGlobalScope(TenantScope::class)`: Safe bypass mechanism for system administration.
- **Tenant Resolution**:
  - `IdentifyTenant` middleware resolving tenant context from authenticated user or explicit `X-Tenant-ID` header.
  - Tenant users are strictly blocked from spoofing context to another tenant.
  - Super admins can explicitly select a tenant context via header.
- **Tenant Health & Status**:
  - `EnsureTenantActive` middleware rejects suspended or canceled tenants with `403 Forbidden`.

---

## Authentication & Role-Based Access Control (RBAC)

The platform provides a complete authentication and authorization system using **Laravel Sanctum** and native Laravel **Policies and Gates**.

### 1. Middleware Pipeline & Execution Order

When a client makes a protected request, the pipeline evaluates in strict sequence:

```
[Incoming Request]
        ↓
1. auth:sanctum        → Authenticates Bearer token and binds authenticated User
        ↓
2. tenant.identify     → Resolves User's assigned tenant into TenantContext singleton.
                         SECURITY: If a tenant user provides an X-Tenant-ID header that 
                         does not match their assigned tenant, execution halts with 403.
        ↓
3. tenant.active       → Verifies tenant operational status (ACTIVE/TRIALING allowed; 
                         SUSPENDED/CANCELED rejected with 403).
                         Platform Super Admins without a tenant context bypass this check.
        ↓
4. Policies / Controller → Granular role and resource authorization checks execute.
```

### 2. Role Definitions & Permission Matrix

| Permission / Action | Super Admin (`super_admin`) | Tenant Owner (`tenant_owner`) | Manager (`manager`) | Staff (`staff`) |
| :--- | :---: | :---: | :---: | :---: |
| **Platform Management** (All Tenants/Plans) | ✅ | ❌ | ❌ | ❌ |
| **Cross-Tenant Access** (via `X-Tenant-ID`) | ✅ | ❌ | ❌ | ❌ |
| **Manage Tenant Profile / Settings** | ✅ | ✅ | ❌ | ❌ |
| **Manage Subscriptions & Billing** | ✅ | ✅ | ❌ | ❌ |
| **Create / Manage Tenant Users** | ✅ (All) | ✅ (In Tenant) | ✅ (Staff only) | ❌ |
| **Assign Roles** | Any role | Owner, Manager, Staff | Staff only | ❌ |
| **View Operational Data** (Customers, Products, Orders) | ✅ | ✅ | ✅ | ✅ |
| **Create / Update Operational Data** | ✅ | ✅ | ✅ | ✅ |
| **Delete Operational Data** | ✅ | ✅ | ✅ | ❌ |

### 3. Policy-Based Authorization

Native Laravel Policies enforce tenant-aware authorization rules:
- **[`UserPolicy`](app/Policies/UserPolicy.php)**: Prevents cross-tenant user access; restricts staff from user management; prevents managers from escalating privileges to owner or super admin.
- **[`CustomerPolicy`](app/Policies/CustomerPolicy.php)**: Ensures only users belonging to the same tenant can view/modify customer records.
- **[`ProductPolicy`](app/Policies/ProductPolicy.php)**: Restricts product catalog management to the owning tenant.
- **[`OrderPolicy`](app/Policies/OrderPolicy.php)**: Enforces tenant-scoped order visibility and restricts order deletion to Tenant Owners.
- **[`TenantPolicy`](app/Policies/TenantPolicy.php)** & **[`SubscriptionPolicy`](app/Policies/SubscriptionPolicy.php)**: Protects tenant organization and billing settings.

### 4. Tenant Security & Anti-Spoofing Rules

1. **No Client-Supplied `tenant_id`**: The backend ignores any `tenant_id` passed in request bodies. The active `TenantContext` solely dictates tenant assignment.
2. **Anti-IDOR Protection**: Eloquent models using `BelongsToTenant` apply `TenantScope` globally. A query like `Customer::find($id)` returns `null` if the ID belongs to another tenant.
3. **Privilege Escalation Prevention**: Form Requests (`CreateTenantUserRequest`, `UpdateTenantUserRequest`) validate that the caller's role is strictly permitted to assign the requested role.

---

## Subscription & Plan Limits

The platform features a configurable, tier-based subscription engine enforced by a dedicated service layer ([`SubscriptionService`](app/Services/Subscription/SubscriptionService.php)) and middleware ([`EnforcePlanLimits`](app/Http/Middleware/EnforcePlanLimits.php)).

### 1. Available Plans & Configurable Quotas

| Plan Tier | Price / Month | Max Users | Max Customers | Max Products | Max Orders / Month | Included Features |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Free** | $0.00 | 1 | 10 | 5 | 20 | Basic analytics |
| **Basic** | $29.00 | 5 | 100 | 50 | 250 | Basic analytics, Email support, API access |
| **Professional** | $79.00 | 25 | 1,000 | 500 | 2,500 | Advanced analytics, Priority support, API access, Custom domain |

*Note on Unlimited Limits*: If any limit in the `plans` table is configured as `null` or `-1`, the engine treats it as unlimited capacity.

### 2. How Usage is Calculated

Usage calculations are tenant-isolated and execute at the database query level:
- **Users**: Number of active users assigned to `tenant_id` (`users.tenant_id = ? AND deleted_at IS NULL`).
- **Customers**: Total customer records for the tenant (`customers.tenant_id = ? AND deleted_at IS NULL`).
- **Products**: Total product catalog entries for the tenant (`products.tenant_id = ? AND deleted_at IS NULL`).
- **Monthly Orders**: Handled on a calendar-month basis (see below).

### 3. Monthly Order Calculation

Monthly orders are measured dynamically against the current calendar month (`Carbon::now()->startOfMonth()` through `Carbon::now()->endOfMonth()`):
- Only orders with `ordered_at` within the active calendar month count toward the limit.
- Orders from previous calendar months are excluded from the current monthly quota.
- Orders with `status = 'canceled'` are deducted from the monthly quota calculation.

### 4. How Enforcement Works

Limit checks are decoupled from controllers and enforced at the backend service/middleware boundary:
1. **Service Verification**: `SubscriptionService::ensureCanCreate(Tenant $tenant, string $resource)` compares real-time usage with the plan threshold.
2. **Exception Handling**: If a tenant has reached or exceeded their plan quota, a `PlanLimitExceededException` is thrown.
3. **HTTP 403 Response**: API and web endpoints automatically render a descriptive payload:
   ```json
   {
       "message": "Product limit reached for your current plan.",
       "resource": "products",
       "current": 50,
       "limit": 50,
       "plan": "Basic"
   }
   ```
4. **Middleware Protection**: Routes guarded by `middleware('plan.limit:products')` prevent any unauthorized creation before the controller action executes.

### 5. Subscription Status Behavior

| Status | Resource Creation | Access Details |
| :--- | :--- | :--- |
| **`ACTIVE`** | **Allowed** | Full operational access within plan quotas. |
| **`TRIALING`** | **Allowed** | Full operational access during trial evaluation. |
| **`PAST_DUE`** | **Blocked** | Read-only access; write operations blocked until billing is resolved (403). |
| **`CANCELED`** | **Blocked** | Account terminated; all resource creation rejected (403). |

---

## API Documentation (Endpoints)

### Authentication Endpoints

#### 1. Login
- **Endpoint**: `POST /api/v1/auth/login`
- **Request Body**:
  ```json
  {
      "email": "owner@apex.test",
      "password": "password"
  }
  ```
- **Response (200 OK)**:
  ```json
  {
      "message": "Login successful.",
      "user": {
          "id": 2,
          "tenant_id": 1,
          "name": "Apex Owner",
          "email": "owner@apex.test",
          "role": "tenant_owner",
          "role_label": "Tenant Owner",
          "is_active": true,
          "tenant": {
              "id": 1,
              "name": "Apex Dynamics",
              "slug": "apex-dynamics"
          }
      },
      "token": "1|abcdef123456...",
      "token_type": "Bearer"
  }
  ```

#### 2. Get Profile (`/me`)
- **Endpoint**: `GET /api/v1/auth/me`
- **Headers**: `Authorization: Bearer <token>`

#### 3. Logout
- **Endpoint**: `POST /api/v1/auth/logout`
- **Headers**: `Authorization: Bearer <token>`
- **Response (200 OK)**: `{"message": "Logged out successfully."}`

### User Management Endpoints (Tenant-Scoped)
All endpoints require `Authorization: Bearer <token>` and execute under `tenant.identify` and `tenant.active`.
- `GET /api/v1/users`: List users in tenant (or all users for Super Admin). Supports pagination.
- `POST /api/v1/users`: Create user in tenant (enforces role hierarchy and plan user quotas).
- `GET /api/v1/users/{id}`: View tenant user.
- `PUT /api/v1/users/{id}`: Update tenant user.
- `DELETE /api/v1/users/{id}`: Remove tenant user.

---

### Customer Management Endpoints (Tenant-Scoped)

Manage customer relationships isolated to the active tenant.
- `GET /api/v1/customers`: List customers with search and pagination.
- `POST /api/v1/customers`: Create customer (enforces plan customer quota).
- `GET /api/v1/customers/{customer}`: Show customer details.
- `PUT /api/v1/customers/{customer}`: Full update of customer.
- `PATCH /api/v1/customers/{customer}`: Partial update of customer.
- `DELETE /api/v1/customers/{customer}`: Soft delete customer (Owners & Managers only).

#### Customer Creation Example:
- **Request (`POST /api/v1/customers`)**:
  ```json
  {
      "name": "Global Logistics Corp",
      "email": "contact@globallogistics.test",
      "phone": "+1-555-0199",
      "company_name": "Global Logistics LLC",
      "address": "450 Industrial Parkway",
      "city": "Atlanta",
      "status": "active"
  }
  ```
- **Response (`201 Created`)**:
  ```json
  {
      "message": "Customer created successfully.",
      "data": {
          "id": 14,
          "name": "Global Logistics Corp",
          "email": "contact@globallogistics.test",
          "phone": "+1-555-0199",
          "company_name": "Global Logistics LLC",
          "address": "450 Industrial Parkway",
          "city": "Atlanta",
          "status": "active",
          "created_at": "2026-09-21T10:15:00.000000Z",
          "updated_at": "2026-09-21T10:15:00.000000Z"
      }
  }
  ```

---

### Product Management Endpoints (Tenant-Scoped)

Catalog management for inventory, pricing, and availability.
- `GET /api/v1/products`: List products with SKU/name search, availability filtering, and pagination.
- `POST /api/v1/products`: Create product (enforces plan product quota and tenant SKU uniqueness).
- `GET /api/v1/products/{product}`: View product details.
- `PUT /api/v1/products/{product}`: Full update of product.
- `PATCH /api/v1/products/{product}`: Partial update of product.
- `DELETE /api/v1/products/{product}`: Soft delete product (Owners & Managers only).

#### Product Creation Example:
- **Request (`POST /api/v1/products`)**:
  ```json
  {
      "sku": "APX-SDN-05",
      "name": "Software Defined Gateway",
      "description": "Enterprise software-defined networking edge node.",
      "price": 149.00,
      "cost_price": 45.00,
      "stock": 20,
      "is_available": true
  }
  ```
- **Response (`201 Created`)**:
  ```json
  {
      "message": "Product created successfully.",
      "data": {
          "id": 8,
          "sku": "APX-SDN-05",
          "name": "Software Defined Gateway",
          "description": "Enterprise software-defined networking edge node.",
          "price": 149,
          "cost_price": 45,
          "stock": 20,
          "is_available": true,
          "created_at": "2026-09-21T10:20:00.000000Z",
          "updated_at": "2026-09-21T10:20:00.000000Z"
      }
  }
  ```

---

### Order Management & OrderService Architecture

Order placement involves financial calculations, inventory stock decrements, historical price snapshots, and multi-step transaction integrity handled by [`OrderService`](app/Services/Order/OrderService.php).

#### Endpoints:
- `GET /api/v1/orders`: List tenant orders with eager loading (`customer`, `items.product`), status filtering, customer filtering, order number search, date range filtering, and pagination.
- `POST /api/v1/orders`: Create new order via `OrderService`.
- `GET /api/v1/orders/{order}`: View order with customer and line items.
- `PUT /api/v1/orders/{order}`: Update order notes or transition status.
- `PATCH /api/v1/orders/{order}`: Partial update order status or notes.
- `DELETE /api/v1/orders/{order}`: Soft delete order (Tenant Owners only).

#### Order Creation Payload Structure:
Clients supply only operational identifiers and quantities. Financial amounts, taxes, discounts, and order numbers are **never** accepted from client requests.

- **Request (`POST /api/v1/orders`)**:
  ```json
  {
      "customer_id": 1,
      "notes": "Express deployment requested.",
      "items": [
          {
              "product_id": 1,
              "quantity": 2
          },
          {
              "product_id": 2,
              "quantity": 1
          }
      ]
  }
  ```

- **Response (`201 Created`)**:
  ```json
  {
      "message": "Order created successfully.",
      "data": {
          "id": 5,
          "order_number": "ORD-APEX-202609-0003",
          "status": "pending",
          "status_label": "Pending Payment",
          "subtotal": 117,
          "tax_amount": 9.36,
          "discount_amount": 0,
          "total_amount": 126.36,
          "notes": "Express deployment requested.",
          "ordered_at": "2026-09-21T10:30:00.000000Z",
          "customer": {
              "id": 1,
              "name": "Acme Corp",
              "email": "acme@client-example.com"
          },
          "items": [
              {
                  "id": 9,
                  "product_id": 1,
                  "product_name": "Cloud Workspace License",
                  "unit_price": 49,
                  "quantity": 2,
                  "subtotal": 98
              },
              {
                  "id": 10,
                  "product_id": 2,
                  "product_name": "Security Shield Addon",
                  "unit_price": 19,
                  "quantity": 1,
                  "subtotal": 19
              }
          ],
          "created_at": "2026-09-21T10:30:00.000000Z",
          "updated_at": "2026-09-21T10:30:00.000000Z"
      }
  }
  ```

---

## Order Business Logic & Transaction Guarantees

### 1. Order Total Calculation Logic

To prevent floating-point inaccuracies and ensure deterministic billing, calculations occur server-side using fixed-precision rules:

1. **Line Item Total**:
   $$\text{line\_total} = \text{unit\_price} \times \text{quantity}$$
   The `unit_price` is fetched directly from the database catalog at the moment of order creation and snapshotted onto the `order_items` table.
2. **Order Subtotal**:
   $$\text{subtotal} = \sum (\text{line\_total})$$
3. **Taxes & Surcharges**:
   Standard 8% tax rate applied to subtotal:
   $$\text{tax\_amount} = \text{round}(\text{subtotal} \times 0.08, 2)$$
4. **Final Order Total**:
   $$\text{total\_amount} = \text{round}(\text{subtotal} + \text{tax\_amount} - \text{discount\_amount}, 2)$$

### 2. Tenant-Safe Order Number Generation

Order numbers follow a deterministic, human-readable, tenant-prefixed format:
`ORD-{TENANT_PREFIX}-{YYYYMM}-{SEQUENCE}` (e.g., `ORD-APEX-202609-0001`).

- Generated server-side inside `OrderService::generateOrderNumber()`.
- Guaranteed uniqueness via composite database constraint `unique(['tenant_id', 'order_number'])`.
- Concurrency collision protection via loop assertion before insert.

### 3. Database Transaction & Atomic Rollbacks

All mutations during order creation execute inside `DB::transaction(...)`:
1. Customer existence and tenant ownership verified.
2. Every item's product availability and inventory stock verified against requested cumulative quantities.
3. Monthly subscription order quota verified.
4. Order record created with status `pending`.
5. Order line items created with immutable price snapshots.
6. Inventory stock decremented atomically for each product.

**Rollback Behavior**:
If any product has insufficient stock or belongs to another tenant, the transaction throws a `ValidationException` (HTTP 422). The database automatically rolls back: no order is created, no order items are persisted, and previous inventory stocks remain untouched.

### 4. Order Status Transition Rules

Order statuses follow the `OrderStatus` PHP Enum:
`DRAFT` $\rightarrow$ `PENDING` $\rightarrow$ `PROCESSING` $\rightarrow$ `COMPLETED` / `CANCELED`.

- **Terminal States**: Orders in `COMPLETED` or `CANCELED` status cannot be transitioned to any other status.
- **Role Restrictions**:
  - Staff members can transition orders between operational states (`PENDING` $\rightarrow$ `PROCESSING`).
  - Marking an order as `COMPLETED` or `CANCELED` requires **Manager**, **Tenant Owner**, or **Super Admin** privileges.
- **Inventory Restoration on Cancellation**:
  When an order transitions to `CANCELED`, `OrderService` automatically restores the inventory stock for all associated line items.

### 5. Pagination, Filtering, and N+1 Prevention

- **Safe Pagination**: Supported on `/customers`, `/products`, `/orders`, and `/users` via `?page=1&per_page=15` (enforcing a minimum of 1 and capped at a maximum of 100).
- **Tenant-Scoped Search**:
  - Customers: Filter by `search` (matches `name`, `email`, or `phone`).
  - Products: Filter by `search` (matches `name` or `sku`) and `is_available` boolean.
  - Orders: Filter by `status`, `customer_id`, `order_number`, and date range (`from_date`, `to_date`).
- **N+1 Query Prevention**: Orders index eagerly loads relations (`Order::with(['customer', 'items'])`), fetching complete orders with relational data in 3 indexed queries regardless of page size.

---

## Local Development & Testing

### Requirements
- PHP 8.2+ (with PDO, SQLite, MySQL extensions)
- Composer 2.x
- Node.js 20+ & NPM

### Setup Instructions

```bash
# 1. Clone repository and install dependencies
composer install

# 2. Environment file setup
cp .env.example .env
php artisan key:generate

# 3. Run fresh migrations with realistic demo seeders
php artisan migrate:fresh --seed

# 4. Run automated test suite
php artisan test
```

### Seeded Demo Accounts (Local Development)

All seed credentials use the standard local password: `password`

| Context | Account Email | Role | Plan Tier | Notes |
| :--- | :--- | :--- | :--- | :--- |
| **System** | `admin@saas-platform.test` | Super Admin | System Admin | Global platform manager (`tenant_id: null`) |
| **Tenant 1** | `owner@apex.test` | Tenant Owner | Basic | Apex Dynamics owner |
| **Tenant 1** | `manager@apex.test` | Manager | Basic | Apex Dynamics manager |
| **Tenant 1** | `staff@apex.test` | Staff | Basic | Apex Dynamics staff |
| **Tenant 2** | `owner@bluewave.test` | Tenant Owner | Free | BlueWave Logistics owner |
| **Tenant 2** | `staff@bluewave.test` | Staff | Free | BlueWave Logistics staff |
