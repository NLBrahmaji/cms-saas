# SitePro Website Application

This application serves customer websites and provides the SitePro visual
website editor.

It is independent from:

- `apps/platform` — public SitePro marketing website
- `apps/dashboard` — authenticated SitePro account/dashboard
- `backend/api` — Laravel API and authoritative domain layer

## Purpose

This application has two closely related responsibilities:

1. Render customer websites for public visitors.
2. Allow authorized website owners to edit those same websites visually.

A customer website may be served from:

- a SitePro temporary subdomain
- a customer-owned custom domain

The same application may serve many websites and hostnames.

Do not create a separate Next.js application per customer website.

## Core Architecture

The fundamental architecture is:

Laravel website data
        |
        v
Website Renderer
        |
        +------------------+
        |                  |
   Public Visitor     Authorized Editor
        |                  |
   Renderer only      Renderer + Editor

There must be ONE website rendering system.

Do not create:

- one renderer for public websites
- another renderer for editor previews

The editor must enhance the same renderer used by the public website.

This rule is critical.

## General Structure

The intended high-level structure is:

src/
├── app/
├── website/
│   ├── renderer/
│   ├── sections/
│   ├── header/
│   ├── footer/
│   ├── navigation/
│   └── theme/
├── editor/
├── features/
├── components/
│   └── ui/
├── lib/
│   ├── api/
│   ├── auth/
│   ├── tenant/
│   └── utils/
├── config/
└── types/

This is an architectural guide, not a requirement to create every
directory immediately.

Create directories only when actual functionality requires them.

## App Router

Use the Next.js App Router.

`src/app` should remain focused on:

- routing
- layouts
- hostname/request context
- page resolution
- metadata
- composition

Do not place the entire website renderer or editor implementation inside
route files.

Customer page routes may use a catch-all route where appropriate, but
routing decisions must follow the approved tenant/domain architecture.

## Tenant and Hostname Resolution

The incoming hostname identifies which customer website should be
rendered.

Tenant resolution must support the approved domain model, including:

- SitePro-managed temporary subdomains
- customer custom domains

Do not hardcode customer domains or website IDs in frontend code.

Do not trust browser-provided website IDs as authoritative tenant
identity when the request hostname is the source of website resolution.

Laravel remains authoritative for website/domain ownership and access.

Tenant resolution is infrastructure-sensitive code. Keep it explicit,
centralized, and testable.

Do not scatter hostname parsing throughout components.

## Public Renderer

Code under `website/` represents the public website rendering system.

It may contain concepts such as:

- page rendering
- sections
- header
- footer
- navigation
- themes/design
- forms

The public renderer must not depend on editor implementation.

Allowed dependency direction:

editor -> website

Forbidden dependency direction:

website -> editor

Public rendering components must remain usable without loading editor
behavior.

## Editor

The editor enhances the website renderer for authorized users.

Editor responsibilities may include:

- element selection
- section selection
- inline text editing
- editing overlays
- section toolbars
- add/remove/reorder actions
- design selection
- AI actions
- preview
- undo/history interaction
- publishing controls

Do not place these concerns inside the public renderer unless the
renderer genuinely requires the underlying non-editor behavior.

The editor should coordinate the renderer rather than replace it.

## Public Bundle Boundary

Normal website visitors should not unnecessarily download SitePro editor
functionality.

Keep editor-only functionality behind appropriate boundaries.

Avoid importing large editor modules into public rendering paths when
they are not required.

Do not make the entire customer website a Client Component merely to
support editing mode.

Prefer Server Components for public rendering where practical.

Introduce Client Components only where browser interaction requires them.

Performance decisions must consider that customer websites are public
production websites.

## Editor Entry and Authentication

Do not invent an independent login system for the website application.

Laravel owns SitePro authentication and authorization.

Temporary SitePro subdomains and custom domains may require different
session/handoff mechanisms.

Do not implement cross-domain authentication shortcuts without an
approved architecture.

For custom-domain editing, the expected architecture may involve a
short-lived secure editor handoff initiated from SitePro.

Any implementation involving:

- authentication tokens
- editor handoff tokens
- cookies
- cross-domain sessions
- CSRF
- session exchange

is security-sensitive and must follow the approved authentication design.

Never place long-lived credentials or sensitive authentication material
in localStorage.

Never expose sensitive tokens unnecessarily in URLs, logs, analytics, or
client state.

## Authorization

Only authorized users may enter editing mode or perform mutations.

The frontend may use permission information to control editor UI.

Laravel must independently authorize every protected operation.

Never treat:

- hidden buttons
- disabled controls
- editor route checks

as sufficient authorization.

## Draft and Published Content

Public visitors must receive the appropriate published website state.

Editors may work with draft/unpublished state.

Do not accidentally expose draft content to public visitors.

Do not blur draft and published data merely to simplify frontend code.

The exact publishing/version contract must follow the backend/product
specification.

If that contract has not yet been defined, do not invent one.

## Sections

Sections are reusable website content/presentation units.

A section type may support multiple designs.

Conceptually:

website/
└── sections/
    ├── hero/
    │   ├── hero-section.tsx
    │   └── designs/
    │       ├── classic.tsx
    │       ├── split.tsx
    │       └── centered.tsx
    ├── features/
    ├── testimonials/
    └── ...

Do not create all possible section types in advance.

Add section types as product implementation requires them.

Do not create separate section implementations for editor and public
rendering.

## Content and Design

Keep content and presentation conceptually separate where the product
requires it.

Changing a section design should not unnecessarily destroy or replace
its content.

Do not hardcode content into section design components when the content
belongs to website data.

A section design should primarily determine presentation.

## Theme

A Website Theme is the initial whole-site starting design.

Do not treat the selected theme as a permanent restriction.

After creation, users may change individual sections and website
appearance according to supported product behavior.

Do not build architecture that requires every section to remain tied to
the original theme forever.

## Editor Selection

Selection state belongs to the editor.

Examples include:

- selected element
- selected section
- active toolbar
- active editing surface

Do not add editor selection state to public website data.

Do not persist temporary editor UI state to Laravel unless there is an
actual product requirement.

## Editor State

Use the smallest state mechanism that satisfies the editor requirement.

Do not automatically place all editor state into one global store.

Separate conceptually different state when useful, such as:

- temporary UI state
- website draft data
- selection state
- mutation state
- history/version state

Do not duplicate authoritative Laravel data unnecessarily.

Avoid a single enormous editor state object that causes unrelated
components to depend on each other.

## Editor Components

Avoid creating one giant component such as:

WebsiteEditor.tsx

that owns every editor responsibility.

Prefer composition of focused responsibilities such as:

- EditorShell
- SelectionLayer
- InlineEditor
- SectionToolbar
- AddSection
- DesignPicker
- AI Assistant
- Preview
- PublishControls

These names are examples, not mandatory components.

Split by real responsibility, not arbitrary file size.

## API Communication

Do not scatter direct `fetch()` calls throughout renderer/editor
components.

Shared HTTP infrastructure belongs in:

lib/api/

Feature-specific API operations should remain close to the feature that
owns them.

Do not create one enormous API file containing every endpoint.

Do not invent API contracts.

Inspect the Laravel implementation/specification before integrating an
endpoint.

Laravel is authoritative for persistent website state.

## Mutations

Website mutations should have explicit behavior.

Examples include:

- update content
- add section
- remove section
- reorder section
- change design
- update navigation
- update website settings
- publish

Do not hide unrelated mutations inside generic "save everything"
functions unless the approved API contract requires it.

Handle:

- pending state
- success
- validation errors
- authorization errors
- conflicts where relevant
- network failures

Do not silently swallow mutation failures.

## Optimistic UI

Use optimistic updates only when they improve editing experience and can
be reconciled safely with backend state.

Do not assume every mutation will succeed.

When optimistic behavior is used, define rollback/reconciliation
behavior.

## AI

AI is an assistance layer over the CMS.

Core manual website management must continue to function independently
of AI availability or allowance.

AI functionality may operate at different scopes:

- element
- section
- page
- website

Do not mix AI provider/inference implementation directly into rendering
components.

The editor should request AI actions through the approved backend
architecture.

Do not allow AI-generated frontend code paths to bypass normal:

- validation
- authorization
- publishing
- version/history rules

AI changes are still normal SitePro changes.

## AI Change Behavior

Follow the approved product behavior:

- small, reversible, high-confidence changes may apply with Undo
- meaningful changes should generally provide preview
- ambiguous changes may ask a minimal clarification
- destructive or broad changes require appropriate confirmation
- successful parts of partially successful operations should not be
  discarded unnecessarily

Do not invent AI behavior when product requirements are undefined.

## Undo and History

Do not implement a second unrelated frontend-only versioning system if
Laravel provides authoritative website history/versioning.

Temporary UI undo and persistent website version history are different
concepts and should not be conflated.

Follow the approved backend contract for persistent versions.

## Publishing

Publishing is an explicit product operation.

Do not assume that every edit immediately becomes public.

The public renderer must respect published state.

The editor must clearly distinguish editing/draft state from what is
currently public when the product workflow requires it.

Do not implement publishing semantics independently of Laravel.

## Forms

Customer website forms are public website functionality.

Form rendering belongs to the website experience.

Form editing/configuration belongs to editor/feature behavior.

Form submissions must be handled through the approved backend contract.

Do not expose private enquiry information through public rendering
paths.

## SEO

Customer websites are public websites and SEO is important.

Use appropriate Next.js metadata/server rendering capabilities.

Metadata must correspond to the resolved website/page.

Do not allow one tenant's metadata to leak into another tenant's
response.

Keep important public content indexable where appropriate.

Editor-only routes/modes must not accidentally become indexable public
content.

## Caching

Caching must be tenant-aware and publishing-aware.

Never cache customer website data under a key that could return one
tenant's content to another tenant.

Do not introduce caching before understanding:

- hostname resolution
- published/draft boundaries
- invalidation after publish
- custom domain behavior

Caching changes in this application should be treated as architecture-
sensitive.

## Performance

Public website performance has higher priority than editor convenience.

Do not force public visitors to load:

- editor state management
- editing overlays
- AI interfaces
- design pickers
- history interfaces
- other owner-only functionality

unless technically necessary.

Use lazy/dynamic loading where it provides a meaningful boundary.

Do not optimize prematurely at the cost of unreadable architecture.

## Accessibility

Public websites and editor controls should use semantic HTML and
accessible interaction patterns.

Editor overlays must not unnecessarily break the semantics or keyboard
accessibility of the rendered website.

Use buttons for actions and links for navigation.

## Components

`components/ui` is for genuinely generic primitives used within this
application.

Website-specific rendering belongs under `website`.

Editor-specific UI belongs under `editor`.

Feature-specific UI belongs with its owning feature.

Do not turn `components/` into a dumping ground.

## TypeScript

Use explicit, understandable types at meaningful boundaries.

Avoid `any` unless there is a concrete reason.

Do not create highly generic type systems merely to remove small amounts
of duplication.

Website content structures should align with the actual backend
contract.

Do not invent fields because they would be convenient for a component.

## Dependencies

Before adding a dependency:

1. Inspect existing dependencies.
2. Determine whether the current stack already handles the requirement.
3. Confirm the dependency is appropriate for public website performance.
4. Add it only when it provides meaningful value.

Be especially careful with large dependencies imported into public
rendering paths.

Do not introduce state management, drag-and-drop, rich-text, AI, or UI
libraries merely because they are common in website builders.

## No Traditional Rich Text Assumption

SitePro is not intended to become a traditional CMS editor by default.

Do not introduce a large traditional rich-text editor unless an approved
feature explicitly requires it.

Prefer direct structured editing appropriate to the website element
being edited.

## Reuse

Reuse code inside this application where there is a clear reusable
concept.

Do not prematurely extract renderer/editor code into shared packages
with `platform` or `dashboard`.

These applications are intentionally independently deployable.

Cross-application sharing should only be introduced after genuine,
stable reuse has been demonstrated.

## Security

Treat the following as security-sensitive:

- tenant resolution
- authentication
- authorization
- custom-domain editor entry
- draft access
- publishing
- form submission
- file/media access
- AI mutations

Do not weaken backend security for frontend convenience.

Do not expose internal IDs, tokens, secrets, or private website data
without a product/API requirement.

## Changes

Before changing existing functionality:

1. Inspect the relevant renderer/editor/feature code.
2. Inspect the related Laravel contract.
3. Check existing conventions.
4. Check relevant tests.
5. Make the smallest coherent change.

Do not rewrite unrelated functionality.

Do not reorganize the architecture during unrelated feature work.

Do not silently introduce a second pattern for something already solved.

## Testing

Add or update tests when behavior changes.

Prioritize tests around:

- tenant/domain resolution
- public vs editor boundaries
- draft vs published behavior
- section rendering
- editor actions
- mutations and failures
- authorization-sensitive behavior
- publishing
- regressions

Do not create low-value tests solely to increase test count.

## Product Requirements

Relevant project documentation is under `/docs`.

Consult documents relevant to the current task rather than loading every
document for every small change.

The approved product specification is authoritative for product
behavior.

Product principles describe the intended SitePro editing experience.

Generated design images are visual references and do not define business
rules.

Do not infer unsupported:

- prices
- AI limits
- subscription behavior
- permissions
- publishing rules
- domain rules

from mockup placeholder content.

If a product or API requirement is unclear, do not invent it.