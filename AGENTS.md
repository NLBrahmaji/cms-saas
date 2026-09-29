# Agent Instructions

This repository contains an AI-first CMS SaaS application.

Before making significant changes, read:

- `README.md`
- `docs/product-principles.md`
- `docs/architecture.md`
- `docs/development-guide.md`
- `docs/roadmap.md`

## Core Product Principle

The website itself is the CMS.

Website owners should primarily manage their website directly from the website rather than through a traditional CMS administration interface.

## Repository Boundaries

- `apps/platform` — public SaaS/marketing application
- `apps/dashboard` — account management application
- `apps/website` — customer website runtime and owner editing experience
- `backend/api` — Laravel authoritative backend/domain/API
- `packages` — shared packages only when genuine reuse exists

## Architecture Rules

Laravel is the authoritative business/domain layer.

Do not place critical CMS business rules only in Next.js.

PostgreSQL is the primary application database.

The frontend must not determine authorization.

All protected operations must be validated and authorized by Laravel.

AI must never directly modify the database or execute arbitrary SQL.

The Website application serves many customer sites. Do not create one Next.js application per customer.

Public and owner website experiences should use the same underlying website rendering system wherever practical.

Owners edit drafts. Visitors see published content.

Do not introduce major architectural changes without discussion and documentation.

## Development Rules

Do not over-engineer future requirements.

Implement the smallest complete vertical slice required by the current milestone.

Do not add dependencies without a concrete requirement.

Use Laravel migrations for database schema changes.

Use TypeScript in Next.js applications.

Keep controllers and UI components focused.

Prefer clear code over premature abstractions.

Add tests for important business rules, authorization and critical flows.

## Current Development Direction

Follow `docs/roadmap.md`.

Do not implement later roadmap features unless they are required by the current task.

When implementation reveals an architectural conflict, stop and raise the issue instead of silently redesigning the system.