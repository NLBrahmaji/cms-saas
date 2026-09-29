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

---

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