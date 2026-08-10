# OpsPilot Admin

The OpsPilot admin SPA is the Vue 3 frontend for the multi-tenant Laravel workflow and approval application.

## Frontend architecture

- Vue 3, TypeScript, Composition API, Vite, and Vue Router
- Tailwind CSS and shadcn-vue/reka-ui application components
- Pinia for authenticated user and selected-workspace client context
- TanStack Vue Query for workspace-scoped server state
- Cookie-based Laravel Sanctum session authentication
- URL-owned server table and report filters

Implemented areas include workspace administration, members and invitations, dynamic request types, versioned workflows, requests and approvals, comments, private authenticated attachments, activity, notifications, dashboard metrics, and reports.

The `/ui` design-system playground is registered only by the development build and is not part of production navigation.

## Project setup

```sh
npm install
```

```sh
npm run dev
```

## Verification

Vitest covers stores, authorization, query keys, workspace safety, forms, and domain presentation helpers. Playwright exercises the real Laravel API across authentication, permission boundaries, administration, request and workflow lifecycles, collaboration, notifications, reports, and responsive navigation.

```sh
npm run type-check
npm run lint
npm run test:unit
npm run build
npm run test:e2e
npx prettier --check src e2e
```

Install Playwright's portable browser builds before the first E2E run:

```sh
npx playwright install
```
