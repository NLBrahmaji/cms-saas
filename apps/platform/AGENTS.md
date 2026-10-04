# SitePro Platform Application

This application is the public marketing website for SitePro.

It is independent from:
- `apps/dashboard` — authenticated SitePro account/dashboard
- `apps/website` — customer website renderer and visual editor
- `backend/api` — Laravel API and authoritative domain layer

## Purpose

The platform application contains public SitePro pages such as:

- Home
- How It Works
- Themes
- Theme previews/details
- Features
- AI
- Pricing
- Resources
- Trial/signup entry points

Its primary concerns are:

- Marketing
- SEO
- Performance
- Accessibility
- Responsive design
- Clear product communication

Do not introduce dashboard or customer website/editor functionality into this application.

## Architecture

Use the Next.js App Router.

Keep `src/app` focused on:

- routing
- layouts
- metadata
- page composition

Do not place large implementations directly inside route files.

General structure:

src/
├── app/
├── components/
│   ├── ui/
│   ├── layout/
│   └── marketing/
├── features/
├── lib/
├── config/
└── types/

Do not create folders simply because they appear in this structure.
Create them only when they are needed.

## Components

`components/ui` is for genuinely reusable UI primitives.

Examples:

- Button
- Dialog
- Tabs
- Accordion

`components/layout` is for application-wide layout components.

Examples:

- Header
- Footer
- Container

`components/marketing` is for reusable marketing sections and presentation components.

Do not turn every section into a generic abstraction.

Prefer clear, specific components over highly configurable components with many props.

## Features

Use `features/` only when functionality has meaningful product behavior or its own domain logic.

Do not create a feature directory for every landing-page section.

For example, theme browsing/filtering may justify:

`features/themes`

A simple homepage CTA does not.

## Server and Client Components

Prefer Server Components by default.

Use `"use client"` only when browser-side behavior is actually required.

Do not make an entire page a Client Component because one small child component needs interactivity.

Keep client boundaries as small as practical.

## API and Data

Do not scatter `fetch()` calls throughout presentation components.

Shared HTTP/API infrastructure belongs in `lib/api`.

Feature-specific data operations should remain close to the feature that owns them.

Laravel is the authoritative backend for SitePro application/domain data.

Do not reproduce backend business rules in this application.

## Styling

Follow the approved SitePro visual direction and design references.

Generated design mockups are visual references, not authoritative business requirements.

Do not infer product rules, pricing, AI limits, trial conditions, or other business values from placeholder mockup content.

Use the product specification for product behavior.

## SEO

Public pages should use appropriate Next.js metadata capabilities.

Maintain semantic heading structure and meaningful page titles/descriptions.

Prefer server-rendered/indexable content for important marketing content.

Do not sacrifice SEO unnecessarily for client-side rendering.

## Accessibility

Use semantic HTML.

Interactive elements must be keyboard accessible.

Use buttons for actions and links for navigation.

Provide meaningful alternative text where appropriate.

Do not implement visual designs in ways that unnecessarily reduce accessibility.

## Dependencies

Do not add a package when the required behavior can reasonably be implemented using the existing stack.

Before adding a dependency:

1. Check existing dependencies.
2. Confirm the functionality does not already exist.
3. Add the dependency only when it provides meaningful value.

Do not introduce large UI frameworks or architectural libraries without an explicit requirement.

## Code Organization

Keep code close to the functionality that owns it.

Avoid generic dumping-ground files such as:

- helpers.ts containing unrelated helpers
- utils.ts containing business logic
- components.tsx containing unrelated components

Do not create abstraction layers such as:

- repositories
- managers
- factories
- providers
- adapters
- service layers

unless the actual problem requires them.

Do not create interfaces solely for the sake of having interfaces.

## Reuse

Avoid duplication within this application when there is a clear reusable concept.

Do not prematurely extract code for use by the other SitePro applications.

The platform, dashboard, and website applications are intentionally independent.

Shared packages should only be introduced later when stable, genuine cross-application reuse has been demonstrated.

## Changes

Before modifying existing functionality:

1. Inspect the relevant implementation.
2. Understand existing conventions.
3. Check related tests where applicable.
4. Make the smallest coherent change.

Do not rewrite unrelated code while implementing a feature.

Do not reorganize directories without a concrete reason.

Do not silently change established architecture.

## Product Requirements

Relevant project documentation is located under `/docs`.

Consult the documents relevant to the task rather than reading every document for every small change.

Product behavior should follow the approved product specification.

Product principles explain the intended SitePro experience.

Design references support implementation but do not override product requirements.

If requirements are unclear, do not invent business behavior.