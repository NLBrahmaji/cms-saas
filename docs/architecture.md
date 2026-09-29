# System Architecture

## Overview

CMS SaaS consists of multiple independently deployable applications backed by a central Laravel domain/API layer.

Conceptual production topology:

cmsplatform.com
→ Platform Next.js application

dashboard.cmsplatform.com
→ Dashboard Next.js application

*.cmsplatform.com
→ Website Next.js application

Custom domains
→ Website Next.js application

api.cmsplatform.com
→ Laravel API

Laravel
→ PostgreSQL

---

## Applications

### Platform

Location:

`apps/platform`

Responsibilities:

- SaaS marketing website
- product information
- pricing presentation
- public documentation/content where appropriate

The Platform application is not the CMS.

---

### Dashboard

Location:

`apps/dashboard`

Responsibilities:

- authentication UI
- My Websites
- account profile
- subscription
- billing
- usage
- account settings

The Dashboard should not become the primary website content editor.

---

### Website

Location:

`apps/website`

Responsibilities:

- render customer websites
- resolve websites based on hostname/site context
- public visitor experience
- owner editing layer
- preview experience
- interaction with CMS editing capabilities

One Website application serves many customer websites.

Do not create a separate Next.js application for every customer.

---

### Laravel API

Location:

`backend/api`

Laravel is the authoritative application/domain layer.

Responsibilities include:

- authentication
- authorization
- users
- sites
- pages
- content
- media
- navigation
- forms
- SEO
- drafts
- publishing
- versions
- AI action validation/execution
- subscriptions and usage when implemented

Important business rules should not exist only in Next.js.

---

## Database

PostgreSQL is the primary application database.

Redis may be introduced later for:

- caching
- queues
- sessions where appropriate
- real-time infrastructure

Do not introduce Redis until there is a concrete requirement.

---

## Site Ownership

Initial model:

User
├── Site A
├── Site B
└── Site C

Organizations and advanced team membership are not required for the initial version.

The architecture should not unnecessarily prevent future team functionality.

---

## Website Structure

Conceptually:

Site
├── Identity
├── Settings
├── Brand
├── Theme
├── Pages
│   └── Sections
│       └── Components
├── Content
├── Media
├── Navigation
├── Forms
├── SEO
├── AI Context
├── Versions
└── Publishing

Implementation details may evolve as the product is developed.

---

## Page Structure

Conceptual structure:

Page
└── Section
    ├── Layout
    └── Components

Sections represent meaningful website areas.

Examples:

- Hero
- Features
- Testimonials
- Pricing
- FAQ
- Contact
- CTA

Components represent elements within sections.

Examples:

- Heading
- Text
- Image
- Button
- Card
- Video
- Form
- Icon
- List

Avoid locking the system into rigid section implementations.

---

## Content Architecture

Content should be separated from page presentation.

Examples of content:

- Products
- Services
- Blog Posts
- Team Members
- Testimonials
- FAQs
- Events

Pages and components may reference content records.

This allows one content record to appear in multiple locations without duplication.

---

## Draft and Published Architecture

Owners edit drafts.

Visitors consume published content.

Conceptual flow:

Editing Model
→ Draft
→ Preview
→ Publish
→ Published Representation
→ Cache/CDN
→ Website Runtime

The long-term published website should not require expensive database/API work for every visitor request.

The exact snapshot/cache implementation will be determined during publishing development.

---

## Authentication

Laravel owns authentication and authorization.

Next.js applications consume Laravel authentication.

Conceptually:

Next.js
→ Laravel Authentication
→ Session/User
→ Authorization
→ Domain Operation

The frontend must not independently decide ownership or permissions.

For first-party browser applications, Laravel Sanctum session/cookie authentication is the intended starting direction.

Custom-domain owner authentication will be designed separately when custom-domain support is implemented.

---

## AI Architecture

AI must operate through controlled application actions.

Conceptually:

User
→ AI Conversation
→ AI Orchestrator
→ Context
→ Model
→ Structured Action Plan
→ Validation
→ Authorization
→ Laravel Service Layer
→ Database

AI must never:

- directly write to the database
- generate and execute arbitrary SQL
- bypass Laravel authorization
- silently publish significant changes

AI providers should eventually be accessed through a provider-independent service abstraction.

---

## Shared Packages

`packages/` is reserved for code that genuinely needs to be shared.

A possible future package is:

`packages/website-engine`

Potential responsibilities:

- rendering concepts
- component definitions
- sections
- layouts
- theme/design tokens

Do not move authentication, billing or core backend business logic into the website engine.

Shared packages should be introduced when actual duplication or reuse justifies them.

---

## Architecture Change Rule

Major architectural changes should be discussed and documented before implementation.

Do not allow coding agents to silently replace established architecture with a different pattern.