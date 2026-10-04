# Development Roadmap

## Purpose

This roadmap defines the intended SitePro V1 development sequence.

It describes development order, not fixed release dates.

The roadmap should evolve when approved product or architecture decisions
change.

The goal is to build SitePro through working vertical slices rather than
completing every backend subsystem before building usable product workflows.

`docs/v1-product-spec.md` defines the V1 product scope.

`docs/architecture.md` defines the major system boundaries.

This roadmap defines the order in which that product should be implemented.

---

## Current Starting Point

The project foundation exists.

Current repository structure:

```text
cms-saas/
├── apps/
│   ├── platform/
│   ├── dashboard/
│   └── website/
├── backend/
│   └── api/
└── docs/
```

Existing foundation includes:

- repository/monorepo structure
- Laravel application
- PostgreSQL development direction/configuration
- Platform Next.js application
- Dashboard Next.js application
- Website Next.js application
- database migration architecture
- product principles
- V1 product specification
- system architecture
- development guide
- application-specific `AGENTS.md` guidance
- Cursor project/application rules

Previous experimental/backend implementation should not be assumed to exist.

The committed migrations remain the database architecture baseline.

---

# Phase 1 — Project Foundation

## Goal

Establish a predictable repository and development environment before product
implementation begins.

## Scope

- repository structure
- Laravel application
- PostgreSQL
- three Next.js applications
- local development ports
- environment configuration foundation
- Cursor rules
- application `AGENTS.md`
- product documentation
- architecture documentation
- development rules

## Status

Foundation/preparation phase.

Complete the documentation/rules consistency review before beginning feature
implementation.

---

# Phase 2 — Authentication and Account Foundation

## Goal

Establish Laravel-controlled authentication and the Account tenant boundary.

## Backend Scope

Implement the smallest coherent authentication/account foundation required by
the product.

Expected scope includes:

- registration
- login
- logout
- current authenticated user
- Laravel Sanctum browser authentication
- CSRF/session behavior
- Account model/domain behavior based on existing schema
- Account Membership behavior
- initial account onboarding
- account-scoped authorization foundation
- baseline roles/permissions
- tenant-isolation tests

Registration should establish the required initial Account and owner
membership consistently.

Do not implement advanced member-management workflows merely because the
authorization foundation exists.

## Dashboard Scope

Implement the minimum frontend authentication flow required to:

```text
Register / Login
        ↓
Authenticated Dashboard
        ↓
Current Account Context
```

The Dashboard does not need to be feature-complete during this phase.

## Completion Outcome

A user can create/access their SitePro account and reach an authenticated
Dashboard using Laravel-controlled authentication.

---

# Phase 3 — Website Foundation

## Goal

Allow an authorized Account to create and manage basic Website records.

## Backend Scope

Implement Website behavior using the existing database architecture.

Expected scope includes:

- Account → Websites relationship
- website listing
- website creation
- website retrieval
- supported website updates
- supported deletion behavior
- website permissions
- tenant isolation
- website settings foundation
- baseline settings initialization
- validation
- API resources/contracts
- tests

Do not implement publishing simply because Website has publishing-related
database fields.

Do not build content, media, forms, or domains during this phase unless needed
for the basic Website vertical slice.

## Dashboard Scope

Implement the initial **My Websites** experience.

The user should be able to:

- view their websites
- create a website through the supported initial workflow
- select/open a website

Theme/onboarding depth can be introduced incrementally as required by the
approved creation experience.

## Completion Outcome

```text
Authenticated User
        ↓
Account
        ↓
My Websites
        ↓
Create Website
        ↓
Website Exists
```

---

# Phase 4 — Public Website Renderer

## Goal

Render a real customer website through the Website Next.js application.

This establishes the core runtime before building the complete editor.

## Scope

Implement the smallest renderer capable of demonstrating the architecture.

Expected scope includes:

- Website application routing
- basic Website resolution
- initial tenant/site context
- basic Page representation
- basic Section representation
- initial Section rendering
- initial theme/design foundation
- public rendering
- published/public data boundary foundation
- loading/not-found/error behavior
- tests around tenant resolution and rendering

Avoid building the entire Section library.

One or two representative section types are enough to establish the renderer.

## Architecture Requirement

The renderer must be suitable for both:

```text
Public Visitor
```

and later:

```text
Authorized Owner + Editor
```

Do not build a separate renderer specifically for editing.

## Completion Outcome

A Website created in SitePro can be rendered through the Website application.

---

# Phase 5 — Website-First Editor Foundation

## Goal

Allow an authorized owner to edit the website from the website itself.

## Scope

Build the minimum editor layer over the existing Website Renderer.

Expected scope includes:

- authorized owner/editor detection
- editor mode
- minimal SitePro editor bar
- element/section selection
- contextual editing controls
- inline heading/text editing
- explicit save behavior
- draft persistence foundation
- error handling
- appropriate authorization
- tests

Editor state must remain separate from persistent website data.

Public visitors must not receive editor controls.

## Completion Outcome

The first editing workflow should work:

```text
Open Website
     ↓
Enter Authorized Editor Mode
     ↓
Select Heading
     ↓
Edit Heading
     ↓
Save Draft
```

This is the first major proof of the website-first CMS architecture.

---

# Phase 6 — Draft, Preview and Publish

## Goal

Complete the first end-to-end website lifecycle.

## Scope

Implement the initial publishing architecture.

Expected scope includes:

- draft state
- persisted website changes
- change/unsaved indicators where appropriate
- Preview
- explicit Publish
- published representation
- public/draft isolation
- basic website history/version foundation
- immediate Undo where appropriate
- basic rollback/recovery direction
- tenant-aware invalidation/caching foundation where required

Do not introduce advanced publishing infrastructure before the basic lifecycle
works.

## First Major Vertical Slice

At the end of this phase, SitePro should support:

```text
Create Website
      ↓
Render Website
      ↓
Owner Editor Mode
      ↓
Edit Heading
      ↓
Save Draft
      ↓
Preview
      ↓
Publish
      ↓
Public Visitor Sees Published Change
```

This vertical slice should be stable before significantly expanding the editor.

---

# Phase 7 — Pages, Sections and Designs

## Goal

Expand the editor from a single editing proof into practical website
management.

## Pages Scope

Implement supported:

- page listing/management
- page creation
- page naming/details
- page deletion according to approved rules
- page ordering where required
- route/slug behavior
- page navigation relationship where required

Include the approved **Create New Page** experience.

AI-assisted page creation may be introduced later with the AI milestone unless
a non-AI foundation is needed first.

## Sections Scope

Implement supported:

- Add Section
- remove Section
- reorder Sections
- edit Section content
- Section selection
- Section controls

Introduce Section types incrementally.

Do not build every possible section before real workflows require them.

## Design Scope

Implement:

- supported Section Designs
- Design selection
- compatible content preservation when changing Design
- responsive behavior for supported Designs

Keep content conceptually separate from presentation.

## Completion Outcome

A non-technical customer can meaningfully construct and rearrange a basic
website without understanding a technical component tree.

---

# Phase 8 — Header, Footer and Navigation

## Goal

Allow customers to manage the website's primary structural navigation areas
directly from the website.

## Header Scope

Implement supported:

- direct header selection
- Edit Menu
- Change Design
- supported header layouts
- CTA/header content where applicable

Possible supported designs may include:

- Classic
- Centered
- Split
- CTA Header
- Simple Dropdown
- Mega Menu
- Mega Menu with Images
- Mega Menu with Featured Content

The final V1 catalogue should be implemented incrementally rather than assumed
from this list.

## Navigation Scope

Implement supported:

- navigation links
- ordering
- page association
- nesting
- menu editing

Changing header Design should preserve compatible navigation content.

## Footer Scope

Implement supported:

- footer content
- footer navigation
- business information
- social links
- footer Designs

## Completion Outcome

The customer can manage the main website structure without leaving the
website-first editing experience.

---

# Phase 9 — Media and Business Details

## Goal

Provide the reusable website information and media workflows required for
normal website editing.

## Media Scope

Implement the approved V1 media workflow, including supported:

- upload
- selection
- image replacement
- media management
- website/account ownership
- public delivery

Storage/transformation infrastructure should be introduced according to actual
requirements.

## Business Details Scope

Provide central management for supported reusable website information such as:

- site/business name
- tagline
- contact email
- contact phone
- address
- social links

Integrate these values into supported website sections/header/footer where
appropriate.

## Completion Outcome

Users can manage common business information and website images without
technical filesystem concepts.

---

# Phase 10 — Forms and Enquiries

## Goal

Allow SitePro websites to collect visitor enquiries.

## Website Scope

Implement supported form editing/configuration through the website editing
experience.

## Public Scope

Implement secure public form submission.

Include appropriate:

- validation
- tenant/site association
- spam/abuse protection direction
- success/failure behavior

## Dashboard Scope

Implement:

- Enquiries list
- Enquiry Details

Enquiry information must remain private.

## Completion Outcome

```text
Visitor
  ↓
Published Website Form
  ↓
Submit
  ↓
SitePro
  ↓
Customer Enquiries
```

---

# Phase 11 — Search & Sharing

## Goal

Provide the V1 customer-facing SEO and sharing controls.

## Scope

Implement approved:

- site/page title information
- descriptions
- search presentation settings
- social-sharing metadata
- Website editor/settings UX
- published metadata rendering

Use customer-friendly language.

Do not expose unnecessary technical SEO complexity.

Do not build an advanced SEO platform unless the V1 product specification is
expanded to require it.

---

# Phase 12 — AI Website Assistant

## Goal

Add AI as a first-class assistance layer over the working CMS.

AI should be introduced after the underlying manual CMS actions exist.

## Foundation

Implement:

- AI Website Assistant UI
- AI command flow
- relevant website context
- controlled structured actions
- validation
- authorization
- preview/apply behavior
- Undo/history integration
- AI usage tracking foundation where required

## AI Scopes

Support the approved progression:

```text
Element AI
↓
Section AI
↓
Website AI
```

Examples:

```text
Rewrite this heading.
```

```text
Make this section more convincing.
```

```text
Make the whole website more professional.
```

## Change Behavior

Implement the approved confidence/scope behavior:

```text
Small + reversible + high confidence
→ Apply + Undo

Meaningful change
→ Preview

Ambiguous request
→ Minimal clarification

Broad/destructive change
→ Plan + confirmation
```

## Multi-Step AI

Introduce supported multi-section/page actions.

Handle partial success without unnecessarily discarding successful work.

AI must use the same authorized SitePro operations as manual editing.

AI must not become a separate privileged CMS.

## Completion Outcome

A user can make useful website changes through natural-language instructions
while retaining visual control and reversibility.

---

# Phase 13 — Domains

## Goal

Allow supported customers to use custom domains.

## Scope

Define and implement the approved domain lifecycle.

Expected areas include:

- SitePro temporary domain behavior
- custom-domain entry
- ownership/verification
- activation
- routing
- SSL readiness
- domain status
- removal/change behavior
- tenant resolution
- error states

## Custom-Domain Editing

Implement the secure editor-entry/handoff architecture for unrelated customer
domains.

This must be designed as a security-sensitive workflow.

Do not attempt to share a SitePro parent-domain authentication cookie directly
with an unrelated custom domain.

## Completion Outcome

A supported Website can be served securely through its verified custom domain,
and an authorized owner can enter the SitePro editor without creating a second
account.

---

# Phase 14 — Dashboard and Account Experience

## Goal

Complete the V1 SaaS account-management experience around the website editor.

Some Dashboard capabilities will already exist from earlier milestones.

This phase completes and refines the broader experience.

## Scope

Complete supported:

- My Websites
- Account/Profile
- Domains
- Notifications
- Enquiries
- Website History
- Security
- Help & Support
- relevant account settings

The Dashboard should remain lightweight relative to the website editor.

Do not move normal website content editing into Dashboard forms merely because
the Dashboard already exists.

---

# Phase 15 — Trial, Subscription, Billing and AI Usage

## Goal

Add the commercial controls required to operate SitePro as a SaaS product.

## Scope

Once commercial rules are approved, implement supported:

- trial state
- subscription state
- plan behavior
- billing integration
- billing UI
- AI usage/allowance
- applicable feature limits
- applicable website limits
- applicable domain restrictions

## Important Constraint

The following must not be invented from mockups:

- exact trial duration
- payment-card requirement
- plan names
- prices
- billing intervals
- AI limits
- storage limits
- website limits
- plan-specific restrictions

Resolve the commercial product decisions before implementing them as fixed
business rules.

Manual CMS editing must remain independent of AI allowance.

---

# Phase 16 — Mobile Editing and V1 Hardening

## Goal

Prepare the complete V1 workflow for production use.

## Mobile Editing

Implement/refine the deliberately simplified mobile editor.

Prioritize common operations rather than forcing the entire desktop editor
onto a small screen.

Verify responsive behavior for:

- public websites
- editor overlays
- contextual controls
- AI Assistant
- page/section operations
- forms
- publishing

## V1 Hardening

Perform focused review of:

- authentication
- authorization
- tenant isolation
- custom domains
- draft/public separation
- publishing
- cache isolation
- media access
- form abuse/security
- enquiries privacy
- AI authorization/context
- billing boundaries
- accessibility
- SEO
- performance
- error handling
- responsive behavior

## Testing

Complete appropriate:

- backend feature tests
- authorization tests
- tenant-isolation tests
- frontend interaction tests
- critical workflow tests
- publishing tests
- domain-resolution tests
- AI action tests
- regression coverage

## Completion Outcome

The complete supported V1 lifecycle works coherently.

---

# V1 Target Lifecycle

The roadmap should ultimately deliver this customer journey:

```text
Create SitePro Account
        ↓
Create Website
        ↓
Choose Starting Theme
        ↓
Receive Temporary Website
        ↓
Open Actual Website
        ↓
Edit Directly
        ↓
Manage Pages / Sections / Designs
        ↓
Manage Header / Menu / Footer
        ↓
Manage Content / Media / Business Details
        ↓
Configure Forms
        ↓
Configure Search & Sharing
        ↓
Use Manual Editing or AI
        ↓
Preview
        ↓
Publish
        ↓
Receive Enquiries
        ↓
Manage Account / Website
        ↓
Connect Custom Domain when supported
```

The roadmap should prioritize completing this lifecycle over adding isolated
advanced features.

---

# Implementation Strategy

Within each phase:

1. confirm the relevant product requirement
2. review relevant UX/design
3. review architecture
4. inspect existing code/schema
5. define the smallest coherent vertical slice
6. implement backend behavior where required
7. implement frontend behavior where required
8. test
9. review the real workflow
10. expand incrementally

Do not implement an entire subsystem merely because a future phase may need it.

---

# Vertical Slice Principle

The roadmap is organized into phases for clarity, but development should remain
vertical whenever practical.

For example, do not spend months building every possible CMS content model
before rendering and editing a real website.

The first major milestone is:

```text
Account
  ↓
Website
  ↓
Render
  ↓
Edit
  ↓
Draft
  ↓
Preview
  ↓
Publish
```

Once that works reliably, expand the CMS around the proven architecture.

---

# Deferred / Future Areas

The following are not automatically required for V1 unless separately
approved:

- plugin marketplace
- third-party extension ecosystem
- arbitrary custom code execution
- unrestricted HTML/CSS/JavaScript editor
- full developer page builder
- advanced reusable content modeling
- full blogging platform
- full e-commerce platform
- full CRM
- advanced marketing automation
- multilingual website system
- complex editorial approval workflows
- advanced analytics platform
- arbitrary customer-defined content schemas
- premature shared frontend packages

Future features should be added because the product requires them, not because
other CMS products contain them.

---

# Roadmap Change Rule

This roadmap is directional rather than immutable.

A phase may be adjusted when implementation reveals a genuine dependency or
when an approved product decision changes.

However, coding agents must not silently expand scope or reorder major product
architecture.

Meaningful roadmap changes should be discussed and reflected in this document.

The preferred progression remains:

```text
Foundation
    ↓
Authentication / Account
    ↓
Website
    ↓
Renderer
    ↓
Editor
    ↓
Publishing
    ↓
Broader CMS
    ↓
AI
    ↓
Commercial / Production Hardening
```