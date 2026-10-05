---
name: new-app
description: Start building a new HTMX + PHP + Bootstrap 5.3 application. Use when the user says "new app", "start a new application", "build an app", "begin the project", or invokes /new-app. Enforces the fixed build order — full database + memory design first, then authentication + app shell, then vertical feature slices — on the standard stack (Ubuntu 24.04, PostgreSQL 17 + MaluDB, Apache, PHP, HTMX).
---

# New Application Workflow

Build every application in the same fixed order. Do not skip phases, reorder them, or start writing feature screens before Phase 2 is approved by the user. Each phase ends with a user checkpoint.

Before starting, load context from the sibling skills in this plugin:

- **memory-first-planning** — memory-first design, ask-me-anything, SaaS Plus+, the two-memory architecture served through MCP. Consult during Phase 0 and 1.
- **mcp-servers** — the two MCP servers every app ships (record + activity memory). Consult during Phase 1 (tool-surface design) and Phase 4.
- **design-system** — the mandatory UI template and rules. Consult during Phase 2 and every screen thereafter.
- **php-patterns** — the mandatory PHP code structure. Consult before writing any PHP.
- **php-session-auth** — security rules for the auth build in Phase 2.
- **malumail-send** — the default email channel (MaluMail API) for reset/verification emails and notifications. Consult in Phase 2 and wherever a feature sends mail.
- **chat-actions** — the voice-first command bar on every screen: LLM router → actions MCP server → the app's own endpoints. Consult in Phase 1 (action manifest), Phase 2 (command bar in the shell), and Phase 4 (assistant service).
- **os-application** — how the application fits the MaluDB Business OS kernel from day one (the default for every application from us): `config/.env`, `/srv/apps/<key>`, the kernel's sign-on instead of a login form, roles as a set, the kernel's action-manifest shape and JSON-mode handlers, the MCP servers' kernel contract, the command bar through the kernel's chat endpoint, `maludb-os.json`. Consult in Phase 0 (decide OS application or standalone), and it changes Phases 1, 2 and 4 as its table says. The contract itself is the `maludb-os-integration` plugin.

## The stack (fixed — do not reopen unless the user does)

| Layer | Choice |
|---|---|
| OS | Ubuntu 24.04 |
| System of record | PostgreSQL 17 |
| Activity memory | MaluDB (PostgreSQL extensions), fed by application logs |
| Memory interface | Two client-facing read MCP servers per app (Python + FastMCP), per the mcp-servers skill |
| Action interface | One localhost-only actions MCP server per app (navigation + app actions), per the chat-actions skill |
| Assistant | **OS application:** the kernel runs the application's expert (the command bar posts to the kernel's chat endpoint; no model key in the app). **Standalone:** one unified Claude Agent SDK service (Python) — AMA answers, chat actions, navigation |
| Web server | Apache (also reverse-proxies the MCP servers and, standalone, the AMA service); an OS application lives at `/srv/apps/<key>` behind `deploy/` templates the kernel's installer renders |
| Application | PHP (vanilla, per php-patterns skill) |
| UI | Bootstrap 5.3 per the design-system skill |
| Interactivity | HTMX partial updates |

Node.js is the documented alternative for the MCP/agent layers; default to Python, one runtime per project.

## Phase 0 — Planning (memory-first)

Plan the application with the user using the memory-first-planning skill: decide what the application *remembers* before what it *does*. Deliverables:

1. The memory model — entities, facts, relationships.
2. The question list — the record questions (answered from PostgreSQL) and activity questions (answered from MaluDB logs) the application exists to answer. Features are pre-packaged answers to these questions.
3. The feature list, ordered for Phase 3 vertical slices.
4. **OS application or standalone product** (the os-application skill): the catalog key, the scope kind, the roles and the rights each gives. The default is an OS application.

**Checkpoint:** user approves the memory model and feature order.

## Phase 1 — Database and memory design (the ENTIRE application)

Design the complete database up front — every table for every planned feature, not just the first slice.

**Survey the estate before designing a table** (OS application; `maludb-os-integration`'s `shared-schema.md`). The
applications of the Business OS share one data model across their separate databases. List every table the sibling
applications already have (`/srv/apps/*/db/*.sql` on the build server, or the `maludb-os-<key>` repositories) and
decide each planned table against them: **read** — another application owns the data (HR's employment, the
ledger's parties, Help Desk's tickets …), so hold at most its id and reach it through the kernel's K7 read, never a
copy; **reuse** — the same concept and your own rows (attachments, notes, notifications, sequences, billing
documents …), so copy the canonical definition verbatim into your own `db/` file and extend only by appending;
**new** — nothing is close, and yours becomes the canonical table. The definition always lives in this application's
own migrations: installations differ in which applications they hold, and the installer creates every table from
this repository alone.

1. Write one SQL file per concern under `db/` (e.g. `db/001_auth.sql`, `db/002_core.sql`, …) targeting PostgreSQL 17. The auth schema always includes the Google-identity and TOTP-2FA structures from the php-session-auth skill's references (`auth_identities`, nullable `password_hash`, `totp_*` columns, recovery codes) — they ship in every app even if a project enables the features later.
2. Design the **activity log** from day one: a log table (and/or file stream) capturing every screen entered, record created, and action taken by every actor, in the shape MaluDB ingests. Activity memory cannot be backfilled — logging exists before the first feature ships.
3. Include the MaluDB extension setup and ingestion wiring.
4. Every table gets created_at/updated_at and, where rows are user-owned, the owning user id. Use `bigint generated always as identity` primary keys unless the user specifies otherwise. Every table in the design document carries its provenance — `read: <app>.<tool>`, `reuse: <app>/db/NNN_x.sql` (+ appended columns) or `new` (why nothing fit) — and a reused table keeps the canonical name, columns and types exactly.
5. **Design the MCP tool surface** (per the mcp-servers skill): map every question from the Phase 0 question list to a named tool on the record or activity server, plus one guarded read-only search tool per server. Define the read-only database roles the servers will use. The question list is the contract — a question with no tool is unfinished design.
6. **Design the action manifest** (per the chat-actions skill): the screen registry (every screen's id, canonical URL, "when the user wants…" description) and the action registry (every performable action with its endpoint, parameters, undo definition, and confirm flag). A screen or action missing from the manifest is unreachable by voice — unfinished design, same as an unanswered question. For an OS application the action table has the kernel's eight columns (`Action · File · Params · Undo · Confirm · Agent approval · Log · Who`), a path parameter is written by its entity's name (`/vessels/{vessel}/status`), and `bin/build_action_registry.php` produces `mcp/action_registry.json`.
7. **Draft `maludb-os.json`** (OS application): catalog key, vhost label, database roles, env keys, services, endpoints, the roles catalogue in the schema (`app_rights`, `app_roles`, `app_role_rights`), the expert's job description and tool grants. The kernel's installer will install from it; writing it now keeps the design honest.

**Checkpoint:** user approves the full schema (every table's provenance stated: read, reuse or new), the MCP tool surface, *and* the action manifest before any PHP is written.

## Phase 2 — Authentication and application shell

Goal: at the end of this phase the application *looks and feels exactly like the finished product*, with working login, even though it has no features yet.

1. **OS application:** build the kernel's sign-on instead of a login — `/sso` and `/sso/logout` from `php-sign-on-kit.md`, the directory mirror and its one-minute sync, the per-request guard, `/api/v1/health`, and `deploy/` templates; prove it with `bin/dev_handoff.php` (os-application). **Standalone product only:** build session auth (register, login, logout, reset) in vanilla PHP following the **php-session-auth** skill — all of its non-negotiables apply. The login page offers email/password **and** "Sign in with Google" (server-side OIDC per `references/google-signin.md`); authenticator-app 2FA (per `references/totp-2fa.md`) ships with its enrollment settings page and login challenge page. CSRF is wired for HTMX from the first shell render (meta tag + `htmx:configRequest` listener). Reset and verification emails go through the **malumail-send** skill's `malumail_send()` helper. Auth pages use the design-system minimal auth layout; login/logout/2FA are full page navigations, never swaps.
2. Build the application shell from the **design-system** skill: the sidebar/header/footer layout, the HTMX content-swap target, an empty dashboard, the navigation stubs for every planned feature, and the **assistant command bar** (`#assistant-bar`, per chat-actions, with Send shown only while the bar is in use) wired to a stub handler so the surface exists from the first screen. Ship the shell's form guard script and the shared page-header partial that marks form headers `page-header-form` (design-system) now, so every slice's forms get the pinned Save and the unsaved-changes prompt for free.
3. Wire activity logging into the shell from the first request: page entry, login/logout events.
4. Verify on a mobile viewport (375px): navigation collapses correctly, no horizontal scroll, no modals anywhere.

**Checkpoint:** user reviews the running shell (desktop and mobile) and confirms the look and feel before Phase 3.

## Phase 3 — Vertical feature slices

Build one feature at a time, end-to-end, in the order fixed in Phase 0. Each slice includes:

1. The screens (list / detail / create / edit as needed) generated per the **new-screen** skill — dedicated full pages, never modals.
2. The PHP handlers per the **php-patterns** skill, against the Phase 1 schema (schema changes at this point are exceptional and need user sign-off).
3. Activity logging for every action in the slice.
4. The slice's screens and actions registered in the **action manifest** (chat-actions) — a slice isn't done until it's reachable by voice.
5. A mobile-viewport check before calling the slice done.

**Checkpoint per slice:** demo the feature, then move to the next.

## Phase 4 — MCP servers and the unified assistant

1. **Build the two read MCP servers** designed in Phase 1 (record memory + activity memory) per the mcp-servers skill: Python + FastMCP, read-only roles, systemd units, Apache reverse proxy, per-client bearer tokens. Verify every Phase 0 question is answerable through the tools using MCP Inspector before moving on.
2. **Build the actions MCP server** from the Phase 1 action manifest per the chat-actions skill: localhost-only, `navigate` + per-action tools calling the app's own endpoints with signed action tokens, undo support.
3. **OS application:** no assistant service and no actions server of the app's own — the command bar posts to the kernel's chat endpoint, the kernel's actions server executes the registry; the two read servers accept the kernel's token (`app_roles`) and agents' run tokens (os-application). **Standalone product:** build the unified assistant — one Claude Agent SDK service (Python) with all three MCP servers, per [references/ama-implementation.md](references/ama-implementation.md) and chat-actions: the AMA page for full conversations, the command bar on every screen for voice actions and navigation. The agent reads memories and performs actions only through MCP.
4. **Verify by utterance:** every manifest action and every screen reachable with a one-sentence spoken-style command; every Phase 0 question answerable.
5. **Publish the client-facing endpoints**: a settings screen listing the two read-MCP URLs and managing access tokens, so clients can connect their own AI tools to their memories (SaaS Plus+). The actions server is never exposed.

## Model tiering (who builds what)

The workflow spends judgment once, up front, on the most capable model available, and hands the repetitive majority to cheaper worker models building against specs. Tier by **class** (specific models change; the classes don't):

| Work | Model class | Current defaults |
|---|---|---|
| Phases 0–1: planning, schema, MCP tool surface, action manifest, **slice build specs** | Planning-class (most capable available) | Fable / Opus |
| Phase 2: auth + shell | Planning-class — security judgment and one-time wiring | Opus |
| Phase 3, **first slice** (the exemplar) | Planning-class | Opus |
| Phase 3, remaining slices | Worker-class | Sonnet by default; Haiku for strictly templated slices |
| Phase 4: MCP servers + unified assistant | Planning-class | Opus |
| Per-slice conformance review | Planning-class at low effort — reviewing a diff costs far less than generating it | Opus/Sonnet |

Four rules make worker-built slices safe:

1. **Build-spec rule.** Phase 1 is not complete until every planned slice has a build spec per [references/slice-build-spec.md](references/slice-build-spec.md) — files, screens, fields, ids, endpoints, manifest entries, all enumerated. A worker model must never face a decision, only substitutions.
2. **Exemplar-slice rule.** The planning-class model builds the first Phase 3 slice; it becomes the canonical reference. Worker prompts are phrased as **replication, not generation**: "Build the `{entity}` slice exactly like the `{exemplar}` slice, per its build spec." Small models replicate patterns far more reliably than they apply pattern documentation.
3. **Conformance gate.** Every worker-built slice passes the mechanical checklist below, then a short planning-class review of the diff, before it counts as done.
4. **Escalation rule.** Worker models never improvise. If the spec is ambiguous, conflicting, or missing something, the worker stops, records the exact question in the spec's Open Questions section, and escalates. An invented decision is a defect even when the code works.

### Per-slice conformance checklist (mechanically checkable)

- Zero `hx-push-url="true"` — every `hx-push-url` value is an explicit canonical URL
- Zero `.modal` usage; `hx-confirm` appears only on destructive controls
- Zero `stretch` / `stretch-full` classes on cards
- All ids follow the kebab-case scheme (`{entity}-list-*`, `{entity}-form-field-*`, `{entity}-row-{id}-*`); no duplicates in any composed DOM
- Every state-changing endpoint: `require_post()` + `verify_csrf()` + authorization check + `log_activity()`
- All dynamic output through `e()`; prepared statements for values; allowlists for SQL identifiers
- Files are exactly the prescribed slice set (php-patterns); query functions take `PDO` as first parameter and never touch request/response
- The slice's screens and actions are registered in the action manifest; new questions have MCP tools
- Every add/edit form page's header is `page-header-form` with `data-form` = its form id (via the shared page-header partial); no Save button outside the page header
- Manual: 375px viewport check; screen renders standalone and as a partial; on a form, scroll to the bottom (Save still visible), edit a field ("Unsaved changes" shows), click a nav link (prompt appears)

## Hard rules that apply in every phase

- **No modal forms or display pages.** No Bootstrap `.modal` for anything that shows or collects data. Create/edit/detail are dedicated full pages loaded via HTMX; quick edits may expand inline; offcanvas drawers only for filters/quick views and full-width on mobile. Confirmations are exempt: `hx-confirm` guards destructive actions.
- **Mobile first.** Every screen must work at 375px width with no horizontal scroll before it is done.
- **Log everything.** Any handler that changes state or renders a screen writes an activity log row.
- **Consistency over creativity.** Never invent new UI patterns; copy the canonical markup from the design-system skill.
