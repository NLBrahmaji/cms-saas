# System Architecture

## Overview

CMS SaaS consists of multiple independently deployable applications backed by a central Laravel domain/API layer.

Conceptual production topology:

cmsplatform.com
→ Platform Next.js application

dashboard.cmsplatform.com
→ Dashboard Next.js application

*.cmsplatform.com
→ Website Next.js application

Custom domains
→ Website Next.js application

api.cmsplatform.com
→ Laravel API

Laravel
→ PostgreSQL

---

## Applications

### Platform

Location:

`apps/platform`

Responsibilities:

- SaaS marketing website
- product information
- pricing presentation
- public documentation/content where appropriate

The Platform application is not the CMS.

---

### Dashboard

Location:

`apps/dashboard`

Responsibilities:

- authentication UI
- My Websites
- account profile
- subscription
- billing
- usage
- account settings

The Dashboard should not become the primary website content editor.

---

### Website

Location:

`apps/website`

Responsibilities:

- render customer websites
- resolve websites based on hostname/site context
- public visitor experience
- owner editing layer
- preview experience
- interaction with CMS editing capabilities

One Website application serves many customer websites.

Do not create a separate Next.js application for every customer.

---

### Laravel API

Location:

`backend/api`

Laravel is the authoritative application/domain layer.

Responsibilities include:

- authentication
- authorization
- users
- sites
- pages
- content
- media
- navigation
- forms
- SEO
- drafts
- publishing
- versions
- AI action validation/execution
- subscriptions and usage when implemented

Important business rules should not exist only in Next.js.

---

## Database

PostgreSQL is the primary application database.

Redis may be introduced later for:

- caching
- queues
- sessions where appropriate
- real-time infrastructure

Do not introduce Redis until there is a concrete requirement.

---

## Account Ownership and Membership

Account is the SaaS tenant and ownership boundary:

User → Account Membership → Account → Websites

A user may own multiple accounts and belong to multiple accounts. Websites belong
to accounts, not directly to users.

`accounts.owner_id` identifies the owner. `account_members` separately records
membership, its status, and the joining timestamp. Ownership does not bypass the
membership requirement: account access requires a membership whose status is
exactly `active`. Soft-deleted accounts are excluded from account access.

Registration creates the user, their initial account, active owner membership,
and an account-scoped owner role assignment in one database transaction.
Registration does not log the user in. Ownership remains authoritative through
`owner_id`; the owner role grants capabilities and does not establish ownership.

The Account Foundation exposes account listing and retrieval. A shared
membership query scopes listing, and `AccountPolicy` authorizes retrieval.
Inaccessible and missing accounts return the same generic 404 response.

## Account Authorization

Authenticated User → Active Membership → Account Context → Spatie Team Context
→ Account-scoped Role → Permission.

Spatie's existing `team_id` represents `Account.id`. Role definitions and user
role/direct-permission assignments are team-scoped; permission definitions are
shared names under the `web` guard. No replacement authorization tables exist.

`SetCurrentAccount` runs after authentication and route binding, but before
Laravel's permission authorization middleware. `AccountContext::run()` verifies
active membership against the database before setting Spatie's team ID through
its supported API. Client headers, query parameters, and session preferences do
not select an account. The bound route account is the source of context.

The context is container-scoped and uses `finally` cleanup. It clears the user's
loaded roles and permissions on context changes, restores authorized outer
contexts for nested operations, and resets to no team at request boundaries.
Future queue operations must explicitly enter and leave an authorized context;
they must not inherit an ambient account or directly reuse loaded role relations.

Baseline account permissions:

| Role | Permissions |
| --- | --- |
| owner | `account.view`, `account.manage`, `account.members.manage`, `website.view`, `website.create`, `website.update`, `website.delete` |
| admin | `account.view`, `account.manage`, `account.members.manage`, `website.view`, `website.create`, `website.update`, `website.delete` |
| member | `account.view`, `website.view` |

Owner and admin currently have the same capability set. This does not authorize
ownership transfer; future owner-only operations must separately verify `owner_id`.
There is no global owner/super-admin bypass.

`GET /accounts/{account}/authorization` requires active membership plus
`account.view` and reports only account ID, role names, and permission names.
Membership-only listing/retrieval remain compatible with existing accounts that
have no roles yet. Account-management capabilities have no production CRUD endpoints yet.

Authorization initialization is per-account and idempotent, using explicit team
IDs and the package's permission-cache invalidation. It synchronizes the baseline
role mappings without removing user assignments. New registration invokes it
transactionally. Existing data requires explicit operator bootstrap; nothing is
backfilled at application startup or during reads.

Invitations, member-management workflows, content permissions, and account lifecycle
rules beyond soft deletion remain deferred. Membership is mandatory regardless
of retained role assignments.

---

## Website Structure

Website Foundation is implemented using the existing `websites` table.
`Account::websites()` is a has-many relationship; `Website::account()` is belongs-to.
The required account foreign key is assigned through the validated route account.
Clients cannot assign or transfer ownership.

Account-nested list/create/show/PATCH/delete routes require Sanctum authentication
and active membership through `SetCurrentAccount`. Collection routes check
website permissions directly; item routes invoke `WebsitePolicy`, which checks
the permission, current account/team, membership, and resource ownership.
Laravel scoped bindings resolve a website through the account's websites relation.
Mismatched IDs return generic 404 even when the user belongs to both accounts.
Soft-deleted accounts and websites do not bind.

Editable fields are name, normalized subdomain, and timezone. New websites default
to draft and UTC. Status and published_at are system-controlled; publishing is
not implemented. Deletion is soft deletion, with no restore or hard-delete API.
The global subdomain unique constraint includes deleted websites, reserving their
names. Account soft deletion blocks access without cascading soft deletion to
website rows; physical foreign-key cascades apply only to hard deletes.

Creation writes the website and its baseline settings atomically through
`CreateWebsite`. Branding, SEO, domains, integrations, media, content, rendering,
preview, and publishing remain deferred.

### Website Settings Foundation

`Website::settings()` is has-one; `WebsiteSettings::website()` is belongs-to.
The existing unique website_id constraint enforces one settings row per website.
Settings have no soft-delete column. Website soft deletion retains configuration,
while scoped parent binding blocks settings access. Physical deletion retains the
schema's foreign-key cascade behavior.

New websites created through the API receive settings with site_name initialized
from the website name; all other configuration starts null. Subsequent website
renaming does not overwrite site_name. Existing websites without a row receive
an unsaved default representation on GET; the first authorized PATCH persists it.
There is no read-time write or automatic backfill. Internal website creation
workflows should use CreateWebsite when baseline initialization is needed;
direct Eloquent/factory creation does not invoke an observer.

GET/PATCH `/accounts/{account}/websites/{website}/settings` reuse WebsitePolicy's
view/update capabilities. No new permissions, role mapping, or bootstrap is needed.
Updates lock the parent website within a transaction to serialize initialization
and partial JSON merges. The development guide defines the fixed JSON contract.

Conceptually:

Site
├── Identity
├── Settings
├── Brand
├── Theme
├── Pages
│   └── Sections
│       └── Components
├── Content
├── Media
├── Navigation
├── Forms
├── SEO
├── AI Context
├── Versions
└── Publishing

Implementation details may evolve as the product is developed.

---

## Page Structure

Conceptual structure:

Page
└── Section
    ├── Layout
    └── Components

Sections represent meaningful website areas.

Examples:

- Hero
- Features
- Testimonials
- Pricing
- FAQ
- Contact
- CTA

Components represent elements within sections.

Examples:

- Heading
- Text
- Image
- Button
- Card
- Video
- Form
- Icon
- List

Avoid locking the system into rigid section implementations.

---

## Content Architecture

Content should be separated from page presentation.

Examples of content:

- Products
- Services
- Blog Posts
- Team Members
- Testimonials
- FAQs
- Events

Pages and components may reference content records.

This allows one content record to appear in multiple locations without duplication.

---

## Draft and Published Architecture

Owners edit drafts.

Visitors consume published content.

Conceptual flow:

Editing Model
→ Draft
→ Preview
→ Publish
→ Published Representation
→ Cache/CDN
→ Website Runtime

The long-term published website should not require expensive database/API work for every visitor request.

The exact snapshot/cache implementation will be determined during publishing development.

---

## Authentication

Laravel owns authentication and authorization.

Next.js applications consume Laravel authentication.

Conceptually:

Next.js
→ Laravel Authentication
→ Session/User
→ Authorization
→ Domain Operation

The frontend must not independently decide ownership or permissions.

For first-party browser applications, Laravel Sanctum session/cookie authentication is the intended starting direction.

Custom-domain owner authentication will be designed separately when custom-domain support is implemented.

---

## AI Architecture

AI must operate through controlled application actions.

Conceptually:

User
→ AI Conversation
→ AI Orchestrator
→ Context
→ Model
→ Structured Action Plan
→ Validation
→ Authorization
→ Laravel Service Layer
→ Database

AI must never:

- directly write to the database
- generate and execute arbitrary SQL
- bypass Laravel authorization
- silently publish significant changes

AI providers should eventually be accessed through a provider-independent service abstraction.

---

## Shared Packages

`packages/` is reserved for code that genuinely needs to be shared.

A possible future package is:

`packages/website-engine`

Potential responsibilities:

- rendering concepts
- component definitions
- sections
- layouts
- theme/design tokens

Do not move authentication, billing or core backend business logic into the website engine.

Shared packages should be introduced when actual duplication or reuse justifies them.

---

## Architecture Change Rule

Major architectural changes should be discussed and documented before implementation.

Do not allow coding agents to silently replace established architecture with a different pattern.
