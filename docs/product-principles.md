# Product Principles

## Product Vision

Build an AI-first CMS SaaS platform that allows non-technical users to manage websites without needing to understand traditional CMS concepts.

The primary product principle is:

> The website itself is the CMS.

Users should manage their website by looking at and interacting with the website itself.

---

## 1. Website-First Management

Website management should happen primarily on the website.

Owners should be able to:

- edit text where it appears
- replace images where they appear
- add, remove and reorder sections
- manage navigation visually
- edit forms directly
- manage dynamic content in context
- ask AI to make changes
- preview changes
- publish changes

Avoid forcing users into a traditional CMS administration interface.

---

## 2. Hide Technical Complexity

Normal users should not need to understand terms such as:

- HTML
- CSS
- breakpoints
- schemas
- content types
- database fields
- component trees
- API endpoints

Use language that describes what the user wants to accomplish.

Technical concepts may exist internally without being exposed unnecessarily.

---

## 3. Edit Things Where They Appear

Whenever practical, content should be editable directly from its visual location.

Example:

Click a heading → edit the heading.

Click an image → replace or edit the image.

Click a product → edit the actual product.

Click a section → manage that section.

---

## 4. AI Is a Universal Control Layer

AI is not merely a chatbot.

Users should be able to describe desired changes naturally.

Examples:

- Make this heading shorter.
- Add a testimonials section.
- Make the website feel more professional.
- Add a phone field to this form.
- Improve the SEO of this page.
- Change the primary brand color.
- Add our new service to the website.

AI converts user intent into structured application actions.

AI must not directly manipulate the database.

---

## 5. AI Changes Must Be Trustworthy

Significant AI operations should be understandable and reversible.

Preferred flow:

User request
→ AI prepares changes
→ User reviews
→ Apply to draft
→ Preview
→ Publish

Bulk and dangerous operations require explicit confirmation.

AI operations should be reversible through version/change history where appropriate.

---

## 6. Draft and Published Content Are Separate

Website owners work primarily against a draft state.

Visitors see the published state.

Flow:

Draft
→ Preview
→ Publish
→ Live Website

Unfinished owner changes should not automatically affect visitors.

---

## 7. Public and Owner Experiences Share the Website

Avoid building an unrelated visual page-builder that renders differently from the public website.

Conceptually:

Public Render
└── Website Components

Owner Render
├── Website Components
└── Owner Editing Layer

The owner experience adds editing capabilities to the website rather than replacing it.

---

## 8. Account Dashboard Is Not the CMS

The account dashboard exists for account-level management.

Examples:

- My Websites
- Profile
- Subscription
- Billing
- Usage
- Account settings

Website content management should primarily happen on the website itself.

---

## 9. Content and Presentation Are Separate

Reusable content should not be duplicated merely because it appears in multiple places.

For example, a Product is content.

A Product Card is presentation.

Updating a product should update places that reference that product.

---

## 10. Design Should Be Semantic

Components should use design-system concepts rather than arbitrary styling wherever possible.

Examples:

- primary color
- heading typography
- standard spacing
- button style
- card radius

This allows users and AI to make consistent global design changes.

---

## 11. Responsive Design Should Be Simple

Users should not manage CSS breakpoints.

The product should expose understandable controls for desktop, tablet and mobile behavior only when necessary.

---

## 12. Safety Before Automation

Convenience must not bypass authorization or publishing safety.

Laravel determines whether a user can perform an operation.

AI and frontend applications must operate through the same authorized domain layer.

---

## 13. Build for Non-Technical Users

When choosing between technical flexibility and understandable interaction, prefer an experience that normal website owners can understand without training.

The ideal experience should feel like:

> I clicked it and changed it.

Not:

> I learned how to operate a CMS.