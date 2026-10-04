<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

## SitePro Backend Context

This Laravel application is the authoritative backend and domain layer for
the SitePro CMS SaaS.

The frontend applications are:

- `apps/platform` — public SitePro marketing website
- `apps/dashboard` — authenticated customer account/dashboard
- `apps/website` — tenant website renderer and visual editor

Laravel owns authoritative persistent business rules and state.

These include, where applicable:

- authentication
- authorization
- accounts and memberships
- permissions
- websites
- website ownership/access
- domains
- website content
- draft and published state
- publishing
- website versions/history
- enquiries
- AI operations and usage
- subscription/billing state

Frontend applications may improve UX with client-side validation and
conditional UI, but they are not security or authorization boundaries.

## Tenant Isolation

SitePro is multi-tenant.

Every operation involving tenant-owned resources must preserve tenant
isolation.

Never authorize access merely because a resource ID exists or was supplied
by the client.

Authorization must consider the authenticated user, account/membership,
website, and relevant permission according to the established domain model.

Queries and mutations must not accidentally expose resources belonging to
another tenant.

Tenant-sensitive behavior requires tests.

## Website and Domain Resolution

A SitePro website may be served through:

- a SitePro-managed temporary subdomain
- a customer custom domain

Laravel is authoritative for website/domain ownership and relationships.

Do not trust arbitrary client-provided website or account identifiers as
proof of access.

Domain and website resolution must follow the approved SitePro architecture.

## Draft and Published State

Editing state and publicly published state are distinct concepts.

Public website requests must not accidentally expose draft/unpublished
content.

Do not invent draft, publishing, or version semantics when they are not yet
defined by the product specification or existing implementation.

Publishing-related changes should preserve the established version/history
contract and require appropriate authorization.

## API Design

Laravel APIs are consumed by independently deployed SitePro applications.

Keep API contracts explicit and predictable.

Use appropriate Form Requests, authorization mechanisms, API Resources, domain/application actions, or other established Laravel patterns when they provide clear responsibility.

Do not create architectural layers merely for symmetry.

Do not create a repository/service/action abstraction chain around simple
operations unless the complexity genuinely requires it.

Controllers should coordinate HTTP concerns rather than accumulate complex
business logic.

Validation and authorization must remain server-side even when equivalent
frontend validation exists.

## AI Operations

AI is an assistance layer over the normal SitePro CMS.

AI-generated changes must pass through the same applicable:

- authorization
- validation
- tenant isolation
- persistence
- publishing
- version/history

rules as manual changes.

Do not create privileged AI mutation paths that bypass normal SitePro
business rules.

Manual CMS functionality must not depend on AI availability or remaining AI
allowance unless explicitly required by the product specification.

## Security-Sensitive Changes

Treat changes involving the following as security-sensitive:

- authentication
- sessions
- authorization
- memberships
- permissions
- tenant resolution
- custom domains
- editor access/handoff
- draft content
- publishing
- billing
- media/file access
- form submissions
- AI mutations

Inspect the relevant architecture and existing implementation before changing
these areas.

Do not weaken authorization, validation, CSRF/session protection, or tenant
isolation for implementation convenience.

## Existing Database Architecture

Existing committed migrations are the current database architecture baseline.

Inspect the relevant migrations and database schema before implementing a
domain feature.

Do not modify existing migrations or redesign established tables merely to
make implementation easier unless the database architecture change is
explicitly approved.

When a schema change is genuinely required, treat it as an explicit database
architecture decision rather than an incidental implementation detail.

## Product Requirements

Project-level documentation is located under `/docs`.

Consult the documents relevant to the current feature when product or
architecture behavior is involved.

In particular, distinguish between:

- product principles — product philosophy
- product specification — authoritative V1 behavior
- architecture — system-level decisions
- development guide — implementation guidance
- roadmap — sequencing and future direction
- design references — visual guidance only

Do not infer business rules from design mockup placeholder content.

Do not invent requirements when the specification and existing implementation
do not establish them.

This application is a Laravel application targeting PHP **^8.3** per `composer.json`. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating models, create useful factories where they support testing. Create seeders only when the application actually needs seeded development, demo, or baseline data.

## APIs & Eloquent Resources

- - For APIs, prefer Eloquent API Resources where appropriate and follow the application's established routing/versioning convention. Do not introduce a new API versioning structure unless the project architecture requires it.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models, create useful factories where they support testing. Create seeders only when the application actually needs seeded development,
  demo, or baseline data.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
