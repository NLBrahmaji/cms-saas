# Development Guide

## General Rule

Build the system incrementally through working vertical slices.

Avoid implementing large speculative frameworks before they are required.

---

## Application Boundaries

### Platform

Public SaaS marketing application.

### Dashboard

Account and SaaS management application.

### Website

Customer website rendering and owner editing experience.

### Laravel

Authoritative backend/domain application.

---

## Local Development Ports

- Platform: 3000
- Dashboard: 3001
- Website: 3002
- Laravel API: 8000
- PostgreSQL: 5432

The Next.js scripts do not pin ports. Start each frontend with its documented port,
for example `npm run dev -- --port 3001` for Dashboard and `--port 3002` for Website.

## Authentication API

Laravel API routes remain in `backend/api/routes/api.php`, with the automatic URL
prefix disabled in `bootstrap/app.php`. Backend endpoints use `/auth/...` and
`/accounts/...` including nested website endpoints; do not add
an `/api` URL prefix.

Current routes:

- `POST /auth/register` — atomically creates a user, initial account, active owner membership, and account-scoped owner role; returns 201 without logging in.
- `POST /auth/login` — authenticates with the web session guard and rotates the session ID.
- `GET /auth/me` — returns the session's authenticated user, or JSON 401 for a guest.
- `POST /auth/logout` — logs out, invalidates the session, and regenerates the CSRF token.

First-party frontends use Sanctum session cookies, not bearer tokens:

1. Request `GET /sanctum/csrf-cookie` from the Laravel backend with credentials enabled.
2. Send registration/login requests with credentials and the URL-decoded `XSRF-TOKEN`
   cookie value in the `X-XSRF-TOKEN` header. Send `Accept: application/json`.
3. After registration, log in separately. Login establishes the authenticated session.
4. Send credentials with `/auth/me` and other authenticated requests.
5. Send the CSRF header with logout. Subsequent `/auth/me` requests return 401.

For Axios, enable `withCredentials` and `withXSRFToken`. With fetch, use
`credentials: 'include'` and explicitly populate the CSRF header on write requests.
Browsers supply the Origin/Referer required by Sanctum's stateful detection.

`SANCTUM_STATEFUL_DOMAINS` contains comma-separated hosts and ports without schemes.
`CORS_ALLOWED_ORIGINS` contains explicit full origins without trailing slashes.
Defaults cover ports 3000, 3001, and 3002 on both localhost and 127.0.0.1.
Use the same hostname consistently for frontend and backend; cookies for localhost
are not shared with 127.0.0.1. CORS allows credentials only for the configured origins.
Add new API paths/methods to `config/cors.php` when their endpoints are implemented.

In deployed environments, set both origin lists for the actual first-party hosts,
configure the shared `SESSION_DOMAIN` where subdomains require it, and use secure
cookies over HTTPS (`SESSION_SECURE_COOKIE=true`). Custom-domain authentication
remains a separate future milestone.

Registration permits five requests per minute per IP. Login permits five requests
per minute per normalized email and IP pair. All attempts count; exceeding a limit
returns JSON 429 with `Retry-After`. Invalid credentials return JSON 422.

Run backend commands from `backend/api`:

```shell
php artisan test --compact tests/Feature/Auth/AuthenticationTest.php
php artisan test --compact
php artisan route:list --path=auth -v
```

The test configuration uses SQLite `:memory:`, array sessions/cache, and synchronous
queues. Do not override these settings with development PostgreSQL values or run
destructive migration commands against the development database. Authentication
feature tests send real stateful origins; the session-flow tests also enforce CSRF
and carry cookies between requests without using token authentication helpers.

## Account Foundation

`GET /accounts` and `GET /accounts/{account}` require `auth:sanctum` and use the
same browser session as authentication. Listing returns only non-deleted accounts
with an active membership for the authenticated user, ordered by ID. Retrieval
uses `AccountPolicy` and the same membership scope. Ownership alone grants no
access. Guests receive JSON 401; unrelated users and inactive members receive
generic JSON 404 for retrieval, matching missing/soft-deleted accounts.

Account resources expose only `id`, `name`, `status`, and `owner_id`. Listing returns
`{"data": [...]}` and retrieval returns `{"data": {...}}`. Registration returns
`message`, `user`, and `account` at the top level; the nested account uses the same
resource fields and omits pivot and soft-delete metadata.

`RegisterUser` owns the onboarding transaction and the initial naming rule:
`John Smith` becomes `John Smith's Account`. For long names, only the name portion
is truncated to fit the existing 255-character account-name column. No slug or
account-name registration input is introduced. User names remain unchanged.

Membership status `active` is centralized in `AccountMember::STATUS_ACTIVE`.
Other stored status strings do not grant access; no additional status lifecycle,
or account-management endpoints are added. Account authorization is described below.
Existing users are not automatically backfilled with accounts; until they have
active membership, their account list is empty.

Run focused account and onboarding coverage from `backend/api`:

```shell
php artisan test --compact tests/Feature/Accounts tests/Feature/Auth/RegistrationOnboardingTest.php
```

## Account Authorization Foundation

`GET /accounts/{account}/authorization` requires session authentication, active
membership, and `account.view`. Its JSON response contains `account_id`, `roles`,
and `permissions` (sorted name arrays), without internal pivot records. A guest
gets 401; an inaccessible/missing account gets generic 404; an active member
without the required permission gets 403. Existing account listing/retrieval
remain membership-based and do not require a role.

For account-scoped routes, declare the typed `Account $account` route binding,
apply `auth:sanctum` and `SetCurrentAccount`, then use Laravel's `can` middleware
or Gate for the required permission. Middleware priority places account context
after bindings and before permission checks. Always retain the membership boundary;
a raw role/permission check alone is not a tenant-access check.

`AccountContext` is a scoped service. Use its `run($account, $user, $callback)`
method for explicit backend operations; it verifies active membership, sets
Spatie's current team to the account ID, and clears loaded roles/permissions on
switching. `finally` cleanup restores nested contexts and removes request context.
Do not persist the context in static fields, session state, or future queued jobs.

`AccountRole` and `AccountPermission` centralize the baseline mapping:

- Owner and admin: `account.view`, `account.manage`, `account.members.manage`,
  `website.view`, `website.create`, `website.update`, `website.delete`.
- Member: `account.view`, `website.view`.

Ownership still comes from `accounts.owner_id`. An owner role never bypasses
membership and does not transfer ownership. No permission-management API, member
workflow, or content permissions are implemented.

`InitializeAccountAuthorization` creates three roles for the specified account
and seven shared permission definitions under the `web` guard. Repeating it
synchronizes the documented role permissions without duplicating roles or
removing user assignments. It locks the account row during initialization and
resets Spatie's permission cache before/after changes. Registration also resets
the cache after the outer onboarding transaction commits or rolls back.

Existing accounts are not modified automatically. An operator can explicitly run
the following from `backend/api`, replacing `<account-id>` with one account ID:

```shell
php artisan accounts:bootstrap-authorization <account-id>
php artisan accounts:bootstrap-authorization <account-id> --assign-owner
```

The first command initializes definitions only. The optional flag also assigns
the authoritative owner's account-scoped owner role, but refuses owners without
active membership. It does not create memberships or assign roles to other users.
Both commands target one account; neither is a broad backfill. Baseline role
permissions are reset to the documented mapping when rerun. Existing non-owner
members need a future explicit role-assignment workflow to use permission-gated
endpoints; membership-only account access continues to work.

Existing accounts must rerun the first command to receive the new website
permission mappings. Existing role assignments and direct user permissions remain
intact. No bootstrap has been run against development data by this milestone.

Focused tests:

```shell
php artisan test --compact tests/Feature/Accounts/AccountAuthorizationTest.php tests/Feature/Accounts/AccountAuthorizationBootstrapTest.php tests/Feature/Auth/RegistrationAuthorizationTest.php
```

Tests use the configured in-memory database and cache. They verify role and direct
permission isolation, context cleanup, cache invalidation, membership revocation,
bootstrap idempotence, and atomic rollback on owner-role assignment failure.
PostgreSQL-specific locking/concurrency verification remains a separate check.

## Website Foundation

All website routes use the existing cookie/CSRF flow and have no `/api` prefix:

| Method | Path | Required permission | Success |
| --- | --- | --- | --- |
| GET | /accounts/{account}/websites | website.view | 200 |
| POST | /accounts/{account}/websites | website.create | 201 |
| GET | /accounts/{account}/websites/{website} | website.view | 200 |
| PATCH | /accounts/{account}/websites/{website} | website.update | 200 |
| DELETE | /accounts/{account}/websites/{website} | website.delete | 204 |

Every route requires active account membership and validated account/team context.
Nested scoped binding and WebsitePolicy prevent cross-account access, including
users with membership in both accounts. Missing, foreign, or deleted resources
return generic 404. Members lacking capability receive 403; guests receive 401.
Stale roles do not bypass membership revocation.

POST requires `name` (nonempty string, max 255) and `subdomain`; `timezone`
is optional and defaults to UTC. PATCH validates only provided fields; omitted
values remain unchanged, and an empty PATCH is a no-op. Subdomains are trimmed and
lowercased before validation and on model assignment, globally unique, and limited
to one ASCII DNS label (1–63 letters/digits/hyphens, no leading/trailing hyphen).
No reserved-word list is introduced. Timezone must be a PHP-recognized timezone
identifier, at most 100 characters.

Payloads containing `account_id`, `id`, `status`, `published_at`, `created_at`,
`updated_at`, or `deleted_at` are rejected with 422, including null values.
Other unknown keys are not persisted. Ownership comes from the validated account.
Resources expose id, account_id, name, subdomain, status, timezone, published_at,
created_at, and updated_at under `data`; no soft-delete metadata or future domains.
Listing returns an ID-ordered unpaginated `data` array, consistent with account
listing and the current expectation of small account website counts.

DELETE soft-deletes the website. It disappears from normal relationships and
routes; its subdomain remains reserved by the existing global unique constraint.
No restore or publishing is implemented. Website creation now initializes only
baseline settings, as documented below; other related records remain deferred.
CORS now permits PATCH and DELETE; browser writes still require credentials/CSRF.

Run `php artisan test --compact tests/Feature/Websites` from `backend/api`.
Tests run on SQLite in memory. PostgreSQL collation, concurrent unique conflicts,
and foreign-key/locking behavior require separate PostgreSQL verification.
Application writes normalize subdomains; the existing database constraint itself
does not enforce lowercase. Pre-existing mixed-case or noncanonical rows require
an explicit audit before accepting live traffic; this milestone does not rewrite
them. A concurrent claim of the same subdomain remains protected by the database
unique constraint, but the losing request can currently return a database error
instead of the ordinary validation 422. No migrations are changed.

---

## Website Settings Foundation

Routes (both return 200 under the usual `data` envelope):

- GET `/accounts/{account}/websites/{website}/settings`: website.view.
- PATCH `/accounts/{account}/websites/{website}/settings`: website.update.

They inherit Sanctum, active membership, validated account/team context, scoped
website binding, and WebsitePolicy. Owners/admins can read and update; members
can read only. No standalone settings ID, POST, DELETE, or /api prefix exists.
Foreign/deleted parents return generic 404; missing capability returns 403.

The resource exposes exactly six fields:

| Field | Contract |
| --- | --- |
| site_name | Nonempty string, max 255; non-null |
| tagline | Nullable string, max 255 |
| contact_email | Nullable email string, max 255 |
| contact_phone | Nullable string, max 50; no country-specific phone rules |
| address | Nullable JSON object with the fixed keys below |
| social_links | Nullable JSON object with the fixed keys below |

Address keys: `line1`, `line2`, `city`, `state`, `postal_code`, `country`.
Values are strings or null; no country-code or address-verification behavior is
implied. Social keys: `facebook`, `instagram`, `linkedin`, `x`, `youtube`.
Values are HTTP(S) URLs or null. Network-specific URL ownership is not verified.
Unknown keys, nested arrays/objects, JSON strings, lists, and non-string values
are rejected. Empty JSON objects are allowed and retain object representation.
The existing JSON columns are unchanged; no separate columns or migrations exist.

PATCH preserves omitted fields and omitted keys within either JSON object.
Explicit null clears an entire JSON field, or just an individual supplied key.
An empty object preserves existing keys. Example:

```json
{
  "address": {"city": "Hyderabad", "line2": null},
  "social_links": {"linkedin": "https://www.linkedin.com/company/example"}
}
```

All unknown top-level fields, including IDs, ownership, timestamps, and unrelated
configuration, are rejected with 422. Authorization precedes validation.

Website creation uses a transaction to initialize exactly one settings row with
site_name copied from the initial website name. Optional fields default to null;
the schema defines no other configuration defaults. Settings failure rolls back
website creation. Later website renaming does not change settings.site_name.
Legacy websites without settings return these effective defaults on GET without
writing. Their first authorized PATCH persists a row, even with an empty payload.
Repeated PATCH requests retain one row. A parent-row lock serializes settings
updates and initialization, with the existing unique constraint as a final guard.
No developer data is backfilled. No additional authorization bootstrap is required
for accounts already initialized during Website Foundation.

Website soft deletion retains settings but makes their endpoints inaccessible.
Settings have no independent delete/restore API or soft-delete metadata.

Run `php artisan test --compact tests/Feature/WebsiteSettings`.
Coverage uses SQLite in memory. PostgreSQL JSON representation, parent-row locks,
concurrent partial updates, unique constraints, foreign-key cascades, and transaction
behavior still need PostgreSQL integration verification. The existing subdomain
concurrency concern remains deferred. Branding, SEO, domains, media, content,
publishing, and frontend work are not included.

## Dependency Policy

Do not add dependencies merely for convenience.

Before introducing a library:

1. Identify the concrete requirement.
2. Check whether the existing framework already solves it.
3. Prefer established, maintained packages.
4. Avoid overlapping libraries solving the same problem.

---

## Database Changes

All database schema changes must use Laravel migrations.

Do not manually depend on production database schema changes.

Keep migrations reviewable and focused.

---

## Business Logic

Important business rules belong in the Laravel domain/service layer.

Do not duplicate critical rules across Next.js applications.

Frontend validation improves UX but does not replace backend validation.

---

## Authorization

Every protected operation must be authorized by Laravel.

Never trust:

- frontend visibility
- URL parameters
- JavaScript state
- client-provided ownership information

as proof of permission.

---

## API Design

Keep API behavior consistent and resource-oriented where practical.

API endpoints should delegate meaningful business operations to appropriate Laravel application/domain services rather than accumulating all logic inside controllers.

Do not create abstractions merely to satisfy a pattern.

---

## AI Development

AI-generated actions must use structured application capabilities.

AI must not directly modify the database.

All AI modifications must pass through:

- validation
- authorization
- application/domain logic

Destructive or high-impact operations require appropriate confirmation.

---

## Frontend Development

Use TypeScript.

Keep components focused.

Do not embed backend business rules in UI components.

Website editing controls should remain separate from the underlying website presentation wherever practical.

---

## Public Website Performance

Do not design the public website runtime around unnecessary API/database calls on every visitor request.

Publishing, caching and CDN strategy will be implemented as the publishing architecture matures.

---

## Testing

Every module should be tested at the appropriate level.

Priority:

1. business/domain behavior
2. authorization
3. API behavior
4. critical user flows
5. frontend interactions

Bug fixes should include regression tests when practical.

---

## Development Cycle

For each significant module:

1. Define behavior.
2. Define UX.
3. Define required data.
4. Define backend behavior.
5. Define frontend behavior.
6. Implement.
7. Test.
8. Review.
9. Adjust.
10. Document meaningful decisions.

---

## Architecture Decisions

Do not silently change major architecture during implementation.

If implementation reveals that an existing architecture decision is problematic:

1. stop the architectural change
2. document the issue
3. discuss alternatives
4. choose the new direction
5. update documentation
6. implement the change
