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
All endpoints require `Authorization: Bearer <token>`.
- `GET /api/v1/users`: List users in tenant (or all users for Super Admin)
- `POST /api/v1/users`: Create user in tenant (enforces role rules and plan user quotas)
- `GET /api/v1/users/{id}`: View tenant user
- `PUT /api/v1/users/{id}`: Update tenant user
- `DELETE /api/v1/users/{id}`: Remove tenant user

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
