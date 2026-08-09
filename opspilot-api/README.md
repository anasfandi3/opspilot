# OpsPilot API

OpsPilot is a multi-tenant internal operations and approval management API built with Laravel.

## Key capabilities

- Workspace multi-tenancy and workspace-scoped RBAC
- Configurable request types and dynamic form fields
- Immutable, versioned conditional approval workflows
- Draft, submission, approval, rejection, and cancellation lifecycle
- Comments, private attachments, and an activity timeline
- Queued database and email notifications
- Operational dashboard and read-only reporting

## Technology

- PHP 8.4+
- Laravel 13 and Laravel Sanctum
- MySQL 8
- Spatie Laravel Permission with teams enabled
- Database-backed queues
- PHPUnit 12

## Architecture overview

API requests generally pass through versioned routes, authentication and workspace-context middleware, a Form Request, and a policy before reaching a controller. Controllers delegate multi-record business operations to focused actions or query services. Eloquent persists the result and API Resources produce the response.

```text
Route -> middleware/context -> Form Request -> Policy
      -> Controller -> Action/query service -> Eloquent/DB -> Resource
```

A workflow definition is immutable after publication. Submitting a request snapshots the active definition into runtime approval records, so later workflow versions cannot rewrite history. Workspace membership establishes tenancy, while a separately assigned, workspace-scoped Spatie role grants capabilities. Membership by itself is not authorization.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure the `DB_*` values for a MySQL database, then run:

```bash
php artisan migrate
php artisan permissions:sync
php artisan serve
```

Workflow notifications use the configured queue. Run a worker alongside the API:

```bash
php artisan queue:work
```

Configure a real mail transport for email delivery. The database notification channel remains available through the notification API.

### First-party SPA authentication

The Vue admin uses Sanctum session authentication. Configure `SANCTUM_STATEFUL_DOMAINS` with frontend hosts (including development ports), `CORS_ALLOWED_ORIGINS` with full frontend origins, and the `SESSION_DOMAIN`/secure-cookie settings appropriate for the deployment. The SPA must send credentials and XSRF headers with requests.

The browser flow is `GET /sanctum/csrf-cookie`, `POST /api/v1/auth/session`, then authenticated `/api/v1/*` requests. Logout uses `POST /api/v1/auth/session/logout`. Existing `POST /api/v1/auth/login` bearer-token authentication remains available for non-browser API clients.

## Private attachments

The defaults are:

```dotenv
REQUEST_ATTACHMENT_DISK=local
REQUEST_ATTACHMENT_MAX_KB=10240
```

`REQUEST_ATTACHMENT_DISK` **must reference a private filesystem disk**. Attachment URLs are never exposed; authorized downloads stream through the API. Do not point this setting at a public disk.

## Demo data

On a development environment, seed the deterministic Acme Operations scenario explicitly:

```bash
php artisan db:seed --class=DemoSeeder
```

All demo accounts use the development-only password `password`:

| Account | Workspace role |
| --- | --- |
| `owner@opspilot.test` | owner |
| `admin@opspilot.test` | admin |
| `approver@opspilot.test` | approver |
| `requester@opspilot.test` | requester |
| `auditor@opspilot.test` | auditor |

The seed contains Purchase, Leave, and Equipment/Access request types; published workflows with conditional steps; draft, pending, approved, rejected, and cancelled requests; completed and pending approvals; comments; activity; and reporting data. Notifications are suppressed while seeding. The seeder refuses to run in production and safely skips an existing demo workspace.

## Tests and formatting

The automated suite uses an isolated SQLite in-memory database by default.

```bash
php artisan test --compact
vendor/bin/pint --test
```

## API overview

All routes are under `/api/v1`. Protected routes accept either a first-party Sanctum session or a Sanctum bearer token.

| Area | Important routes |
| --- | --- |
| Auth | `POST auth/register`, PAT `POST auth/login`/`POST auth/logout`, SPA `POST auth/session`/`POST auth/session/logout`, `GET/PATCH me`, `PUT me/password` |
| Workspaces | `GET/POST workspaces`, `GET/PATCH workspaces/{workspace}`, member and current-workspace routes |
| Invitations and roles | workspace invitations, invitation acceptance, member role update/removal |
| Request types | request-type CRUD, field CRUD/reorder, and request catalog |
| Workflow definitions | workflow draft/create/clone/publish/delete; step CRUD/reorder |
| Requests | request CRUD, submit/cancel, request detail and listing |
| Approvals | approval inbox/detail and approve/reject actions |
| Collaboration | nested comments, attachments/downloads, and activity timeline |
| Notifications | inbox, unread count, mark read, and mark all read |
| Operations | workspace dashboard, request report, and approval report |

Use `php artisan route:list --path=api/v1` for the authoritative route catalog and middleware assignments.

## Domain flow

```text
Workspace
  -> Request Type
  -> Workflow Version
  -> Request Draft
  -> Submit
  -> Runtime Approval Plan
  -> Sequential Decisions
  -> Approved / Rejected
```

Conditional steps that do not match the submitted field snapshot are recorded as skipped. Approval assignment is resolved at submission time; deciding an approval still requires current workspace membership and permission.

## Security and tenancy

- Nested scoped bindings and explicit workspace predicates isolate tenant data.
- Workspace resources expose `permissions` calculated from the backend `WorkspaceRoleMap`; clients should not infer capabilities from role names.
- Spatie's team ID is set per request and restored in a `finally` block for long-running-worker safety.
- Policies require workspace-scoped permissions; auditors remain read-only and requesters cannot access workspace-wide reports.
- Published workflow versions and runtime approval history are protected from mutation.
- Attachment disk/path values are server-controlled and omitted from API resources.
- Notification operations are registered after the business commit, reload models by stable IDs, and isolate queue failures from committed state.

## Intentional limitations

- Approval execution is sequential; delegation and reassignment are not supported.
- There is no workspace deletion workflow, frontend, realtime push, reminder/escalation system, report export, or custom workflow scripting.
- Direct workspace deletion is unsupported because historical records intentionally use restrictive foreign keys.
- Reports are read-only direct database aggregates and are not cached.
