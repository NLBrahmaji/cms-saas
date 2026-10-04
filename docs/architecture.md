# System Architecture

## Overview

SitePro consists of multiple independently deployable applications backed by a
central Laravel domain/API layer.

Conceptual production topology:

```text
cmsplatform.com
→ Platform Next.js application

app.cmsplatform.com
→ Dashboard Next.js application

*.cmsplatform.com
→ Website Next.js application

Custom domains
→ Website Next.js application

api.cmsplatform.com
→ Laravel API

Laravel
→ PostgreSQL
```

The domain names above are conceptual examples. Production domain names must be
configuration rather than hardcoded application assumptions.

The applications are intentionally separated by responsibility and deployment
boundary.

---

## Local Development Topology

Production hostnames and local development addresses are separate concerns.

The default local development topology is:

```text
Platform
http://localhost:3000

Dashboard
http://localhost:3001

Website
http://localhost:3002

Laravel API
http://localhost:8000
```

These are development defaults, not hardcoded production assumptions.

Each application must use environment configuration for URLs and service
endpoints.

Conceptually:

```text
apps/platform
→ http://localhost:3000

apps/dashboard
→ http://localhost:3001
→ Laravel API: http://localhost:8000

apps/website
→ http://localhost:3002
→ Laravel API: http://localhost:8000

backend/api
→ http://localhost:8000
```

Frontend code must not hardcode these localhost addresses.

Environment variables should provide application and API URLs appropriate to
the current environment.

For example:

```text
Local Development
Dashboard → http://localhost:3001
API       → http://localhost:8000

Production
Dashboard → https://app.cmsplatform.com
API       → https://api.cmsplatform.com
```

The exact environment variable names should follow each application's
established configuration conventions.

### Local Website Tenant Resolution

The Website application eventually needs to test hostname-based tenant
resolution locally.

Normal development may use:

```text
http://localhost:3002
```

for general Website application development.

Features that specifically depend on hostname/domain resolution should use a
documented local hostname strategy rather than bypassing tenant resolution in
production code.

For example, local development may later use hostnames conceptually similar to:

```text
site1.localhost
site2.localhost
```

or another explicitly configured local development domain strategy.

The exact local custom-domain testing approach should be established when
domain resolution is implemented.

Do not introduce production logic that trusts arbitrary website IDs merely to
make localhost development easier.

### Configuration Rule

URLs, cookie domains, API origins, CORS configuration, Sanctum stateful
domains, and similar environment-specific values must be configurable.

Do not assume that development, testing, staging, and production use the same
hostnames.

---

## Applications

### Platform

Location:

`apps/platform`

Responsibilities:

- SaaS marketing website
- product information
- How It Works
- Themes and theme previews
- Features
- AI product information
- pricing presentation
- resources/public content where appropriate
- trial and signup entry points

The Platform application is not the CMS.

It must not become responsible for website editing, account authorization, or
customer website rendering.

---

### Dashboard

Location:

`apps/dashboard`

Responsibilities:

- authentication UI
- My Websites
- account/profile management
- subscription and billing UI
- domain management where appropriate
- AI usage
- notifications
- enquiries
- website history access where appropriate
- security
- help and support

The Dashboard is the authenticated SitePro account-management experience.

It should not become the primary website content editor.

When a user chooses to edit a website, the normal flow should move them into
the Website application and the website-first editing experience.

---

### Website

Location:

`apps/website`

Responsibilities:

- render customer websites
- resolve websites from hostname/site context
- public visitor experience
- authorized owner editing layer
- website preview
- website-first CMS interaction
- contextual content editing
- section/design editing
- AI website assistance UI
- publishing UI
- tenant-aware website SEO/rendering

One Website application serves many customer websites.

Do not create a separate Next.js application for every customer.

The same Website application may therefore respond to:

```text
site1.cmsplatform.com
site2.cmsplatform.com
customer-one.com
customer-two.com
```

while resolving each hostname to the correct website.

---

### Laravel API

Location:

`backend/api`

Laravel is the authoritative application and domain layer.

Responsibilities include:

- authentication
- authorization
- users
- accounts
- account memberships
- permissions
- websites
- website settings
- pages
- sections/content
- media
- navigation
- forms
- enquiries
- SEO-related website data
- domains
- drafts
- publishing
- versions/history
- AI action validation and execution
- AI usage/allowance state when implemented
- subscriptions and billing state when implemented

Important business rules must not exist only in Next.js.

Frontend applications may provide user-interface validation and convenience,
but Laravel remains authoritative for security-sensitive validation,
authorization, ownership, tenant boundaries, and domain operations.

---

## Application Independence

The Platform, Dashboard, Website, and Laravel API are independently deployable
applications.

Each application should remain understandable and maintainable independently.

Do not introduce cross-application imports.

Do not create shared frontend packages merely because similar code exists in
multiple applications.

Duplication is acceptable when it preserves clear application ownership and
the shared abstraction has not yet proven necessary.

Shared code may be extracted later when stable, genuine reuse has been
demonstrated.

---

## Database

PostgreSQL is the primary application database.

The committed Laravel migrations are the current database architecture
baseline.

Implementation must inspect and respect those migrations rather than
redesigning established tables from assumptions.

Existing committed migrations should not be modified merely to make a new
implementation easier.

Schema changes should be explicit architecture decisions and should normally
be introduced through new migrations.

Redis may be introduced later for:

- caching
- queues
- sessions where appropriate
- real-time infrastructure

Do not introduce Redis until there is a concrete requirement.

---

## Account Ownership and Membership

Account is the SaaS tenant and ownership boundary:

```text
User
  ↓
Account Membership
  ↓
Account
  ↓
Websites
```

A user may own multiple accounts and belong to multiple accounts.

Websites belong to accounts, not directly to users.

`accounts.owner_id` identifies the account owner.

`account_members` separately records membership, its status, and joining
information.

Ownership does not bypass membership requirements.

Account access requires an appropriate membership whose status is `active`.

Soft-deleted accounts must not be treated as accessible accounts.

Registration is expected to create:

- the user
- their initial account
- an active owner membership
- the appropriate account-scoped owner role assignment

as one consistent operation.

Ownership remains authoritative through `owner_id`.

An owner role grants capabilities but does not itself establish legal/domain
ownership of the account.

---

## Account Authorization

The authorization model is:

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

Spatie's existing `team_id` represents the Account ID.

Role definitions and user role/direct-permission assignments are account/team
scoped.

Permission definitions are shared permission names under the application's
established guard.

Do not create replacement authorization tables when the established Spatie
team model satisfies the requirement.

The authenticated route account should be the authoritative source of account
context for account-scoped operations.

Client-controlled headers, query parameters, local frontend state, or stored
preferences must not independently establish authorization context.

Account context handling must verify active membership before entering the
account's authorization context.

Context must not leak between accounts or requests.

Future queued/background operations must explicitly establish an authorized
account context rather than inheriting ambient request state.

Baseline account permissions are:

| Role | Permissions |
| --- | --- |
| owner | `account.view`, `account.manage`, `account.members.manage`, `website.view`, `website.create`, `website.update`, `website.delete` |
| admin | `account.view`, `account.manage`, `account.members.manage`, `website.view`, `website.create`, `website.update`, `website.delete` |
| member | `account.view`, `website.view` |

Owner and admin currently have the same baseline capability set.

This does not imply that an admin can perform ownership-specific operations.

Future ownership transfer or owner-only operations must separately verify the
authoritative account owner.

There is no implicit global owner or super-admin authorization bypass.

Membership is mandatory regardless of retained role assignments.

Invitations, member-management workflows, expanded content permissions, and
account lifecycle rules beyond the established foundation remain separate
product decisions unless explicitly implemented.

---

## Tenant Isolation

Account and website boundaries are security boundaries.

Every protected operation must establish the authorized account and website
context rather than trusting a resource ID alone.

Knowing or supplying another account's website, page, media, enquiry, or other
resource identifier must not grant access to that resource.

Where appropriate, nested resource resolution should be scoped through the
authorized parent resource.

Inaccessible and nonexistent protected resources should use behavior that does
not unnecessarily reveal cross-tenant information.

Tenant isolation must apply consistently to:

- HTTP requests
- background jobs
- AI actions
- publishing
- media
- forms and enquiries
- caching
- domain resolution
- version/history access

---

## Website Foundation

The Website domain uses the existing `websites` database architecture.

A Website belongs to an Account.

An Account may have many Websites.

Website creation must derive the account relationship from an authorized
account context.

Clients must not be able to arbitrarily assign or transfer website ownership
by submitting another account ID.

Account-scoped website operations must require:

- authentication
- active account membership
- appropriate account context
- required website permission
- resource ownership within that account

Website identifiers alone are not authorization.

The established website fields and database constraints defined by the
committed migrations remain the architecture baseline.

Website status and publishing state are system-controlled concepts rather than
arbitrary client-controlled values.

Website deletion follows the established soft-delete database architecture.

Restore and permanent deletion behavior must not be invented unless separately
specified.

---

## Website Settings Foundation

A Website has one Website Settings record according to the existing database
architecture.

The existing unique `website_id` relationship enforces one settings record per
website.

Website settings currently cover the established settings contract, including
supported identity/contact/address/social configuration.

New website creation should initialize required baseline settings consistently
with the approved website-creation workflow.

Settings access must remain scoped through the authorized Website and Account.

Settings updates must preserve omitted values and follow the established
validation contract.

Unknown or unsupported settings must not silently become arbitrary website
configuration.

Website settings are part of the Website domain and do not create a separate
authorization boundary.

---

## Website Structure

Conceptually:

```text
Website
├── Identity
├── Settings
├── Brand
├── Theme
├── Pages
│   └── Sections
├── Content
├── Media
├── Navigation
├── Forms
├── SEO
├── AI Context
├── Versions
└── Publishing
```

This is a conceptual domain map, not a requirement to create one class, table,
service, or directory for every item.

Implementation details may evolve as individual product features are developed.

Do not generate architecture layers solely to mirror this diagram.

---

## Page and Section Structure

A page is composed from supported website sections.

Conceptually:

```text
Page
└── Section
    ├── Content
    └── Design
```

Sections represent meaningful website areas.

Examples may include:

- Hero
- Features
- Testimonials
- Pricing
- FAQ
- Contact
- CTA

The exact V1 section catalogue is a product decision and must not be inferred
from this list.

Section content and section presentation should remain conceptually separate
so that compatible Design changes can preserve existing content.

Internal implementation may use structured elements/components where useful,
but normal customers should not be required to understand component schemas,
layout variants, or implementation terminology.

Avoid locking the system into unnecessarily rigid section implementations.

At the same time, do not build an unrestricted generic page-builder framework
unless a real product requirement requires it.

---

## Themes and Designs

A Website Theme is the initial whole-site starting design.

A theme may establish an initial combination of:

- pages
- sections
- section designs
- header design
- footer design
- typography
- visual styling

A theme is not a permanent restriction.

After website creation, supported content and designs can evolve independently
according to SitePro capabilities.

A Design represents presentation/layout rather than a separate copy of the
content.

Changing a compatible Design should preserve existing content where possible.

---

## Content Architecture — Future Direction

Reusable structured content may eventually support concepts such as:

- products
- services
- blog posts
- team members
- testimonials
- FAQs
- events

Pages or sections may eventually reference reusable content records where that
provides real product value.

However, a generic reusable content-modeling system is not automatically a V1
requirement.

Do not build a generic headless-CMS content architecture speculatively.

Implement structured/reusable content when an approved V1 feature requires it.

---

## Website Renderer and Editor Boundary

The Website application has two primary runtime experiences:

1. public website rendering
2. authorized visual editing

Both experiences must use the same website rendering system.

Conceptually:

```text
Laravel Website Data
        |
        v
Website Renderer
        |
        +----------------------+
        |                      |
        v                      v
Public Visitor          Authorized Owner
        |                      |
Renderer Only          Renderer + Editor
```

Do not create independent public and editor website renderers.

The owner should edit the same website representation that visitors ultimately
experience.

The editor enhances the website renderer with capabilities such as:

- element/section selection
- inline editing
- editing overlays
- contextual toolbars
- section controls
- design selection
- AI actions
- preview controls
- publishing controls

The dependency direction is:

```text
Editor
  ↓
Website Renderer
```

The Website Renderer must not depend on the editor implementation.

Public visitors should not unnecessarily load:

- editor code
- editor state
- editing overlays
- AI editing functionality
- owner-only controls

Public website performance takes priority over editor implementation
convenience.

---

## Editor Architecture

The editor is an enhancement layer over the Website Renderer.

Editor-specific state should be separated from persistent website data.

Examples of temporary editor state include:

- selected section
- selected editable element
- open toolbar
- open design picker
- active AI interaction
- temporary preview state

These states should not automatically become persisted website data.

Avoid creating one giant website-editor component responsible for rendering,
selection, persistence, AI, navigation, publishing, and all editor UI.

Compose editor responsibilities around clear behavior boundaries.

Avoid introducing a large global state store by default.

Use the smallest state mechanism that satisfies the actual editor requirement.

Manual website editing must remain functional independently of AI.

---

## Website and Domain Resolution

The incoming hostname determines which Website the Website application
attempts to render.

Supported hostname categories include:

- SitePro-managed tenant subdomains
- verified customer custom domains

Conceptually:

```text
site1.cmsplatform.com
        |
        v
Hostname Resolution
        |
        v
Website Identity
        |
        v
Website Runtime
```

and:

```text
customer.com
        |
        v
Hostname Resolution
        |
        v
Website Identity
        |
        v
Website Runtime
```

Laravel/database domain relationships are authoritative for website/domain
ownership and validity.

A browser-provided website ID must not override hostname-based tenant
resolution for normal public website requests.

Hostname resolution must be centralized enough to remain predictable and
testable.

Domain resolution must distinguish valid configured domains from unknown,
unverified, disabled, or otherwise unusable domains according to the approved
domain lifecycle.

The exact domain verification and activation workflow will be defined when
custom-domain functionality is implemented.

---

## Draft and Published Architecture

Owners edit draft website state.

Public visitors consume published website state.

Conceptually:

```text
Editing Model
      ↓
Draft
      ↓
Preview
      ↓
Publish
      ↓
Published Representation
      ↓
Cache / CDN
      ↓
Website Runtime
```

Editing and publishing are distinct operations.

Saving an edit must not automatically imply public publication unless an
explicit approved workflow says otherwise.

Draft content must never be exposed to unauthenticated public visitors.

Preview must provide authorized access to the appropriate draft representation
without changing what normal public visitors receive.

Publishing should create or activate an appropriate published representation.

The long-term public website should not require expensive database/API work for
every visitor request.

The exact published snapshot, cache, invalidation, and CDN implementation will
be determined during publishing development.

Published website caching must be tenant-aware and publishing-aware.

Cache keys and invalidation must prevent one website's content from being
served for another website or hostname.

---

## Preview

Preview allows an authorized user to inspect website changes before publishing
where appropriate.

Preview is not equivalent to publication.

Preview access must respect:

- authentication where required
- website authorization
- tenant boundaries
- draft/published separation

Preview mechanisms must not expose private draft data through predictable
public URLs without appropriate authorization.

---

## Publishing

Publishing is an explicit domain operation.

The Laravel backend remains authoritative for whether a website may be
published and what data constitutes the published state.

Frontend applications must not manufacture published state independently.

Publishing should eventually coordinate the necessary:

- validation
- authorization
- version/history behavior
- published representation
- cache invalidation
- website runtime refresh

The exact implementation should be introduced when the publishing feature is
developed rather than prematurely abstracted.

---

## Versions, History, and Undo

SitePro distinguishes between immediate editor Undo and persistent website
history/versioning.

They may cooperate but are not assumed to be the same mechanism.

Persistent versions/history must belong to the correct Website and respect
tenant authorization.

AI-originated mutations must participate in the appropriate version/history
behavior rather than bypassing it.

The exact version snapshot strategy will be defined with the publishing and
history implementation.

---

## Forms and Enquiries

Forms have two distinct concerns:

1. public form rendering/submission
2. authorized form configuration and enquiry management

Public visitors may interact only with published forms exposed by the website.

Form configuration belongs to the authorized website-management experience.

Enquiries are private account/website data.

Public website requests must never expose enquiry records or owner-only form
configuration.

Submission endpoints must preserve website/tenant context and apply appropriate
validation, abuse protection, and security controls.

---

## Media

Media belongs to an authorized Website/Account context.

Media identifiers must not provide cross-tenant access.

Public media required by a published website may be publicly deliverable, but
management operations remain authorized.

The exact storage provider, transformation pipeline, CDN strategy, and limits
should be introduced when required rather than assumed by the architecture.

---

## SEO and Metadata

Website and page SEO data is managed as part of the Website domain.

The Website application is responsible for rendering appropriate metadata for
the resolved website/page.

Laravel remains authoritative for persisted SEO configuration.

Public metadata must be based on published state.

Editor convenience must not cause draft metadata to leak into normal public
responses.

The exact advanced SEO feature set is a product decision.

---

## Authentication

Laravel owns authentication and authorization.

Next.js applications consume Laravel authentication rather than implementing
an independent authentication authority.

Conceptually:

```text
Next.js
   ↓
Laravel Authentication
   ↓
Session / User
   ↓
Authorization
   ↓
Domain Operation
```

Frontend applications must not independently decide ownership or permissions.

For first-party browser applications, Laravel Sanctum session/cookie
authentication is the intended starting direction.

Exact authentication behavior must follow Laravel/Sanctum and browser security
requirements rather than assumptions made by frontend code.

---

## First-Party SitePro Authentication

For SitePro-controlled domains, the authentication architecture should provide
one coherent SitePro account experience.

Conceptually, a user may:

```text
Login
  ↓
Dashboard
  ↓
Choose Website
  ↓
Edit Website
```

without being asked to establish an unrelated second SitePro account/session.

The exact cookie domain and session configuration must be designed according
to:

- Laravel Sanctum behavior
- Secure cookies
- HttpOnly cookies where appropriate
- SameSite behavior
- CSRF protection
- deployment topology
- browser security requirements

Do not weaken browser security merely to simplify cross-application
authentication.

---

## Custom-Domain Editor Authentication

A SitePro authentication cookie scoped to the SitePro parent domain cannot
directly authenticate an unrelated customer custom domain.

For example:

```text
app.cmsplatform.com
```

cannot rely on its parent-domain cookie being available to:

```text
customer.com
```

Custom-domain editing therefore requires a secure editor-entry mechanism.

Conceptually:

```text
Authenticated Dashboard User
        ↓
Edit Website
        ↓
Laravel verifies:
User + Membership + Website + Permission
        ↓
Short-Lived One-Time Editor Handoff
        ↓
Customer Custom Domain
        ↓
Secure Handoff Verification / Exchange
        ↓
Authorized Editing Session
```

The exact handoff/session mechanism is intentionally deferred until
custom-domain editor authentication is implemented.

The implementation must consider:

- short expiration
- one-time consumption
- replay prevention
- secure cookies
- HttpOnly where applicable
- SameSite behavior
- CSRF protection
- origin/domain validation
- avoiding sensitive token leakage through URLs
- avoiding token leakage through logs, analytics, or referrers

Custom-domain editing must not bypass normal Laravel website authorization.

---

## API Boundary

Laravel provides the authoritative backend API used by the independently
deployed frontend applications.

API contracts should be explicit and predictable.

Frontend applications should not guess undocumented response structures or
business behavior.

Laravel should use established Laravel patterns such as:

- route model binding
- policies/authorization
- Form Requests where appropriate
- Eloquent/API Resources where appropriate
- transactions where consistency requires them

Do not automatically introduce repository, service, action, manager, adapter,
or provider layers for every operation.

Use additional application/domain abstractions when they provide a clear
responsibility or coordinate meaningful business behavior.

Controllers should coordinate HTTP behavior and should not become containers
for large amounts of domain logic.

Do not introduce a new API versioning structure unless the project architecture
actually requires it.

---

## AI Architecture

AI is an assistance layer over the SitePro CMS.

It must not become a privileged alternative backend.

Manual CMS operations must continue to work independently of AI availability
or customer AI allowance.

AI operates through controlled application actions.

Conceptually:

```text
User
  ↓
AI Conversation / Command
  ↓
AI Orchestrator
  ↓
Relevant Website Context
  ↓
Model
  ↓
Structured Action Plan
  ↓
Validation
  ↓
Authorization
  ↓
Approved Domain / Application Action
  ↓
Database / Draft State
```

AI changes must use the same authoritative business rules as equivalent manual
changes.

AI must respect:

- authentication
- account membership
- website authorization
- tenant isolation
- validation
- draft/published separation
- version/history behavior
- publishing rules
- AI allowance rules where applicable

AI must never:

- directly write to the database outside approved application operations
- generate and execute arbitrary SQL
- bypass Laravel authorization
- bypass tenant boundaries
- bypass validation
- silently publish significant changes
- expose another tenant's data through AI context
- make manual CMS functionality dependent on AI availability

AI provider integration may eventually use a provider-independent abstraction
when there is a concrete requirement.

Do not build unnecessary provider abstraction before AI integration requires
it.

---

## AI Scope

AI may operate at different website scopes.

Conceptually:

```text
Element AI
→ one selected element/content item

Section AI
→ one website section

Website AI
→ multiple sections/pages or broader website context
```

Broader AI scope does not grant broader authorization.

Every generated action must still operate only within the authenticated and
authorized Website context.

Multi-step AI operations should preserve appropriate successful work and
report partial failures according to approved product behavior.

Destructive, broad, or publishing-related AI operations require appropriate
preview/confirmation behavior.

---

## Caching

Caching must preserve tenant isolation.

Any cache containing website-specific data must include sufficient website,
domain, publication, or version context to prevent cross-tenant responses.

Do not use only a page path such as `/about` as a cache identity when multiple
websites can have that path.

Conceptually, cache identity may need to account for:

```text
Website / Domain
+
Published Version
+
Route
```

The exact cache implementation will be determined when public rendering and
publishing requirements make it necessary.

Do not introduce complex caching infrastructure prematurely.

Correct tenant resolution is more important than cache convenience.

---

## Security Boundaries

The following areas are security-sensitive:

- authentication
- sessions
- CSRF
- account membership
- roles and permissions
- tenant resolution
- custom domains
- editor handoff
- website ownership
- draft content
- publishing
- media access
- enquiries
- billing
- AI mutations
- AI context
- version/history access
- caching

Frontend restrictions are never sufficient security for these operations.

Laravel must enforce the authoritative backend rules.

Security-sensitive behavior should have appropriate automated tests.

---

## Frontend Data and State

Frontend applications should distinguish between:

1. persistent domain data
2. server/API-derived state
3. URL/navigation state
4. temporary UI/editor state

Persistent SitePro domain state belongs to the backend.

Do not create frontend state as an alternative source of truth for
authorization, ownership, publishing, or billing.

Use local/component state for temporary UI behavior where practical.

Introduce broader state management only when actual application behavior
requires it.

---

## Public Website Performance

The public Website runtime is customer-facing production infrastructure.

Public rendering performance is therefore a higher priority than editor
implementation convenience.

Where practical:

- public rendering should minimize unnecessary client JavaScript
- editor-only functionality should not be included for normal visitors
- published data should be suitable for caching
- tenant resolution should be efficient
- website metadata should render correctly
- public pages should remain accessible and SEO-friendly

The architecture should not require every public page view to execute the full
CMS editing stack.

---

## Shared Packages

The Platform, Dashboard, and Website applications are intentionally
independently deployable.

Do not create shared packages merely because similar code exists in multiple
applications.

Develop application-owned code within the application that owns the behavior.

A shared package may be introduced later only when stable, genuine
cross-application reuse has been demonstrated and the architectural benefit is
clear.

Do not move authentication, authorization, billing, tenant logic, or
Laravel-owned business rules into frontend shared packages.

There is no requirement to create a `packages/` directory for V1.

---

## Deployment Boundaries

Conceptually:

```text
Platform
cmsplatform.com
        |
        +------------------+
                           |
Dashboard                  |
app.cmsplatform.com        |
        |                  |
        +----------+-------+
                   |
                   v
            Laravel API
        api.cmsplatform.com
                   |
                   v
              PostgreSQL


Tenant Subdomains
*.cmsplatform.com
        |
        v
Website Application
        |
        +------> Laravel API / Published Data


Custom Domains
customer.com
        |
        v
Website Application
        |
        +------> Laravel API / Published Data
```

The exact hosting provider, CDN, edge runtime, queue infrastructure, object
storage provider, and deployment automation are operational decisions and
should not be assumed until selected.

Application architecture should avoid unnecessary coupling to a specific
hosting provider unless there is a demonstrated requirement.

---

## Product Specification Boundary

`docs/v1-product-spec.md` defines what SitePro V1 should do from the product
perspective.

This architecture document defines how the system is separated and which
technical boundaries must be preserved.

Generated UI/UX mockups are visual references.

Mockups must not be treated as authoritative sources for:

- pricing
- plan names
- AI limits
- storage limits
- account data
- domains
- feature restrictions
- unsupported business rules

When product behavior is not established by the product specification or
another approved requirement, implementation must not silently invent it.

The missing decision should be surfaced instead.

---

## Implementation Discipline

Before implementing a meaningful feature:

1. inspect the existing implementation
2. inspect relevant committed migrations/schema
3. follow the application's `AGENTS.md`
4. consult relevant product/architecture documentation
5. inspect relevant existing tests
6. make the smallest coherent change that satisfies the approved requirement

Do not create architecture merely because an AI coding agent commonly
generates it.

In particular, do not automatically create combinations of:

- repositories
- services
- managers
- factories
- adapters
- providers
- global stores
- generic hooks
- generic utilities

Use an abstraction when it has a clear responsibility and current value.

Do not modify unrelated code during a feature implementation.

Behavior changes should include appropriate tests.

---

## Architecture Change Rule

Major architectural changes should be discussed and documented before
implementation.

Coding agents must not silently replace established architecture with a
different pattern.

If implementation reveals that an established architecture decision is no
longer appropriate, treat that as an architecture discussion rather than
quietly working around it.

The preferred sequence is:

```text
Product Requirement
        ↓
Architecture Decision
        ↓
Implementation
        ↓
Tests
        ↓
Documentation Update where needed
```

Architecture should guide implementation without becoming unnecessary
ceremony.