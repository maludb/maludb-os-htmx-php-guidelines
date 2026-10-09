---
name: new-screen
description: Generate a new screen (list, detail, create/edit form, report, or settings page) in an HTMX + PHP + Bootstrap 5.3 application built with this plugin. Use when the user asks to "add a screen", "create the customers page", "build the edit form", or invokes /new-screen. Guarantees every screen matches the design system and mobile rules.
---

# New Screen Workflow

Every screen must be indistinguishable in style from the rest of the application. Never design a screen from scratch — assemble it from the canonical patterns.

## Steps

1. **Read the design system first.** Open the design-system skill's references — `design-decisions.md` first (it overrides everything), then `layout-skeleton.html` / `components.md`, and start from the matching page in `examples/`:
   - List screen → the canonical "Traffic Reports" table card with server-rendered pagination (`examples/partials/customers-list.html`)
   - Detail screen → view/panels pattern (`examples/partials/customer-view.html`)
   - Create/edit → full-container form partial (`examples/partials/customer-form.html`)
   - Auth-related → minimal auth layout (`examples/auth-login.html`)
2. **Follow the php-patterns skill** for the file layout and handler structure: endpoints under `/var/www/html/{feature}/` (the Apache DocumentRoot) (`index.php`, `form.php`, `save.php`, `delete.php`), query functions in `app/features/{feature}/queries.php`, views under `app/views/{feature}/` (`page.php` + `partials/`). One screen = the pattern's prescribed files, no more.
3. **HTMX wiring.** The screen renders as a partial into the shell's main content target (see the marked `MAIN CONTENT TARGET` comment in the layout skeleton). Navigation links use `hx-get` + `hx-push-url` with an **explicit canonical URL — `hx-push-url="true"` is banned** (it pushes the partial's path and breaks relative URLs); forms use `hx-post` returning either the updated partial or an `HX-Redirect` header. The page must also render standalone (full layout) when requested directly, so deep links and refreshes work.
4. **Interaction rules (no modals) — the CRUD contract:**
   - Every list screen: "Add <Entity>" primary button in the page-header actions + an explicit Edit button on every table row; both `hx-get` the same full-container form partial (empty vs pre-filled) into the shell swap target with `hx-push-url`.
   - Create/edit/detail: dedicated full pages.
   - Quick single-field edits: inline HTMX swap in place.
   - Filters or quick previews only: offcanvas drawer, full-width on mobile. Never put a form submit inside an offcanvas.
   - Confirmations: `hx-confirm` on the destructive control (the no-modal rule covers forms and display pages, not confirmations).
   - **A field that chooses a record** (a supplier, a customer, an item, a person — anything with its own list screen) is the **record picker** (`design-system/references/record-picker.md`): `form_picker()` / `picker_field()` under the field's name and id, a source added to `app/pickers.php` over the feature's `find_*()` query. A `<select>` is only for enumerations and short configuration lists.
5. **Activity logging.** The handler logs screen entry and every state-changing action to the activity log.
6. **Mobile check before done.** Verify at 375px: no horizontal scroll, tables wrap in `.table-responsive`, toolbars stack, touch targets ≥ 44px. A screen is not finished until this passes.
7. **Register it.** Add the sidebar/menu entry using the exact menu-item markup from the layout skeleton.

## Consistency checklist (apply to every screen)

- Every meaningful element carries a unique, stable, kebab-case `id` following the scheme in `design-decisions.md` (`{screen}-{element}`, `{screen}-field-{name}`, `{entity}-row-{id}`, …); verify no id collides with the shell's or the screen's own ids.
- Page header block (title + breadcrumb + actions) copied from the canonical pattern — same classes, same structure. Create/edit forms use the pinned form header (`page-header-form` + `data-form`, unsaved badge) from `components.md`; Save/Cancel only in that header.
- No `<select>` whose options are a table with a list screen — those fields are record pickers (`record-picker.md`), and each has its source in `app/pickers.php`.
- Cards, buttons, badges, form controls use only classes that appear in `components.md`. If a needed component is missing there, extend the design system reference first, then use it — never improvise one-off styling.
- No inline `style=` attributes; no new CSS files unless the user explicitly approves adding to the theme.
