# CMS SaaS

An AI-first SaaS website management platform designed for non-technical users.

The core product principle is:

> The website itself is the CMS.

Instead of managing content through a traditional CMS dashboard, website owners manage their website directly from the frontend.

## Applications

The repository contains four independently runnable applications:

- `apps/platform` — Public SaaS marketing/platform website
- `apps/dashboard` — Customer account portal
- `apps/website` — Customer website runtime and owner editing experience
- `backend/api` — Laravel central API and domain/business layer

## Repository Structure

cms-saas/
├── apps/
│   ├── platform/
│   ├── dashboard/
│   └── website/
├── backend/
│   └── api/
├── packages/
├── docs/
├── infrastructure/
├── AGENTS.md
└── README.md

## Technology Stack

### Frontend

- Next.js
- React
- TypeScript
- Tailwind CSS

### Backend

- Laravel
- PHP
- PostgreSQL

### Future Infrastructure

Introduced only when required:

- Redis
- Queues
- Object storage
- CDN
- Vector search / pgvector
- AI providers

## Local Development

Default local ports:

- Platform: `http://localhost:3000`
- Dashboard: `http://localhost:3001`
- Website: `http://localhost:3002`
- Laravel API: `http://localhost:8000`
- PostgreSQL: `localhost:5432`

Each application is currently managed independently.

## Documentation

See:

- `docs/product-principles.md`
- `docs/architecture.md`
- `docs/development-guide.md`
- `docs/roadmap.md`

## Development Philosophy

Build the product vertically rather than building every subsystem upfront.

The first major product flow is:

Create Site
→ Render Site
→ Owner Mode
→ Edit Content
→ Save Draft
→ Preview
→ Publish

Major architectural decisions should be documented before implementation.