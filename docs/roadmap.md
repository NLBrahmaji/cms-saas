# Development Roadmap

This roadmap describes development order, not fixed release dates.

Implementation details may change as each milestone is designed.

## Phase 1 — Foundation

Completed / in progress:

- Repository structure
- Laravel installation
- PostgreSQL configuration
- Next.js Platform application
- Next.js Dashboard application
- Next.js Website application
- Architecture documentation
- Development rules

---

## Phase 2 — Authentication Foundation

Goal:

Establish Laravel-controlled authentication for the SaaS applications.

Initial scope:

- Register
- Login
- Logout
- Current authenticated user
- Protected Dashboard
- Laravel authorization foundation

Do not build advanced roles/teams unless required.

Backend authentication is implemented. Frontend authentication UI and the
protected Dashboard are still pending.

---

## Phase 2A — Account Foundation

Implemented backend scope:

- Account as the SaaS tenant/ownership boundary
- Account and account-membership models and relationships
- Atomic user, initial account, and owner-membership onboarding
- Authenticated account listing and retrieval
- Active-membership access checks, owner/member distinction, and tenant-isolation tests

No account editing, invitations, member management, or frontend implementation.

## Phase 2B — Account Authorization Foundation

Implemented backend scope:

Authenticated User + Active Account Membership + Account Context + Spatie Team
Context + Account-scoped Roles/Permissions.

- Validated account context mapped to Spatie's existing team ID
- Account-scoped owner/admin/member roles and three account permissions
- Transactional owner-role assignment during registration
- Permission-gated authorization-info endpoint
- Explicit per-account bootstrap command for existing data
- Multi-account isolation, membership revocation, cache, and cleanup tests

Website Foundation extends this mapping with four website permissions.

---

## Phase 3 — Site Foundation

Goal:

Allow an authenticated account member to create and access account-owned websites.

Implemented backend Website Foundation:

- Website model and Account ↔ Website relationships
- Account-scoped list, create, show, PATCH, and soft-delete endpoints
- Owner/admin website CRUD permissions; member read permission
- Scoped nested binding, resource policy, and active membership enforcement
- Identity validation, subdomain normalization/uniqueness, clean resources
- Tenant isolation, role switching, revocation, lifecycle, and validation tests

Pending: opening sites from Dashboard and website runtime
resolution. No frontend, domain routing, content, or publishing is implemented.

Website Settings Foundation is implemented:

- Website has-one settings with database uniqueness
- Atomic website/settings initialization and read-only defaults for legacy sites
- Account/website-scoped GET/PATCH reusing website.view and website.update
- Fixed address/social JSON contracts, null clearing, and partial key updates
- Validation, isolation, role switching, lifecycle, and rollback tests

Recommended next backend milestone: a narrow website SEO-settings configuration
slice using the existing schema, initially excluding media-dependent image
management and publishing. Design that contract before implementation. Branding,
SEO, content, and publishing remain unimplemented in this milestone.

---

## Phase 4 — Basic Website Rendering

Goal:

Render a real customer website through the Website application.

Initial scope:

- basic page
- basic sections
- basic components
- site theme foundation
- hostname/site resolution

Avoid building the full component library upfront.

---

## Phase 5 — Owner Mode

Goal:

Allow a website owner to manage the website directly from the website.

Initial scope:

- identify authorized owner
- owner toolbar
- element selection
- inline heading/text editing
- save draft

---

## Phase 6 — Draft, Preview and Publish

Goal:

Separate owner changes from the live website.

Initial scope:

- draft state
- unsaved/change indicators
- preview
- publish
- published representation
- basic version history
- rollback foundation

At the end of this phase, the first major vertical slice should work:

Create Site
→ Render Site
→ Owner Mode
→ Edit Heading
→ Save Draft
→ Preview
→ Publish

---

## Later Product Areas

Designed and implemented incrementally:

- section management
- layouts
- responsive controls
- design system
- navigation
- media
- forms
- reusable content
- dynamic content
- dynamic pages/templates
- SEO
- AI assistant
- AI change sets
- version history
- subscriptions
- usage tracking
- billing
- custom domains
- teams/permissions
- analytics
- caching/CDN
- queues
- advanced publishing infrastructure

These are intentionally not fully scheduled yet.
