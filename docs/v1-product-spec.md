# SitePro V1 Product Specification

## 1. Purpose

SitePro is an AI-first CMS SaaS designed to allow non-technical users to
create, manage, and update websites without needing to understand traditional
CMS terminology or workflows.

The primary SitePro experience is the website itself.

Users should be able to look at their website and make changes directly,
rather than navigating through a traditional CMS administration interface.

SitePro follows this interaction priority:

1. AI assistance
2. Direct visual editing
3. Advanced/manual controls when required

The goal is not to reproduce WordPress, Joomla, or another traditional CMS
with a different interface.

---

## 2. V1 Product Goals

SitePro V1 must allow a customer to:

- Create a SitePro account.
- Create and manage one or more websites subject to their account/plan.
- Start a website using an available Website Theme.
- Receive a temporary SitePro website domain.
- View and manage their websites from the SitePro dashboard.
- Open a website and edit it directly from the website experience.
- Edit website text and supported content directly.
- Add, remove, reorder, and update supported website sections.
- Change the design of supported sections without unnecessarily replacing
  their content.
- Manage pages and website navigation.
- Manage header and footer content/design.
- Manage website images and supported media.
- Manage business/contact information.
- Configure supported website settings.
- Configure supported search and sharing information.
- Create and manage supported website forms.
- Receive and view form enquiries.
- Use SitePro AI to assist with website changes.
- Preview changes before publishing where appropriate.
- Publish website changes.
- Access supported website change/version history.
- Undo supported changes.
- Connect a custom domain when the feature is available to the customer's
  account/plan.
- Manage their SitePro account, subscription-related information, AI usage,
  security, and support areas provided by V1.

---

## 3. Product Surfaces

SitePro V1 consists of three customer-facing application surfaces.

### 3.1 SitePro Platform

The public SitePro website.

It communicates the product and may contain:

- Home
- How It Works
- Themes
- Theme details/previews
- Features
- AI
- Pricing
- Resources
- Trial/signup entry points

This is a marketing/product-discovery experience and is not the CMS.

### 3.2 SitePro Dashboard

The authenticated account-management experience.

It provides access to areas such as:

- My Websites
- Account/Profile
- Subscription and Billing
- Domains where applicable
- AI Usage
- Notifications
- Enquiries
- Website History
- Security
- Help and Support

The dashboard is not the primary website content editor.

Website editing should direct the user into the actual website editing
experience.

### 3.3 Customer Website

The customer website is both:

1. the public website visitors see, and
2. the primary visual editing surface for an authorized owner.

A public visitor sees the published website without SitePro editing controls.

An authorized owner entering editing mode sees the same website enhanced with
SitePro editing capabilities.

---

## 4. Product Language

SitePro should prefer language understandable to non-technical website owners.

Avoid exposing implementation terminology when a simpler product term exists.

Examples:

- "Website Theme" — the initial whole-site starting design.
- "Design" — the presentation/layout of a section.
- "Pages & Menu" — page and navigation management.
- "Website Look" — website-wide appearance controls.
- "Business Details" — reusable business/contact information.
- "Search & Sharing" — customer-facing SEO/social sharing controls.

Internal technical terminology does not need to match customer-facing
terminology.

---

## 5. Website Themes

A Website Theme is a starting point for creating a website.

A theme may define an initial combination of:

- page structure
- sections
- section designs
- header design
- footer design
- typography
- visual styling

Selecting a theme must not permanently lock the website to that theme.

After creation, users can modify supported sections, designs, content, and
website appearance independently according to SitePro capabilities.

Theme selection is therefore an initial website setup decision, not a
permanent website restriction.

---

## 6. Website-First Editing

The primary website management experience must take place on the website.

The user should normally see their website rather than a traditional CMS
editing dashboard.

Persistent editor controls should remain minimal.

Expected high-level controls include:

- SitePro/editor bar
- website/global options
- Publish
- AI Website Assistant

Contextual controls should appear when the user interacts with relevant
website areas.

For example, selecting a section may expose actions such as:

- Edit
- Change Design
- AI
- More actions

SitePro should avoid presenting technical content schemas, component names, or
database-oriented concepts to normal users.

---

## 7. Direct Content Editing

Supported website content should be editable as directly as practical.

For example, selecting editable text should allow the user to change that
text in context rather than requiring navigation to a separate traditional
rich-text administration screen.

SitePro should use structured editing appropriate to the selected content.

V1 must not assume that every content field requires a traditional rich-text
editor.

Manual editing must remain available even when AI functionality is
unavailable or the customer's AI allowance has been exhausted.

---

## 8. Sections and Designs

Pages are composed from supported sections.

V1 should support operations appropriate to a section, including:

- add
- edit supported content
- remove
- reorder
- change design
- use relevant AI assistance

SitePro provides supported section types and designs so customers do not need
to design layouts from scratch.

Changing a section's Design should preserve compatible content wherever
possible.

Content and presentation should therefore remain conceptually separate.

SitePro should not expose internal schema or variant terminology to normal
customers.

---

## 9. Header and Menu

The website header should be editable directly from the website experience.

Selecting the header should provide customer-facing actions such as:

- Edit Menu
- Change Design
- AI
- More actions where needed

V1 may provide supported header/menu designs such as:

- Classic
- Centered
- Split
- CTA Header
- Simple Dropdown
- Mega Menu
- Mega Menu with Images
- Mega Menu with Featured Content

The exact available design catalogue is implementation/content configuration
and may evolve.

Changing header design should preserve compatible navigation content.

"Edit Menu" manages navigation content such as links, order, and supported
nesting.

"Change Design" changes presentation.

AI may assist with organizing or improving navigation.

---

## 10. Footer

The footer should be editable from the website experience.

Supported footer functionality may include:

- footer content
- navigation links
- business details
- social links
- supported footer designs
- AI assistance

Changing footer design should preserve compatible footer content where
possible.

---

## 11. Pages & Menu

V1 must provide a customer-friendly way to manage website pages and navigation.

Supported operations should include:

- view pages
- create a page
- rename/update supported page information
- manage navigation placement
- reorder supported navigation items
- manage supported nesting
- remove pages subject to product rules

Page creation may be assisted by AI.

The user should not need to understand routing or URL implementation details
to create normal website pages.

---

## 12. Media

V1 must provide a way to manage images and other supported website media.

Users should be able to select/change images from the website editing
experience where appropriate.

Media management should support the website editing workflow rather than
requiring users to understand filesystem concepts.

Exact storage limits, supported formats, transformations, and plan-specific
limits are defined separately from this specification unless explicitly
approved.

---

## 13. Business Details

SitePro should provide a central way to maintain reusable business
information where supported.

This may include:

- business/site name
- tagline
- contact email
- contact phone
- address
- social links

Website areas that use this information may reuse the centrally maintained
values where appropriate.

---

## 14. Forms and Enquiries

V1 supports website forms.

Authorized users should be able to configure supported form behavior from the
website editing experience.

Public visitors can submit published website forms.

Form submissions create enquiries according to the supported backend
behavior.

Customers can view their enquiries through SitePro.

The enquiry experience should include:

- enquiry list
- enquiry details
- relevant submission information

Private enquiry information must never be exposed through the public website.

---

## 15. Search & Sharing

SitePro should expose supported SEO and social-sharing configuration using
customer-friendly terminology.

The normal customer experience should avoid requiring detailed technical SEO
knowledge.

Supported controls may include appropriate page/site information used for:

- search result presentation
- page titles/descriptions
- social sharing presentation

Exact advanced SEO functionality is not implied by this V1 requirement.

---

## 16. AI Website Assistant

AI is a primary SitePro assistance mechanism.

AI can operate at different scopes.

### Element AI

Example:

"Rewrite this heading."

### Section AI

Example:

"Make this section more convincing."

### Website AI

Example:

"Make the whole website more professional."

Website-level AI may coordinate changes across multiple pages or sections
when supported.

AI should provide visual website actions and previews rather than behaving
only as a generic text chatbot.

AI does not replace the normal CMS.

Manual SitePro functionality must remain usable independently of AI
availability.

---

## 17. AI Change Behavior

SitePro should choose an interaction appropriate to the scope and confidence
of an AI change.

### Small, reversible, high-confidence change

The change may be applied directly with a clear Undo option.

### Meaningful change

Show an appropriate preview before application.

### Ambiguous request

Ask only the minimum clarification necessary.

### Destructive or broad change

Present the proposed action/plan and obtain appropriate confirmation before
application.

AI should avoid unnecessary conversational steps when the user's intent is
already clear.

---

## 18. AI Partial Success

A multi-step AI request may succeed only partially.

SitePro should preserve successful changes when appropriate rather than
discarding all work solely because another part failed.

The user should be informed about unresolved or failed portions and given an
appropriate way to continue.

---

## 19. Preview and Publishing

Editing and publishing are distinct concepts.

V1 must provide an understandable workflow for:

1. editing
2. previewing where appropriate
3. publishing
4. confirming successful publication

An edit must not be assumed to become public immediately unless the approved
workflow explicitly defines that behavior.

Public visitors see the appropriate published website state.

Draft/unpublished work must not accidentally become publicly visible.

---

## 20. Website History and Undo

V1 should provide appropriate change/history functionality.

Users should be able to understand meaningful website changes and recover
from supported mistakes.

SitePro may provide:

- immediate Undo for appropriate recent actions
- persistent website versions/history

Immediate editor Undo and persistent website version history are related but
not necessarily the same mechanism.

AI-originated changes should participate in the appropriate history/version
workflow rather than bypassing it.

---

## 21. Domains

A newly created website receives a SitePro-managed temporary domain.

Conceptually:

`site-name.cmsplatform.com`

The actual production platform domain is configuration and must not be
hardcoded from this example.

Supported customers may connect a custom domain such as:

`customer.com`

Domain ownership, verification, activation, SSL, and related technical
behavior belong to the system architecture/backend implementation.

The product experience should present domain management in understandable
customer language.

---

## 22. Mobile Editing

V1 should provide a deliberately simplified mobile website editing
experience.

Mobile editing does not need to expose every desktop control simultaneously.

The experience should prioritize common actions and maintain usability on a
smaller screen.

The public customer website itself must remain responsive independently of
editor functionality.

---

## 23. Account and Security

Customers must have an authenticated SitePro account.

The account experience should provide supported functionality for:

- profile/account information
- authentication/security
- website access
- subscription information
- relevant notifications/support

A SitePro user should not be required to create a second independent account
to edit their website.

Exact authentication/session mechanics are defined by the technical
architecture rather than this product specification.

---

## 24. Trial, Subscription and AI Allowance

SitePro is expected to support a free-trial experience.

The current product direction is approximately a one-month trial.

However, the following are NOT finalized by this specification:

- exact trial duration
- whether payment details are required before starting the trial
- plan names
- exact prices
- billing intervals
- number of websites per plan
- storage limits
- form/enquiry limits
- AI allowance amounts
- AI allowance reset behavior
- custom-domain eligibility by plan
- other plan-specific limits

These values must be configurable/defined by approved commercial requirements
and must not be inferred from design mockups.

Marketing CTAs may use approved trial wording once the commercial details are
finalized.

---

## 25. Authorization and Permissions

Only authorized users may manage a website.

Frontend visibility of editing controls is not sufficient authorization.

Protected operations must follow the authoritative backend permission model.

V1 must preserve isolation between different customer accounts and websites.

The exact V1 role/permission catalogue is governed by the approved backend
domain specification and should not be invented from UI assumptions.

---

## 26. V1 UX Principles

V1 should preserve these product principles:

- Website-first rather than dashboard-first editing.
- AI-first assistance.
- Direct manipulation where practical.
- Non-technical customer language.
- Minimal persistent editor chrome.
- Visual choices rather than technical configuration.
- Content should survive compatible design changes.
- Manual functionality must work without AI.
- Important AI changes should be previewable/reversible.
- Public website quality, accessibility, SEO, and performance must not be
  sacrificed for editor convenience.

---

## 27. Explicitly Undecided

The following decisions must not be invented during implementation unless
they have subsequently been approved elsewhere:

- final SitePro product/domain name
- exact commercial plan names
- pricing
- billing intervals
- exact trial terms
- payment-card requirement for trial
- exact AI allowance model and limits
- exact storage/media limits
- final section catalogue
- final theme catalogue
- final header/footer design catalogue
- exact advanced SEO feature set
- exact plan-specific feature restrictions
- any unsupported business rule not established by the product specification

When one of these decisions blocks implementation, it should be surfaced as a
product decision rather than silently assumed.

---

## 28. Out of Scope by Default

A feature is not part of V1 merely because another CMS or website builder
commonly provides it.

Unless separately approved, this specification does not automatically imply:

- plugin marketplace
- third-party extension ecosystem
- arbitrary custom code execution
- full developer page builder
- unrestricted HTML/CSS/JavaScript editing
- traditional WordPress-style administration
- traditional rich-text editing everywhere
- advanced marketing automation
- full CRM
- e-commerce platform
- multilingual website system
- complex workflow/approval engine
- arbitrary customer-defined database/content schemas

These may be considered later without changing the fundamental SitePro
product direction.

---

## 29. V1 Completion Principle

SitePro V1 is product-complete when a supported customer can reasonably move
through the core lifecycle:

Account
  ↓
Create Website
  ↓
Choose Starting Theme
  ↓
Temporary Website
  ↓
Edit Actual Website
  ↓
Manage Pages / Sections / Content / Media
  ↓
Use AI or Manual Editing
  ↓
Preview
  ↓
Publish
  ↓
Receive Enquiries
  ↓
Manage Website / Account
  ↓
Connect Custom Domain when supported

The implementation should prioritize completing this coherent lifecycle over
adding isolated advanced features.