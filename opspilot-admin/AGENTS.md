# OpsPilot Admin — AGENTS.md

## Purpose

This file defines the frontend architecture, engineering conventions, and implementation boundaries for `opspilot-admin/`.

Codex and other coding agents must follow these rules when implementing or modifying the OpsPilot frontend.

OpsPilot is a multi-tenant internal operations/request/approval SaaS built with a Laravel API and a Vue admin SPA. The frontend must remain aligned with the backend domain model and authorization rules. The Laravel API is authoritative for business rules, permissions, validation, workflow state, and tenant isolation.

Do not redesign the frontend architecture unless explicitly instructed.

---

# 1. Frontend Stack

Use the existing project stack and conventions:

- Vue 3
- TypeScript
- Composition API
- `<script setup lang="ts">`
- Vite
- Vue Router
- Pinia
- TanStack Vue Query for server state
- Tailwind CSS v4
- shadcn-vue
- Reka UI through shadcn-vue primitives
- Lucide icons
- VueUse where appropriate
- Vitest
- Playwright
- ESLint / Oxlint
- Prettier

Do not introduce a second UI framework such as PrimeVue, Vuetify, Element Plus, or Quasar.

Do not replace Prettier or the existing linting/tooling stack unless explicitly requested.

Use Vue Docs MCP for current Vue/Vue Router/Vite/Vitest ecosystem guidance when APIs or conventions are uncertain.

Use Playwright MCP/browser verification when visual or browser behavior matters.

---

# 2. UI Foundation

Patch 0 established the reusable frontend foundation. Preserve it.

## Component layers

```text
src/components/
├── ui/          # low-level shadcn-vue primitives
└── app/         # reusable application-level components
```

Rules:

- `components/ui/` contains low-level shadcn-vue primitives.
- Avoid wrapping shadcn components without a real application-level reason.
- `components/app/` contains reusable OpsPilot-level UI patterns that are not tied to one business feature.
- Feature-specific UI belongs inside that feature.

Current reusable patterns include or may include:

- AppShell
- AppSidebar
- AppHeader
- PageHeader
- FormField
- FormGrid
- DataTable
- ServerPagination
- DataTableToolbar
- EmptyState
- LoadingState
- ConfirmDialog
- ThemeToggle
- FileInput

## Visual rules

The application uses:

- clean modern SaaS styling
- neutral/default colors
- medium border radius
- Inter typography
- Lucide icons
- light / dark / system themes
- sticky header
- collapsible desktop sidebar
- mobile sidebar drawer
- full-width content areas

Page structure should normally follow:

```text
Page title + actions + filters/tools + content
```

Forms:

- labels above inputs
- field errors directly below the field
- optional form-level alert for general/server errors
- adaptive one/two-column layout
- fields may span columns
- use the shared date picker rather than native date input as the primary date UI
- simple file upload UI unless a feature explicitly requires more

Loading:

- skeletons for content/page/table reads
- spinners/loading states for actions/buttons

Success:

- use toast notifications for successful mutations where appropriate
- inline success states may also be used when the context benefits from persistence

Destructive actions:

- always use the reusable confirmation dialog
- never use browser `confirm()`

Empty states:

- icon
- short explanation
- primary action where relevant

Tables:

- server-driven
- optional search
- optional filters
- sorting
- numbered pagination
- page-size selector
- row selection where appropriate
- optional bulk actions
- explicit row actions
- loading state
- empty state
- horizontal scrolling on mobile
- no column visibility feature
- rows are not globally clickable
- primary row action may be visible
- secondary actions belong in a three-dot menu
- common filters inline
- advanced filters in a drawer/panel
- table state belongs in the URL

Do not add bulk actions simply because the DataTable supports them. Use them only when they make sense for the domain.

The hidden `/ui` route is the component playground. Keep it development-oriented. By final hardening it should not be part of production navigation and may be registered only in development.

---

# 3. Feature-Based Architecture

Business code is organized by feature/domain.

```text
src/
├── components/
│   ├── ui/
│   └── app/
├── features/
│   ├── auth/
│   ├── workspaces/
│   ├── members/
│   ├── request-types/
│   ├── workflows/
│   ├── requests/
│   ├── approvals/
│   ├── notifications/
│   └── reports/
├── composables/
├── lib/
├── router/
├── stores/
├── types/
└── views/
```

A feature may contain:

```text
features/requests/
├── api/
├── components/
├── composables/
├── queries/
├── types/
├── utils/
├── routes.ts
└── views/
```

Example views:

```text
RequestListView.vue
RequestCreateView.vue
RequestDetailView.vue
RequestEditView.vue
```

## Feature boundary rule

If code belongs to one business domain, keep it inside that feature.

Do not create giant global folders full of unrelated business logic such as:

```text
services/
types/
utils/
```

Only genuinely cross-feature infrastructure should live outside `features/`.

A feature may import:

- `components/ui`
- `components/app`
- `lib`
- global stores
- truly shared composables/types
- explicitly reusable code from another feature when a genuine domain relationship exists

If unrelated features both need the same generic behavior, extract the generic part upward rather than creating random cross-feature dependencies.

---

# 4. API Architecture

Business components must not call `fetch()` directly.

Use:

```text
View / Component
↓
Feature composable/query
↓
Feature API module
↓
Shared API client
↓
Laravel API
```

Shared transport belongs in:

```text
src/lib/api/
├── client.ts
├── errors.ts
└── types.ts
```

Feature endpoints belong inside each feature:

```text
features/requests/api/requests.ts
features/workflows/api/workflows.ts
features/members/api/members.ts
```

The shared API client handles only cross-cutting transport concerns such as:

- API base URL
- credentials/session handling
- CSRF/Sanctum behavior
- standard JSON headers
- response parsing
- normalized API errors
- authenticated blob/download responses
- request cancellation support when useful

Feature API modules own business endpoint details.

Do not introduce generic CRUD/repository abstractions such as:

```text
BaseRepository<T>
CrudService<T>
RepositoryImpl
```

unless there is a demonstrated architecture need.

The rule is:

> Generic transport globally, business endpoints locally.

---

# 5. State Ownership

Use each state tool for one clear purpose.

```text
Pinia
→ durable application-wide client context

TanStack Vue Query
→ server state

Vue Router query
→ shareable filters/search/sorting/pagination

Local Vue reactive state
→ forms/builders/temporary UI
```

## Pinia

Keep Pinia intentionally small.

Expected global stores:

```text
stores/
├── auth.ts
├── workspace.ts
└── notifications.ts   # summary only, when Patch 8 is implemented
```

Do not create feature/server-data stores such as:

```text
requestStore
workflowStore
memberStore
approvalStore
reportStore
```

unless a specific client-state need is proven and explicitly justified.

### Auth store

May own:

- current user
- initialized/bootstrap state
- authenticated state
- login orchestration
- logout orchestration

### Workspace store

May own:

- available workspaces
- current workspace
- current workspace ID
- persisted workspace selection
- workspace switching

Do not put workspace-scoped business lists/data inside the workspace store.

### Notification store

When implemented, it may own only globally needed summary data such as:

- unread count
- recent notification summary
- summary refresh action

The full notification inbox remains TanStack Query server state.

---

# 6. TanStack Vue Query

Use TanStack Vue Query for server state.

Examples of server state:

- members
- invitations
- request types
- workflows
- requests
- approvals
- notification inbox
- dashboard data
- reports

## Query key rules

Every feature owns deterministic query-key factories.

Example:

```ts
requestKeys.all(workspaceId)
requestKeys.list(workspaceId, filters)
requestKeys.detail(workspaceId, requestId)
```

Every workspace-scoped query key MUST contain the current workspace ID.

This is required to prevent stale visual cross-tenant data.

## Mutation rules

After mutations:

- invalidate/refetch the smallest sensible authoritative query scope
- prefer authoritative backend refetching for important business transitions

Do not aggressively optimistic-update:

- approval decisions
- workflow publishing
- request submission
- role changes
- state-machine transitions
- other high-consequence business mutations

Optimistic updates are allowed only for low-risk interactions where rollback is trivial and correctness is not compromised.

Do not blindly retry mutations.

Configure sensible query defaults globally, then override only where a feature needs different behavior.

URL query state owns list/table controls. TanStack Query consumes that state; it does not replace it.

---

# 7. Routing

Feature routes are owned by the feature and composed centrally.

Example:

```text
features/requests/routes.ts
features/workflows/routes.ts
features/members/routes.ts
```

Core router files:

```text
router/
├── index.ts
├── guards.ts
└── meta.d.ts
```

Business views should be lazy-loaded.

Use typed Vue Router metadata.

Typical metadata:

```ts
meta: {
  requiresAuth: true,
  permission: 'reports.view',
}
```

or:

```ts
meta: {
  requiresAuth: true,
  anyPermissions: ['members.view', 'members.manage'],
}
```

Do not scatter authorization guards through individual components when route metadata/global guards can handle page access.

## Global guard flow

Conceptually:

```text
navigation starts
↓
ensure auth bootstrap is complete
↓
handle guest/authenticated route rules
↓
ensure valid workspace context
↓
check route permission metadata
↓
allow or redirect
```

Rules:

- guest visiting a protected route → `/login?redirect=<original-full-path>`
- authenticated user visiting `/login` → capability-aware home
- insufficient frontend permission → `/403`
- unknown route → `/404`
- backend `403` remains authoritative even if frontend access checks allowed the route

After login:

1. honor a safe preserved redirect when present
2. otherwise choose the first meaningful route the user can access

Do not hardcode `/dashboard` as the universal landing route.

Possible capability-aware order may begin with:

```text
dashboard
requests
approvals
another authorized feature
```

Use TanStack Query for business data, not Vue Router data-loader architecture.

---

# 8. Global Workspace Model

OpsPilot uses one globally selected workspace context.

URLs remain clean:

```text
/requests
/workflows
/members
/reports/requests
```

Do NOT put the workspace ID in every route.

The header contains the workspace switcher.

## Workspace selection

Rules:

- one workspace → select automatically
- multiple workspaces → restore the previously selected workspace if still valid
- invalid persisted workspace → choose a valid fallback
- persist current workspace locally
- backend membership remains authoritative

## Workspace switching lifecycle

Workspace switching is a first-class operation:

```text
select workspace
↓
validate membership/context
↓
set current workspace
↓
persist selection
↓
cancel/remove irrelevant workspace-scoped queries
↓
navigate to a safe route when needed
↓
load new workspace data
```

Old-workspace business data must disappear immediately rather than remain visible while the new workspace loads.

### List routes

Stay on the route and reload:

```text
/requests
/members
/reports/requests
```

### Resource/detail/edit routes

On workspace switch, navigate to the feature's safe root/list.

Example:

```text
/requests/123
→ switch workspace
→ /requests
```

Do not try to interpret the old tenant's resource ID inside the new tenant.

Cross-workspace notifications may explicitly switch workspace and then deep-link to the relevant resource.

---

# 9. Authorization

Frontend authorization improves UX but never replaces backend authorization.

Use a reusable authorization interface:

```ts
can('reports.view')
canAny(['members.manage', 'members.view'])
canAll([...])
```

A `<Can>` component may exist for rendering convenience, but business rules must not depend on hidden UI alone.

Permissions are tied to the current user/current workspace context.

Do not create a separate permission store unless the architecture later proves it necessary.

Use backend permission names exactly. Do not invent frontend-only permission names.

Authorization is enforced at:

- route access
- navigation visibility
- action/button visibility
- backend policy/API level

The backend always wins.

---

# 10. Forms and Validation

Use typed local Vue state for forms and builders.

Do not put forms in Pinia.

Flow:

```text
Page / Builder
↓
local typed form state
↓
feature mutation
↓
API
```

Client-side validation should catch obvious immediate errors.

Laravel validation remains authoritative.

## Laravel 422 handling

Use a standardized mapper for Laravel validation errors.

Support nested paths such as:

```text
fields.2.options.1.label
```

Field-specific errors belong below the affected field.

General/non-field failures may use a form-level alert.

Do not make every form understand raw Laravel validation response structures independently.

## Dirty state

Use a reusable dirty-state/navigation protection pattern for:

- request type builders
- workflow builders
- editable request drafts
- other significant full-page forms

Rules:

- warn only when data is actually dirty
- successful save resets the dirty baseline
- do not block navigation when clean

## Dynamic forms

There must be one shared typed renderer for request field definitions.

Patch 3 defines field schemas.

Patch 5 consumes those schemas to render request forms.

Approval/request detail pages should reuse the same field-definition/display architecture rather than creating a second rendering system.

Unknown field types must fail safely rather than crash or silently render incorrect controls.

Do not create a second frontend schema DSL that competes with the Laravel backend model.

---

# 11. Errors and Mutation UX

Normalize API errors centrally.

Expected error categories:

```text
Unauthenticated
Forbidden
Not Found
Validation
Conflict
Server
Network
Unknown
```

Feature pages should not inspect raw Laravel error JSON directly.

## UX rules

### 401
Handle authentication/session expiry centrally.

Avoid redirect loops.

### 403
Use route/page forbidden behavior where appropriate.

### 404
Use resource-not-found behavior.

### 422
Map to fields/form errors.

### 409 or stale-state conflict
Explain that the state changed, refetch authoritative data, and prevent misleading stale actions.

This is especially important for approvals and other concurrent business transitions.

### 500/network
Reads should offer an appropriate retry state.

Writes should show a clear mutation error without pretending the mutation succeeded.

## Toasts

Use toasts for:

- mutation success
- useful non-blocking feedback

Do not use a toast as the only error representation when the user must fix something.

## Loading

- content reads → skeletons
- action mutations → button loading/spinner
- background refetch → keep usable existing screen where appropriate
- important actions must prevent duplicate submissions

---

# 12. Attachments and Downloads

Attachments are private.

Never construct public attachment URLs.

Downloads must go through the authenticated Laravel/API flow.

The shared API layer should support authenticated blob/download responses.

Use the existing simple file-input experience unless the feature explicitly requires something more.

Do not add drag-and-drop or upload libraries without a demonstrated need.

---

# 13. Tables and URL Query State

List/search/filter/sort/page controls belong in the router query.

Example:

```text
?search=purchase&status=pending&sort=created_at&direction=desc&page=2&per_page=20
```

Benefits:

- bookmarkable
- shareable
- browser back/forward friendly
- server-driven

Invalid query values must fall back safely.

Do not store table query state in Pinia.

Changing filters/search/page-size should reset page when appropriate.

Server pagination metadata is authoritative. Do not infer totals from the current result page.

---

# 14. Testing Strategy

Use the correct testing layer for the behavior.

## Vitest

Test logic that can fail independently of the browser, such as:

- API error normalization
- query-key factories
- permission helpers
- stores
- important composables
- form error mapping
- dynamic field mapping
- pagination/query parsing
- workflow condition helpers
- activity/notification presentation mapping
- non-trivial reusable application components

Do not test shadcn-vue internals.

## Playwright

Test real user/business workflows.

Important flows include:

- authentication
- route guards
- workspace switching
- permission boundaries
- member/invitation management
- request type builder
- workflow publishing
- request draft/submission
- approve/reject
- comments
- private attachments
- notification deep links
- reports
- mobile shell
- dark-mode regression where useful
- final cross-role showcase flow

For business E2E tests, favor the real OpsPilot Laravel API and demo accounts/data rather than mocking the entire application.

OpsPilot is a full-stack showcase; genuine frontend-to-backend E2E coverage is intentional.

Use reusable Playwright auth/workspace helpers/fixtures rather than repeating login boilerplate everywhere.

Avoid brittle E2E selectors based on implementation CSS classes.

Prefer:

- roles
- labels
- accessible names
- stable test IDs only where accessibility selectors are insufficient

The browser console should remain clean during E2E flows:

- no Vue warnings
- no unhandled promise rejections
- no unexpected console errors

---

# 15. Naming Conventions

Prefer clear domain names.

Examples:

```text
RequestListView.vue
RequestDetailView.vue
RequestStatusBadge.vue
DynamicFieldRenderer.vue

useRequestForm.ts
useAuthorization.ts

requests.ts
requestQueries.ts
requestMutations.ts
requestKeys.ts
```

Avoid abstraction-heavy naming such as:

```text
RequestManager
RequestHandlerFactory
RequestRepositoryImpl
GenericEntityService
```

unless such an abstraction is truly required.

Use typed props, emits, API types, and composables.

Avoid `any`.

Do not add TypeScript suppression comments to bypass fixable problems.

---

# 16. Backend Authority and Business Logic

The Laravel API is authoritative for:

- tenant isolation
- permissions
- request lifecycle
- workflow immutability
- approval eligibility
- conditional workflow evaluation
- concurrency/locking
- validation
- attachment access
- reports and aggregate metrics

The frontend should present and orchestrate these rules, not duplicate them unnecessarily.

Examples:

- do not recreate the full request state machine client-side
- do not recreate approval locking logic
- do not compute authoritative report totals from paginated rows
- do not reconstruct audit history from local events
- do not infer workflow execution locally

Where possible, display backend-provided state/capabilities and refresh after important mutations.

---

# 17. Frontend Business Patch Roadmap

The business frontend is implemented in this order.

## Patch 0 — UI Foundation ✅

Already established:

- Tailwind/shadcn-vue
- light/dark/system theme
- shell/sidebar/header
- forms
- date picker
- file input
- alerts/toasts
- skeletons
- dialogs/drawers
- DataTable
- numbered pagination
- URL table state
- `/ui` playground
- Vitest/Playwright baseline

Do not redesign Patch 0 architecture during business patches unless a real product need is discovered.

## Patch 1 — Auth / API / Workspace Context

Implement:

- shared API client
- Laravel session/Sanctum-compatible authentication
- login/logout
- current-user bootstrap
- protected routes
- auth guards
- current workspace state
- workspace switcher
- persisted workspace selection
- permission helpers
- permission-aware navigation foundation
- `403`
- `404`

Do not implement business modules in this patch.

Global workspace routes remain clean; no workspace ID in route paths.

## Patch 2 — Workspace / Members / Invitations

Routes:

```text
/settings/workspace
/settings/members
/settings/invitations
```

Implement:

- workspace settings supported by backend
- members table
- invitations table
- invite-member drawer
- role changes
- member removal
- permission-aware management

Do not invent a custom role/permission builder unless the backend explicitly supports it.

## Patch 3 — Request Types / Dynamic Fields

Routes:

```text
/request-types
/request-types/create
/request-types/:id
/request-types/:id/edit
```

Implement:

- request type list
- create/edit/view
- dynamic field builder
- field ordering
- field-type configuration
- select-option editing
- validation
- dirty-state protection

Patch 3 defines dynamic schemas. It does not submit real requests.

## Patch 4 — Workflow Builder

Routes:

```text
/workflows
/workflows/create
/workflows/:id
/workflows/:id/edit
```

Implement:

- workflow list
- draft workflow builder
- sequential approval steps
- approver/role assignment
- conditions based on request-type fields
- step ordering
- validation
- publishing
- immutable published versions
- version history
- dirty-state protection

Do not implement runtime approval decisions here.

## Patch 5 — Requests

Routes:

```text
/requests
/requests/create
/requests/:id
/requests/:id/edit
```

Implement:

- requests table
- request-type selection
- dynamic request form rendering
- draft save/edit
- submit
- lifecycle actions supported by backend
- request detail
- submitted field display
- approval-chain summary

Do not implement approve/reject controls here.

## Patch 6 — Approval Inbox / Decisions

Routes:

```text
/approvals
/approvals/:id
```

Implement:

- pending/completed approval inbox
- approval detail
- shared submitted-request field display
- approve
- reject
- rejection reason where required
- approval-chain state
- stale/concurrent decision handling

Do not implement bulk approval actions.

## Patch 7 — Comments / Private Attachments / Activity

Extend request detail.

Implement:

- comments
- private attachment upload/download/removal
- activity timeline
- centralized activity presentation mapping
- unknown event fallback
- shared collaboration components where approval detail benefits

No WebSockets are required.

## Patch 8 — Notifications

Route:

```text
/notifications
```

Implement:

- header notification bell
- unread summary/count when supported
- recent notification preview
- full notification inbox
- deep links
- read/unread behavior when supported
- unknown notification fallback
- cross-workspace notification navigation where backend payload supports it

No WebSockets/Pusher/browser push system.

## Patch 9 — Dashboard / Reports

Routes:

```text
/dashboard
/reports/requests
/reports/approvals
```

Implement:

- dashboard backend aggregates
- KPI cards
- operational summaries
- request report
- approval report
- URL-synced filters
- permission-aware dashboard/report access
- capability-aware post-login home behavior

Do not compute authoritative aggregates from paginated frontend data.

Do not add export unless backend support exists.

## Patch 10 — Hardening / E2E / Showcase Polish

No new business features.

Perform:

- full permission audit
- tenant/workspace isolation regression
- auth/session edge cases
- error consistency
- form consistency
- loading/empty/error consistency
- responsive pass
- dark-mode pass
- accessibility pass
- status formatting consistency
- route/document-title polish
- remove dead placeholders
- development-only `/ui`
- Playwright architecture cleanup
- final full-stack cross-role E2E showcase
- README/frontend documentation updates
- performance sanity check
- console-cleanliness verification

Final showcase journey should prove the system end-to-end across roles.

---

# 18. Scope Discipline

Each patch must stay within its defined scope.

Do not sneak future feature work into earlier patches.

Do not modify `opspilot-api/` during frontend patches unless explicitly instructed.

If frontend implementation reveals a missing backend capability:

1. document the gap clearly
2. do not invent frontend-only behavior to fake it
3. do not silently change backend scope
4. wait for explicit instruction before modifying the API

Do not create or modify unrelated repository files.

---

# 19. Required Verification

For each frontend patch, run the relevant project checks.

At minimum where applicable:

```bash
npm run type-check
npm run lint
npm run test:unit
npm run build
npm run test:e2e
```

Also run formatting checks using the scripts/configuration present in the repository.

Use Playwright/browser verification for interaction-heavy patches.

Do not claim tests passed unless they were actually run.

When a local environment issue prevents a check, report it explicitly rather than changing committed project configuration to fit one machine.

Do not hardcode local/system browser channels or other machine-specific workarounds in committed configuration.

---

# 20. Patch Workflow

Frontend development follows the established review workflow:

1. Inspect the existing committed frontend before coding.
2. Follow this `AGENTS.md`.
3. Use current Vue documentation/MCP guidance where relevant.
4. Implement only the requested patch.
5. Run verification.
6. Produce the requested patch file against the specified baseline.
7. Do not commit or push unless explicitly asked.
8. Return a concise completion report including:
   - implemented scope
   - architecture decisions actually used
   - dependencies added/changed and why
   - tests/checks run
   - any warnings or unresolved constraints
   - patch path

The actual patch will be reviewed independently. Do not rely on the completion report as a substitute for correctness.

---

# 21. Core Principle

Prefer simple, typed, explicit architecture.

OpsPilot should demonstrate:

- strong Laravel/Vue integration
- multi-tenant correctness
- clean authorization
- dynamic forms
- workflow configuration
- approval state handling
- reusable UI
- server-state discipline
- secure private files
- auditability
- reporting
- meaningful automated testing

Avoid unnecessary framework-building, premature abstraction, and feature creep.

When uncertain, choose the implementation that is easiest to understand, safest across workspaces, and closest to the backend's authoritative model.
