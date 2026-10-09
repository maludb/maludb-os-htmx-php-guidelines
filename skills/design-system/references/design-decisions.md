# Locked design decisions

These choices are made once, here, so application builds require as little design editing as possible. Where this file conflicts with `components.md` or the original template, **this file wins**. Every screen uses these defaults unless the user explicitly overrides them for a specific project.

## Tables (DEFAULT for every list screen): the "Traffic Reports" pattern

Source: `widgets-tables.html`, "Traffic Reports" card. A plain hover table in a flush card — **not** DataTables. Structure:

```html
<div class="col-lg-12">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Customers</h5>
            <div class="card-header-action">
                <div class="card-header-btn">
                    <div data-bs-toggle="tooltip" title="Refresh">
                        <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                    </div>
                    <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                        <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                    </div>
                </div>
                <div class="dropdown">
                    <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                        <div data-bs-toggle="tooltip" title="Options"><i class="feather-more-vertical"></i></div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a href="#" class="dropdown-item"><i class="feather-plus"></i>New</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body custom-card-action p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Name</th>
                            <th>…</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <a href="…">
                                    <span class="wd-10 ht-10 bg-success me-2 d-inline-block rounded-circle"></span>
                                    <span>Row title</span>
                                </a>
                            </td>
                            <td>value <small class="text-muted">(secondary)</small></td>
                            <td class="text-end">
                                <a href="…"><i class="feather-more-vertical"></i></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
```

Rules that follow from this choice:

- **No DataTables on application list screens.** Pagination, search, and filtering are server-rendered (PHP + HTMX): a Bootstrap pagination bar under the table (`hx-get` per page link) and a search input in the page header posting via `hx-get` with a debounce (`hx-trigger="keyup changed delay:400ms"`). No client-side table JS to re-initialize after swaps.
- The first cell is the row's link (with the colored status dot where a status exists); the last cell is right-aligned actions.
- Row status is expressed by the `wd-10 ht-10 bg-{color} … rounded-circle` dot and/or a badge, using the standard status-color vocabulary below.
- Secondary values inside a cell use `<small class="text-muted">`.

## The CRUD screen contract (every entity)

Each entity's list page is the canonical table card with:

1. **An "Add <Entity>" primary button at the top** (in the page-header actions area), and
2. **An explicit Edit button on every row** (right-aligned actions cell — `feather-edit` icon button, not a hidden dropdown).

Both buttons navigate to **full-container forms rendered as HTMX partials** — `hx-get` the form partial into the shell's swap target (`#page-content`) with `hx-push-url` set to an **explicit canonical screen URL** — `hx-push-url="true"` is banned outright (it pushes the partial's fetch path, shifting the document base and breaking relative URLs; never use the `"true"` form anywhere). The same form partial serves add (empty) and edit (pre-filled, keyed by record id in the URL). Never a modal, never an inline drawer form. Save/Cancel live in the form's page header; Save `hx-post`s and returns either the updated list partial or an `HX-Redirect`; Cancel `hx-get`s back to the list.

Two adjacent rules:

- Partials carry their own `.page-header` + `.main-content` and swap the whole `#page-content` (`.nxl-content`) region; search/pagination refresh only the results region within the screen.
- **Login/logout are full page navigations, never HTMX swaps** — session regeneration and the post-login redirect need a real navigation (see php-session-auth).

## Choosing a record: the picker, not a select (DECIDED 2026-10-09)

A `<select>` is for an enumeration or a short configuration list (status, kind, priority, unit, a handful of sites or
departments). A **record** — anything a person adds rows to without limit: suppliers, customers, items, lots, accounts, people,
projects, tickets, pages — is chosen through the **record picker**: a hidden input under the field's name and id, a button
showing the chosen label, and the shell's one `#record-picker` modal with a search box over `GET /pick/?source=`. Mechanical
test: if the options come from a table with its own CRUD list screen, it is a picker. The component, its contracts and the
retrofit steps are `record-picker.md`; the exemplar is ProcessCore's purchase order. Single value in v1; the first page in the
source's own order with the chosen row marked; creating a record from the picker opens the screen's inline panel or the create
page, never a form in the modal.

## Scrollbars: always visible

The theme's stock scrollbars (5px, near-invisible thumb, some hidden outright) are overridden by the sanctioned `assets/css/app-overrides.css`, loaded after `theme.min.css` on every page: 12px grabbable scrollbars with contrasting thumbs in light and dark mode. Never hide a scrollbar (`scrollbar-width: none`, `::-webkit-scrollbar { display: none }`) and never `overflow: hidden` on a content panel — fixed-height panels get `overflow-y: auto` and show their scrollbar.

## Interaction defaults (recap of plugin-wide rules)

- Create/edit/detail: dedicated full pages via HTMX (`hx-get` + `hx-push-url` into the shell's swap target). No modal forms or display panels. The no-modal rule has **two exemptions**: confirmations (destructive actions use `hx-confirm`) and the **record picker** (`record-picker.md`), the one `.modal` in the shell, which only chooses a record and closes.
- Quick single-field edits: inline swap. Filters/quick views only: offcanvas, full-width on mobile.
- Form save/cancel buttons live in the page header (`.page-header-right-items-wrapper`); forms use the `col-lg-4` label / `col-lg-8` control horizontal grid with `input-group` icon prefixes.
- Form headers are pinned (`page-header-form`): Cancel/Save stay in view at the top while the form scrolls, inline on phones; editing shows "Unsaved changes" and leaving asks first. No second Save at the bottom of a form.
- The shell footer (`#page-footer`) is pinned: it sits at the bottom of the viewport directly above the assistant bar when a screen is shorter than the viewport, and follows the content when the screen is longer. Done in `app-overrides.css` by making `.nxl-container` a flex column whose minimum height is the viewport less the 80px header and `--assistant-bar-height`; never let a footer float mid-screen under short content.
- Tabs on create/view pages: the card-header nav-tabs pattern (`.card.border-top-0 > .card-header.p-0 > ul.nav.nav-tabs...`).

## Assistant command bar (every screen)

A fixed bottom command bar (`#assistant-bar`, input `#assistant-input`) ships on every screen — the voice-first entry point for chat actions and navigation (see the chat-actions skill). Latest exchange in `#assistant-reply` above the bar; full history in offcanvas `#assistant-transcript` (full-width on mobile); `Ctrl/Cmd+K` or `/` focuses the input. Body reserves bottom padding so the bar never covers content: the shell measures the bar into the `--assistant-bar-height` CSS variable (ResizeObserver, 84px fallback), which also sizes the pinned footer. Its Send button (`#assistant-send-btn`) shows only while the bar has focus or holds text — an always-visible Send at the bottom of every screen was mistaken for the page's Save. Confirmation policy: creates/updates act immediately with an Undo affordance; destructive actions always confirm; navigation pushes explicit canonical URLs via `HX-Location`.

## Auth pages: minimal style

All auth screens (login, register, reset, verify, 404, maintenance) use the **minimal** variants (`auth-*-minimal.html`). The cover and creative variants are not used.

## Authentication composition (per php-session-auth + its references)

- CSRF is the default on every state-changing request, including HTMX (`X-CSRF-Token` header via the shell's `htmx:configRequest` listener + meta tag; hidden input on full-page forms).
- The login page offers email/password **and** a "Sign in with Google" button (`auth-login-google-btn`, server-side OIDC redirect flow — no Google JS widgets).
- Authenticator-app 2FA (TOTP) is available on every account: enrollment on a dedicated settings page, challenge on a dedicated `/login/2fa` page, recovery codes. 2FA applies to Google sign-ins too.
- Login, logout, and the 2FA challenge are **full page navigations** — never HTMX swaps.

## Default dashboard: stat cards + table

The Phase 2 dashboard is 3–4 stat cards over one canonical-pattern table (recent activity or the primary entity). No chart libraries in the default bundle; ApexCharts is added only when a project's plan demands charts.

## Status-color vocabulary (all badges and row dots)

| Color | Meaning |
|---|---|
| `success` | active / complete |
| `warning` | pending / in review |
| `danger` | overdue / failed / blocked |
| `info` | in progress |
| `secondary` | inactive / archived |
| `dark` | draft |

Every entity's states map onto these in Phase 1; never invent a new color-meaning pair per screen.

## Element IDs: everything addressable, everything stable

Every meaningful HTML element carries a unique, semantic, kebab-case `id` so future change requests can name elements precisely (e.g. "make `left-sidenav` collapsible by default"). This is a communication contract between the owner and the model.

**Naming scheme:**

| Level | Pattern | Examples |
|---|---|---|
| Layout regions (shell) | fixed names | `left-sidenav`, `top-header`, `page-content`, `page-footer` |
| Screen containers | `{screen}-{element}` | `customers-list-header`, `customers-list-table`, `customers-list-pagination` |
| Actions | `{screen}-{action}-btn` | `customers-list-add-btn`, `customer-form-save-btn`, `customer-form-cancel-btn` |
| Form fields | `{screen}-field-{name}` | `customer-form-field-email` (the `<input>`; its wrapper row gets `-row`, its label `-label`) |
| Table columns | `{screen}-col-{name}` | `customers-list-col-email` (on the `<th>`) |
| Generated rows | `{entity}-row-{record id}` | `customer-row-1042`; cells `customer-row-1042-email` |
| Nav items | `nav-{screen}` | `nav-dashboard`, `nav-customers-list` |

**Rules:**

1. Prefix screen-level ids with the screen name — partials compose into the shell, so ids must be unique across the shell *plus* the active partial, and screen prefixes prevent cross-screen ambiguity in conversation.
2. IDs are **stable across the application's lifetime** — never rename one once shipped; the id is how changes are requested. Template-required ids (`mobile-collapse`, `menu-mini-button`, …) keep their original names because theme JS depends on them.
3. Loop-generated elements interpolate the record's primary key (`customer-row-<?= $id ?>`).
4. Purely decorative leaves (icons, status dots, `<small>` fragments) don't need ids — their addressable parent covers them. Everything a person might plausibly ask to change does.
5. Every new screen's ids follow this scheme mechanically; no creativity, no abbreviations.

## Date and time display

- Dates: **medium format — `Jan 15, 2026`**; times: `2:30 PM`.
- Exact timestamps (with seconds/timezone) appear in tooltips and detail views, not in table cells.
- Store everything in PostgreSQL as `timestamptz` UTC; format at render time.
