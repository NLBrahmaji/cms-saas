# Development Guide

## General Rule

Build SitePro incrementally through working vertical slices.

Prefer a small complete workflow over a large collection of disconnected
backend or frontend foundations.

Avoid implementing speculative frameworks, abstractions, infrastructure, or
generic systems before they are required by an approved product feature.

---

## Application Boundaries

### Platform

Location:

`apps/platform`

Public SitePro marketing application.

Responsibilities include:

- product marketing
- Themes
- Features
- AI product information
- Pricing
- Resources
- trial/signup entry points

The Platform application is not the CMS.

---

### Dashboard

Location:

`apps/dashboard`

Authenticated SitePro account-management application.

Responsibilities include:

- authentication UI
- My Websites
- account/profile
- subscription and billing
- AI usage
- notifications
- enquiries
- security
- support
- other account-level management

The Dashboard is not the primary website content editor.

---

### Website

Location:

`apps/website`

Customer website rendering and owner editing application.

The same application serves:

- public customer websites
- authorized website editing
- SitePro-managed tenant subdomains
- supported custom domains

One Website application serves many customer websites.

Do not create one Next.js application per customer.

---

### Laravel

Location:

`backend/api`

Laravel is the authoritative backend and domain application.

Laravel owns security-sensitive business behavior including:

- authentication
- authorization
- accounts
- memberships
- permissions
- websites
- ownership
- website settings
- domains
- content persistence
- draft/published state
- publishing
- versions/history
- enquiries
- AI mutations
- AI usage state
- subscription/billing state when implemented

Important business rules must not exist only in frontend applications.

---

## Local Development Ports

Default local development ports:

| Application | URL |
| --- | --- |
| Platform | `http://localhost:3000` |
| Dashboard | `http://localhost:3001` |
| Website | `http://localhost:3002` |
| Laravel API | `http://localhost:8000` |
| PostgreSQL | `localhost:5432` |

The Next.js applications should use their documented ports during normal local
development.

For example:

```shell
npm run dev -- --port 3001
```

for Dashboard and:

```shell
npm run dev -- --port 3002
```

for Website.

These addresses are development defaults.

Do not hardcode localhost URLs into application behavior.

Use environment configuration for application URLs, API endpoints, origins,
cookie configuration, and other environment-dependent values.

Use the same hostname style consistently during local authentication
development.

For example, do not unnecessarily mix:

```text
localhost
```

and:

```text
127.0.0.1
```

because browser cookie/origin behavior differs between them.

Start the Laravel API from `backend/api` with:

```shell
composer serve
```

That runs `php artisan serve --no-reload --host=localhost --port=8000`.

On Windows, plain `php artisan serve` (with hot reload) can strip `APP_KEY`
from the PHP built-in server child process and cause
`MissingAppKeyException` / “headers already sent” errors on web routes. Use
`composer serve` or pass `--no-reload` explicitly. Prefer `localhost` over
`127.0.0.1` for the host so Sanctum session cookies align with the dashboard
and website SPAs.

---

## Production URL Direction

Conceptually:

```text
cmsplatform.com
→ Platform

app.cmsplatform.com
→ Dashboard

*.cmsplatform.com
→ Website

Customer custom domains
→ Website

api.cmsplatform.com
→ Laravel API
```

These are conceptual production addresses.

The final platform/product domain is configuration and must not be embedded as
an irreversible application assumption.

---

## Development Sources of Truth

Before implementing a meaningful feature, consult the relevant project
documentation.

The documents have different responsibilities:

### `docs/product-principles.md`

Defines the product philosophy and interaction principles.

### `docs/v1-product-spec.md`

Defines what SitePro V1 should do.

This is the primary product-behavior reference.

### `docs/architecture.md`

Defines system boundaries, application responsibilities, security boundaries,
and major technical architecture.

### `docs/development-guide.md`

Defines how implementation work should be approached.

### `docs/roadmap.md`

Defines implementation sequence.

### `docs/designs/`

Contains local visual/UX references.

Design references are not authoritative business specifications.

Mockup values such as pricing, plan names, limits, domains, account data, or
other placeholders must not automatically become product requirements.

---

## Existing Implementation

Before changing code:

1. inspect the existing implementation
2. inspect relevant tests
3. inspect relevant migrations/schema
4. read the application's `AGENTS.md`
5. consult relevant product/architecture documentation

Do not assume a class, route, API, component, hook, service, or database field
exists because it appeared in an earlier design or development discussion.

Do not recreate obsolete implementation history automatically.

The current repository is authoritative for what code exists.

---

## Database Baseline

The committed Laravel migrations are the current database architecture
baseline.

Before implementing backend persistence:

- inspect the relevant migrations
- inspect existing relationships and constraints
- understand nullable/default behavior
- understand indexes and uniqueness
- understand soft-delete behavior
- understand foreign-key behavior

Do not modify existing committed migrations merely to make implementation
easier.

When an approved feature requires a schema change, create an appropriate new
migration.

Database architecture changes should be deliberate and reviewable.

---

## Authentication Direction

Laravel owns authentication.

The Next.js applications consume Laravel authentication rather than becoming
independent authentication authorities.

For first-party SitePro browser applications, Laravel Sanctum
session/cookie authentication is the intended starting direction.

Do not introduce bearer-token authentication for the first-party browser
applications merely because it is easier for frontend code.

Authentication implementation must account for:

- browser cookies
- CSRF
- CORS
- allowed origins
- credentials
- session security
- development hostnames
- production hostnames
- secure cookies in production
- SameSite behavior

Exact configuration should be implemented and tested as part of the
authentication milestone.

---

## Registration and Login

V1 requires a coherent account onboarding and authentication experience.

Registration should establish the required initial SitePro account structure
according to the Account architecture.

Conceptually:

```text
Register User
      ↓
Create Initial Account
      ↓
Create Active Owner Membership
      ↓
Initialize Required Account Authorization
```

These related operations should be consistent and transactional where
necessary.

Do not invent additional onboarding fields, account naming requirements,
automatic login behavior, or role workflows unless established by the product
or architecture specification.

Login should establish the Laravel-controlled authenticated browser session.

Logout should safely terminate the authenticated session.

The authenticated-user endpoint/API shape should be designed explicitly when
the authentication implementation is created.

---

## Custom-Domain Authentication

Do not assume that authentication cookies for SitePro-controlled domains will
be available on unrelated customer custom domains.

Custom-domain editor authentication is a separate security-sensitive
workflow.

Follow the architecture documented in `docs/architecture.md`.

Do not invent a permanent cross-domain token mechanism during unrelated
feature implementation.

When custom-domain editor authentication is implemented, explicitly design and
test the secure handoff/session mechanism.

---

## Account Foundation

Account is the SaaS tenant and ownership boundary.

Conceptually:

```text
User
  ↓
Account Membership
  ↓
Account
  ↓
Websites
```

A Website belongs to an Account.

A user may belong to more than one Account.

Account access requires an appropriate active membership.

Ownership alone must not silently bypass the membership boundary.

`accounts.owner_id` remains the authoritative ownership concept defined by the
database architecture.

Role assignment does not itself redefine account ownership.

---

## Account Authorization

The intended authorization direction is:

```text
Authenticated User
        ↓
Active Membership
        ↓
Account Context
        ↓
Spatie Team Context
        ↓
Account-Scoped Role
        ↓
Permission
```

Spatie team context represents the Account context.

Do not create a second competing role/permission architecture unless the
established model is explicitly changed.

The Account associated with the authorized operation must establish account
context.

Do not trust client-controlled state such as:

- account IDs stored in JavaScript
- query parameters
- custom headers
- local storage
- frontend-selected account state

as authorization proof.

Frontend state may help select an account for UX purposes, but Laravel must
independently establish and authorize the real account context.

---

## Baseline Account Permissions

The current architecture defines this baseline:

| Role | Permissions |
| --- | --- |
| owner | `account.view`, `account.manage`, `account.members.manage`, `website.view`, `website.create`, `website.update`, `website.delete` |
| admin | `account.view`, `account.manage`, `account.members.manage`, `website.view`, `website.create`, `website.update`, `website.delete` |
| member | `account.view`, `website.view` |

Owner and admin currently share the same baseline capability set.

This does not imply ownership transfer rights.

Operations that are inherently owner-specific must verify authoritative
ownership separately.

Do not expand the role/permission catalogue without an approved requirement.

---

## Tenant Isolation

Tenant isolation is mandatory.

A resource ID alone is never proof that an authenticated user may access the
resource.

Protected operations must consider the appropriate combination of:

- authenticated user
- active membership
- Account
- Website
- permission
- resource ownership

Where practical, resolve nested resources through their authorized parent.

For example:

```text
Account
  ↓
Website
  ↓
Website Resource
```

is safer than independently loading a Website resource and trusting a supplied
ID.

Tenant isolation applies to:

- accounts
- websites
- pages
- sections
- settings
- media
- navigation
- forms
- enquiries
- domains
- versions
- AI operations
- publishing
- background jobs
- caching

Add tests for cross-account and cross-website access when implementing
protected functionality.

---

## Website Foundation

Website implementation must follow the established `websites` database
architecture.

A Website belongs to an Account.

Clients must not arbitrarily assign website ownership.

Website creation should derive ownership from the authorized Account context.

Website operations must respect:

- authentication
- active membership
- account context
- website permissions
- tenant ownership

Website status and publication state are system-controlled concepts.

Do not allow arbitrary client mutation of system-controlled publishing fields.

Soft deletion, uniqueness, defaults, and other database behavior should follow
the committed schema unless explicitly changed through an approved migration.

---

## Website Settings Contract

Website Settings use the existing database architecture.

A Website has one Website Settings record.

The established editable settings contract is:

| Field | Contract |
| --- | --- |
| `site_name` | Required non-null string when supplied; max 255 |
| `tagline` | Nullable string; max 255 |
| `contact_email` | Nullable email; max 255 |
| `contact_phone` | Nullable string; max 50 |
| `address` | Nullable structured object |
| `social_links` | Nullable structured object |

Supported `address` keys:

```text
line1
line2
city
state
postal_code
country
```

Each address value is a nullable string.

Supported `social_links` keys:

```text
facebook
instagram
linkedin
x
youtube
```

Each social-link value is a nullable HTTP(S) URL.

Unknown fields and unknown nested keys must not silently become arbitrary
configuration.

Nested arrays/objects are not implied by this contract.

PATCH-style updates should preserve omitted settings and omitted JSON keys.

Explicit `null` may clear an allowed nullable value according to the approved
contract.

Website creation should initialize required baseline settings consistently.

Do not infer additional website settings from design mockups.

---

## API Design

Keep API behavior explicit, predictable, and resource-oriented where
practical.

Do not invent API contracts from frontend assumptions.

For APIs:

- use Laravel authorization
- use validation/Form Requests where appropriate
- use API Resources where appropriate
- use transactions when consistency requires them
- keep error behavior predictable
- preserve tenant boundaries

Follow the application's established routing convention.

Do not introduce a new API versioning structure unless the project architecture
requires it.

Do not add an `/api` prefix merely because Laravel APIs commonly use one if
the established SitePro routing convention intentionally does not.

Controllers should coordinate HTTP concerns.

Do not accumulate large amounts of business logic inside controllers.

At the same time, do not automatically create:

```text
Controller
→ Service
→ Repository
→ Manager
→ Adapter
```

for simple operations.

Use an abstraction when it has a clear responsibility.

---

## Business Logic

Important business rules belong in the Laravel application/domain layer.

Do not duplicate critical rules across Next.js applications.

Frontend validation improves user experience but does not replace backend
validation.

Frontend applications must not become authoritative for:

- ownership
- permissions
- account membership
- domain ownership
- publishing eligibility
- billing state
- AI allowance
- other security-sensitive business rules

---

## Authorization

Every protected operation must be authorized by Laravel.

Never trust:

- frontend visibility
- URL parameters
- JavaScript state
- hidden buttons
- client-provided ownership information
- a resource ID alone

as proof of permission.

Authorization should occur before protected mutation.

Security-sensitive authorization behavior should have automated tests.

---

## Frontend Development

Use TypeScript in the Next.js applications.

Keep components focused.

Prefer Server Components where they improve the architecture and no client
behavior is required.

Use `"use client"` only where client-side interaction actually requires it.

Do not embed backend business rules in UI components.

Do not create application-wide client state by default.

Prefer, in order where appropriate:

1. URL/navigation state
2. server/API-derived persistent state
3. local component state
4. feature-level shared state when genuinely necessary

Do not introduce a global state library merely because the application may
eventually become complex.

---

## Platform Development

The Platform application should remain relatively simple.

Route files should primarily compose pages.

Reusable marketing UI belongs in appropriate Platform components/features.

Do not turn every marketing section into a feature architecture.

Prioritize:

- accessibility
- SEO
- responsive behavior
- performance
- visual consistency

Do not treat placeholder marketing copy, pricing, limits, or account data as
backend business requirements.

---

## Dashboard Development

The Dashboard should be feature-oriented without unnecessary ceremony.

Routes should remain reasonably thin.

Meaningful domain UI may be organized around features such as:

- websites
- account
- billing
- domains
- AI usage
- enquiries
- security
- support

Do not automatically create a component, hook, service, schema, store, and
utility directory for every feature.

Start with the smallest structure that keeps the feature clear.

Centralize common API transport/configuration.

Keep feature-specific operations near the feature where practical.

Do not scatter arbitrary `fetch()` behavior throughout unrelated UI
components.

---

## Website Application Development

The Website application is the most architecture-sensitive frontend.

It serves both:

- public website visitors
- authorized website owners/editors

Both must use the same underlying Website Renderer.

Do not create separate website-rendering implementations for public and editor
modes.

The dependency direction is:

```text
Editor
  ↓
Website Renderer
```

The Website Renderer must not depend on editor implementation.

Public visitors should not unnecessarily load editor code.

Editor selection/UI state must remain separate from persisted website content.

Avoid creating one giant `WebsiteEditor` component containing all editor
responsibilities.

Compose editor behavior around clear responsibilities.

---

## Hostname and Tenant Resolution

The Website application eventually resolves the current Website from the
incoming hostname.

Normal public requests must not trust an arbitrary client-provided Website ID
instead of hostname resolution.

Tenant/domain resolution must be centralized and testable.

During ordinary local development:

```text
http://localhost:3002
```

may be used for general Website application work.

When hostname-specific behavior is implemented, use an explicit local
hostname-testing strategy.

Do not weaken production tenant resolution merely to make localhost testing
easier.

---

## Sections and Designs

Website pages are composed from supported Sections.

Section content and Design should remain conceptually separate.

Changing a compatible Design should preserve content where possible.

Do not expose technical schema/variant terminology unnecessarily to customers.

Do not build the entire possible section catalogue before the product requires
it.

Implement sections incrementally as working product features.

Do not build an unrestricted generic page-builder framework unless an approved
requirement needs it.

---

## Draft and Published State

Editing and publishing are separate operations.

Conceptually:

```text
Edit
 ↓
Draft
 ↓
Preview
 ↓
Publish
 ↓
Published Website
```

Public visitors must receive published state.

Draft data must not accidentally become publicly visible.

Preview must remain appropriately authorized.

Do not make every edit public automatically.

The exact published representation and caching architecture should be
implemented when the publishing milestone requires it.

---

## AI Development

AI is an assistance layer over the normal CMS.

Manual CMS functionality must remain usable without AI.

AI-generated actions must use controlled application capabilities.

AI must not directly modify the database outside approved application/domain
operations.

AI modifications must pass through:

- authentication
- authorization
- tenant isolation
- validation
- application/domain behavior
- appropriate history/version behavior
- publishing rules

AI must not create a privileged parallel mutation path.

Destructive, broad, or high-impact AI operations require the appropriate
preview or confirmation behavior defined by the product.

Do not build provider abstractions before a concrete AI integration requires
them.

---

## Forms and Enquiries

Public form submission and private enquiry management are separate concerns.

Public visitors may submit supported published forms.

Enquiry records are private account/website information.

Never expose enquiry data through the public website runtime.

Form-management operations must follow normal Website authorization.

Submission behavior should include appropriate validation and abuse/security
controls when implemented.

---

## Media

Media management must preserve Account/Website boundaries.

A media ID must not grant cross-tenant access.

Public assets required by published websites may be publicly deliverable, but
media-management operations remain authorized.

Do not select a storage/CDN/transformation architecture before the media
feature requires it.

---

## Domains

Laravel/database domain relationships are authoritative for domain ownership
and website association.

Custom-domain implementation should explicitly handle the approved lifecycle,
which may include:

- domain submission
- verification
- activation
- SSL readiness
- routing
- removal

Do not invent the exact lifecycle before the domain milestone defines it.

Domain-related functionality is security-sensitive and should have appropriate
tests.

---

## Public Website Performance

The public Website runtime is production customer infrastructure.

Do not design it around unnecessary API/database work on every visitor
request.

Public visitors should not load the full editor implementation.

Publishing, caching, CDN, and invalidation strategies should be introduced as
the public rendering/publishing architecture matures.

Caching must remain tenant-aware.

Never allow a cache optimization to risk serving one customer's website
content to another customer's domain.

---

## Dependency Policy

Do not add dependencies merely for convenience.

Before introducing a library:

1. identify the concrete requirement
2. check whether the existing framework already solves it
3. inspect existing project dependencies
4. prefer established and maintained packages
5. avoid overlapping libraries solving the same problem

Do not add a dependency solely because generated code commonly uses it.

---

## Shared Code

The three Next.js applications are independently deployable.

Do not create cross-application imports.

Do not introduce a shared React/component package initially.

A shared package may be introduced later only when real, stable duplication
demonstrates that it provides a clear architectural benefit.

Do not move Laravel-owned business logic into frontend shared code.

---

## Database Changes

All database schema changes must use Laravel migrations.

Keep migrations focused and reviewable.

The committed migrations are the current architecture baseline.

Do not rewrite historical migrations without an explicit architecture decision.

Do not depend on manual production database changes.

---

## Testing

Test behavior at the appropriate level.

Priority:

1. business/domain behavior
2. tenant isolation
3. authorization
4. API behavior
5. critical user workflows
6. frontend interactions

Bug fixes should include regression tests when practical.

Security-sensitive behavior should receive explicit test coverage.

For backend work, prefer focused tests during implementation.

Run broader test suites at appropriate checkpoints.

Do not create throwaway verification scripts when a useful automated test
should exist instead.

---

## Development Cycle

For each significant feature:

1. identify the approved product requirement
2. review relevant UX/design reference
3. review relevant architecture
4. inspect existing implementation
5. inspect database/schema where relevant
6. define the smallest coherent behavior
7. implement backend/domain behavior where required
8. implement frontend behavior where required
9. test
10. review the complete user workflow
11. update meaningful documentation when decisions changed

Prefer finishing a useful vertical slice over creating multiple unfinished
foundations.

---

## Scope Discipline

During a feature implementation:

- do not modify unrelated code
- do not redesign unrelated architecture
- do not introduce speculative infrastructure
- do not invent product behavior
- do not add future features merely because they are nearby
- do not silently change established contracts

If an unrelated problem is discovered, document or raise it separately unless
it blocks the current feature.

---

## Architecture Decisions

Do not silently change major architecture during implementation.

If implementation reveals that an existing architecture decision is
problematic:

1. stop the architectural change
2. identify the problem
3. discuss alternatives
4. choose the new direction
5. update the relevant documentation
6. implement the approved change

Coding agents must follow this process rather than quietly replacing the
architecture with their preferred pattern.

---

## Product Decisions

Do not invent unresolved commercial or product rules.

Examples include:

- exact pricing
- plan names
- trial duration
- payment-card requirements
- AI allowance limits
- storage limits
- website limits
- final theme catalogue
- final section catalogue
- plan-specific restrictions

If implementation genuinely depends on an unresolved decision, surface the
decision instead of silently selecting a value.

---

## Definition of Good Implementation

A good SitePro implementation is not the one with the most architecture,
files, abstractions, or generated code.

Prefer code that is:

- correct
- secure
- tenant-safe
- understandable
- testable
- appropriately simple
- consistent with Laravel/Next.js conventions
- consistent with SitePro product behavior
- easy for a developer to modify manually later

The architecture exists to preserve clarity and product behavior, not to
maximize complexity.