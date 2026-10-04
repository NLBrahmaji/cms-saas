# SitePro Dashboard Application

This application is the authenticated SitePro customer dashboard.

It is independent from:

- `apps/platform` — public SitePro marketing website
- `apps/website` — tenant website renderer and visual editor
- `backend/api` — Laravel API and authoritative domain layer

## Purpose

The dashboard manages the customer's SitePro account and websites.

Typical areas include:

- My Websites
- Account/Profile
- Subscription and Billing
- Domains
- AI Usage
- Notifications
- Enquiries
- Website History
- Security
- Help and Support

Website content editing itself belongs in `apps/website`, not here.

## Architecture

Use the Next.js App Router.

Keep `src/app` focused on:

- routing
- layouts
- authentication boundaries
- metadata where applicable
- page composition

Application functionality belongs primarily under `src/features`.

General structure:

src/
├── app/
├── features/
├── components/
│   ├── ui/
│   └── layout/
├── lib/
│   ├── api/
│   ├── auth/
│   └── utils/
├── config/
└── types/

Do not create directories merely because they appear in this example.
Create them when actual implementation requires them.

## Feature Ownership

Organize application functionality by product feature.

Examples:

features/
├── auth/
├── websites/
├── account/
├── billing/
├── domains/
├── ai-usage/
├── notifications/
├── enquiries/
├── website-history/
└── support/

A feature owns the UI and frontend behavior specific to that feature.

For example:

features/enquiries/
├── components/
├── api/
├── hooks/
└── types.ts

This is an example, not a mandatory template.

Do not automatically create `components`, `api`, `hooks`, `services`,
`schemas`, `stores`, `utils`, and `types` for every feature.

Start with the smallest structure required by the feature and expand it
only when necessary.

## Route Files

Route files should remain thin.

A route should primarily:

- establish route-level boundaries
- obtain required route parameters
- compose feature components
- perform appropriate server-side loading where useful

Do not build large dashboard screens directly inside `page.tsx`.

## Laravel Authority

Laravel is the authoritative backend and domain layer.

Laravel owns authoritative rules for:

- authentication
- authorization
- memberships
- permissions
- websites
- domains
- subscriptions
- billing state
- publishing state
- AI usage/allowances
- enquiries
- other persistent business rules

Do not reproduce authoritative backend business rules in Next.js.

Frontend validation exists for user experience.
Backend validation remains authoritative.

Never rely on hiding or disabling UI as an authorization mechanism.

## Authentication

Do not invent a separate authentication system inside the dashboard.

Authentication must follow the SitePro Laravel authentication/session
architecture.

Do not store sensitive authentication credentials in localStorage.

Authentication-sensitive behavior must respect the security architecture
defined for the project.

The dashboard and website editor may participate in the same SitePro
account experience, but do not implement cross-domain authentication
mechanisms without an approved design.

## Authorization

The frontend may use permission information returned by Laravel to decide
what controls to display.

Laravel must independently enforce authorization for every protected
operation.

Never assume that because a button is hidden, an operation is protected.

## API Communication

Do not scatter direct `fetch()` calls throughout React components.

Shared HTTP behavior belongs in:

lib/api/

Examples include:

- base URL handling
- credentials/session configuration
- common response handling
- standardized API errors

Feature-specific API operations belong with the feature that owns them.

Example:

features/enquiries/api/get-enquiries.ts

Do not create one enormous global API file containing every SitePro
endpoint.

Do not create unnecessary repository/service layers around simple API
operations.

## API Contracts

Do not guess Laravel response structures.

Inspect the existing backend API contract before implementing frontend
integration.

Keep frontend request and response types aligned with the actual API.

Do not silently compensate for inconsistent backend responses inside UI
components.

If the API contract needs to change, treat that as an explicit
cross-application change.

## Server and Client Components

Prefer Server Components where they provide a clear benefit.

Use `"use client"` only when browser-side behavior is required.

Examples include:

- interactive forms
- dialogs
- client-side state
- browser APIs
- immediate interactive controls

Do not convert an entire route tree to Client Components merely because
one component requires client-side behavior.

Keep client boundaries reasonably small.

## State

Use the smallest appropriate state mechanism.

Prefer:

1. URL/search parameters for shareable navigation state.
2. Server/API data for persistent state.
3. Local component state for local UI behavior.
4. Feature-level state only when multiple components genuinely need it.

Do not introduce global state management merely because the application
has many screens.

Do not duplicate Laravel data unnecessarily into global client stores.

## Forms

Forms should provide clear frontend validation and useful error feedback.

Laravel validation remains authoritative.

Handle validation errors returned by the API and display them near the
relevant fields where practical.

Prevent accidental duplicate submissions.

Clearly represent loading, success, and failure states.

Do not silently swallow API errors.

## Loading and Error States

Features that depend on remote data must intentionally handle:

- loading
- empty
- success
- validation failure
- request failure

Do not assume successful network requests.

User-facing errors should be understandable and should not expose
internal implementation details.

## Components

`components/ui` contains genuinely reusable UI primitives.

Examples:

- Button
- Input
- Select
- Dialog
- Tabs
- Table

`components/layout` contains dashboard-wide layout components.

Examples:

- DashboardShell
- DashboardHeader
- DashboardNavigation

Feature-specific components belong inside their feature.

For example, an enquiry details panel belongs under `features/enquiries`,
not global `components`.

## Reuse

Reuse code within this application when there is a clear reusable concept.

Do not prematurely extract code into shared packages for `platform`,
`dashboard`, and `website`.

The three applications are intentionally independently deployable.

Cross-application sharing should be introduced only when genuine,
stable reuse has been demonstrated.

## TypeScript

Use clear TypeScript types for application boundaries and meaningful
domain data.

Avoid `any` unless there is a concrete reason.

Do not create complicated generic abstractions merely to reduce a few
lines of repeated typing.

Prefer readable explicit types over clever type systems.

## Dependencies

Before adding a dependency:

1. Inspect existing dependencies.
2. Determine whether the existing stack already solves the problem.
3. Add the dependency only when it provides meaningful value.

Do not introduce new:

- state libraries
- form libraries
- data-fetching libraries
- UI frameworks
- validation libraries

without first confirming they are needed and consistent with the
application architecture.

## Code Organization

Keep code close to the feature that owns it.

Avoid dumping unrelated functionality into generic files such as:

- helpers.ts
- utils.ts
- services.ts
- hooks.ts

Do not create architectural layers simply because they are common in
other projects.

Avoid unnecessary:

- repositories
- managers
- factories
- adapters
- providers
- service classes

Use them only when the actual implementation benefits from them.

## Component Size

Split components when they contain clearly separate responsibilities.

Do not split components solely to satisfy an arbitrary line-count rule.

Avoid giant page or feature components that combine:

- data access
- forms
- dialogs
- tables
- mutations
- navigation
- unrelated UI behavior

into one file.

## Security

Treat account, billing, domain, membership, and authentication operations
as security-sensitive.

Do not expose secrets or sensitive tokens to browser code.

Do not log sensitive account or authentication information.

Do not weaken Laravel authorization or validation for frontend
convenience.

Security-sensitive architecture changes require deliberate review.

## Changes

Before modifying existing functionality:

1. Inspect the relevant feature.
2. Inspect related API integration.
3. Check existing conventions.
4. Check relevant tests.
5. Make the smallest coherent change.

Do not rewrite unrelated functionality.

Do not reorganize directories during unrelated feature work.

Do not silently introduce a new architectural pattern.

## Testing

Add or update tests when behavior changes.

Prioritize tests around:

- important user workflows
- forms
- permissions-related UI behavior
- API integration boundaries
- error handling
- regressions

Do not create low-value tests merely to increase test count.

## Product Requirements

Relevant project documentation is located under `/docs`.

Consult the documents relevant to the task instead of loading every
project document for every change.

The approved product specification is authoritative for product behavior.

Product principles describe the intended SitePro experience.

Generated design references are visual references only.

Do not infer:

- prices
- subscription limits
- AI allowances
- trial conditions
- permissions
- business rules

from placeholder design content.

If a business requirement is unclear, do not invent it.