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

---

## Phase 3 — Site Foundation

Goal:

Allow an authenticated user to create and access websites.

Initial scope:

- Site model
- Create site
- List user's sites
- Basic site settings
- Site ownership authorization
- Open site from Dashboard
- Resolve site in Website application

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