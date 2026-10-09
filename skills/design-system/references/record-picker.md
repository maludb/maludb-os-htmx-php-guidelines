# The record picker — how a form chooses a record

**Decided 2026-10-09** (the plan: `docs/record-picker-plan.md`, D1–D12). A `<select>` that lists every row fails as soon as a
business has many suppliers, items, accounts or people, and it never worked at 375px. Wherever a form chooses a **record**, it
uses the record picker: one searchable modal dialog, defined once here, built the same way in every application. The exemplar
is ProcessCore's purchase order (`/purchase-orders/new`: the supplier, and the line's item that depends on the supplier).

## The rule: select or picker?

| Use a `<select>` | Use the picker |
|---|---|
| An enumeration or a short configuration list: status, kind, priority, unit, weekday, role, a handful of sites or departments | A **record** — anything a person adds rows to without limit: suppliers, customers, items, lots, accounts, people and members, projects, tickets, pages, organizations |

Mechanical test: **if the options come from a table that has its own CRUD list screen, it is a picker.** A configuration list that
can grow past about twenty-five rows (sites of a chain, queues, categories) may use either; use the picker when the screen's
users will search.

The picker is the **second exemption to the no-modal rule** (design-decisions.md "Interaction defaults"), beside `hx-confirm`: it
only chooses and closes. It shows no record and submits no form — creating a record from it opens the screen's existing inline
panel or navigates to the create page (D6), never a form inside the modal.

## The shape (one modal per page, one endpoint per application, one helper per field)

```
field (in the form)          modal (one, in the shell)           endpoint (one per application)
┌──────────────────────┐     ┌────────────────────────────┐      GET /pick/?source=supplier&q=lake&page=1&site_id=3
│ hidden input         │     │ Suppliers              [x] │  ──► html/pick/index.php
│  name=supplier_id    │     │ [🔍 Search suppliers…    ] │        require_login(); the source's roles; q ≤ 80; params whitelisted
│  id=…-field-supplier │     │ ──────────────────────────  │        → app/pickers.php['supplier']['search']($pdo, $q, $params, $page)
│ [ Lakeside Steel  🔍]│ ◄── │ Acme Packaging             │  ◄──   → shared/picker-rows.php (25 rows, "Show more", the chosen row active)
│   (button, form-ctl) │pick │  Packaging · Pat · pat@…   │
└──────────────────────┘     │ Lakeside Steel Mill  ✓     │
                             │ Show more — 25 of 312      │
                             │               [New supplier]│
                             └────────────────────────────┘
```

- **The field** keeps the select's name and id on a hidden input (`data-picker-value`), so the handler, every `hx-include`
  selector, the smokes and the action manifest change nothing. Beside it, inside one `input-group`: a `<button type="button"
  class="form-control text-start record-picker-open">` showing the chosen label (or the placeholder, muted), a Clear button when
  the field is optional and holds a value, the search button, and anything the screen appends (the purchase order keeps its
  "New supplier" button there). `is-invalid` and the `invalid-feedback` line work as on any control.
- **The modal** is ONE `#record-picker` in the shell, outside `#page-content` so swaps keep it: `.modal-dialog-scrollable
  .modal-fullscreen-md-down` — full screen on a phone with the search box pinned above the keyboard, a 560px × 70vh dialog
  on a desktop so it never jumps as results change. The search box carries the htmx request:
  `hx-get="/pick/" hx-trigger="input changed delay:300ms, search, pickerload" hx-target="#record-picker-results" hx-sync="this:replace"
  hx-vals="js:{...recordPickerValues()}"` (htmx wraps a `js:` value in braces unless it starts with one — keep the spread).
- **The rows** (`shared/picker-rows.php`): `button.list-group-item.list-group-item-action.record-picker-row` with `data-value` and
  `data-label`, a bold label line, a muted detail line, an optional badge in the status-color vocabulary; the current value marked
  `active`; an empty state that names the query with its words escaped; a "Show more" row that fetches the next page and replaces
  itself. Page size 25. A later page renders rows only.
- **The script** (`assets/js/record-picker.js`, loaded after `common-init.min.js` on every page) opens, loads the first page,
  picks (writes value + label, dispatches `change` on the hidden input so dependent refreshes fire and the shell's
  unsaved-changes guard flags the form, closes, returns focus to the opener), and does the keyboard: ArrowDown/Up from the
  search box through the rows and back, Enter on a row or Enter in the search box picks (the first match), Escape closes. Focus
  stays inside the dialog through "Show more" (a focused row that a swap removes drops focus on the body, where Escape no longer
  closes a Bootstrap modal). A pick during the opening transition waits for `shown` (Bootstrap ignores `hide()` while opening).
  The modal is created with `{ focus: false }` so the search box, not the dialog, takes focus.
- **The endpoint** (`html/pick/index.php`) is a Pattern A fragment: `require_login()`, the source's `roles` through
  `user_can()` (or the application's equivalent), `source` cleaned to `[a-z0-9_]`, `q` cut at 80 characters, `page ≥ 1`, only the
  source's declared `params` read (as integers), `selected` echoed back to mark the row. Refusals answer a **fragment** — 404 "That
  list does not exist.", 403 "You do not have permission…" — never a page, because the answer lands inside the modal. A search is
  not an action: nothing is logged. The browser asks for `/pick/` with the trailing slash so Apache serves the directory index
  without a redirect.
- **The registry** (`app/pickers.php`) returns `key => ['title', 'noun', 'roles' => [...], 'params' => [...], 'search' => fn(PDO,
  string $q, array $params, int $page): array{rows, total, page, page_size, pages}, 'label' => fn(PDO, int $id): ?string]`.
  `search` **reuses the feature's own list query** (every list screen already has `find_<entity>($pdo, $search, $sort, $page)` with
  ILIKE and paging) and maps rows to `[id, label, detail?, badge?, color?]`; `label` is how a form shows the chosen record on first
  render and after a 422. A dependent source (items of the chosen supplier) declares the param and orders or filters by it.
- **The helper** (`app/picker.php`): `picker_field(array $spec)` for any markup, `form_picker($prefix, $name, $label, $source,
  $value, $errors, $opts)` for the standard form row. `$spec`/`$opts`: `placeholder`, `title`, `search` (the box's placeholder),
  `params` (fixed filters), `include` (CSS selectors whose live values filter the search — their `name`s must be declared params),
  `required` (no Clear), `disabled`, `label` (skip the lookup when the screen already has it), `extra` (attributes on the hidden
  input: a dependent refresh's `hx-get … hx-trigger="change" hx-target … hx-include …`), `append` (more input-group buttons),
  `create` (`['label' => 'New supplier', 'panel' => '#…-new-supplier']` opens an inline panel; `['label', 'url']` navigates).
  An id that names nothing renders as no choice (the placeholder), never an error.

The PHP files, verbatim from the exemplar: `examples/php/record-picker/` (its README says where each goes). The browser side:
`examples/assets/js/record-picker.js`, the RECORD PICKER section of `examples/assets/css/app-overrides.css`, the modal in
`examples/index.html` and `references/layout-skeleton.html`, a field in `examples/partials/customer-form.html`, rows in
`examples/partials/picker-rows.html`.

## The field's markup (what `picker_field()` emits)

```html
<div class="input-group record-picker-field" id="order-form-field-supplier-id-picker"
     data-picker-source="supplier" data-picker-title="Suppliers" data-picker-placeholder="Choose a supplier"
     data-picker-search="Search suppliers…" data-picker-include="#order-form-field-site-id"
     data-picker-create-label="New supplier" data-picker-create-panel="#order-form-new-supplier">
    <input type="hidden" name="supplier_id" id="order-form-field-supplier-id" value="12" data-picker-value />
    <button type="button" class="form-control text-start record-picker-open" id="order-form-field-supplier-id-open"
            aria-haspopup="dialog" aria-controls="record-picker" aria-labelledby="order-form-field-supplier-id-label order-form-field-supplier-id-open">
        <span class="record-picker-label">Lakeside Steel Mill</span>              <!-- or class "record-picker-label text-muted" + the placeholder -->
    </button>
    <button type="button" class="btn btn-light-brand record-picker-clear" id="order-form-field-supplier-id-clear" aria-label="Clear"><i class="feather-x"></i></button>   <!-- optional fields only; d-none while empty -->
    <button type="button" class="btn btn-light-brand record-picker-search-btn" id="order-form-field-supplier-id-search-btn" aria-label="Search"><i class="feather-search"></i></button>
</div>
```

Ids follow the scheme: the hidden input is the field (`{screen}-field-{name}`); the wrapper `-picker`, the opener `-open`, the
buttons `-clear` and `-search-btn`; the row label `-label` points its `for` at `-open`. Rows in the modal are
`record-picker-row-{id}`; the modal's parts are fixed (`record-picker`, `-title`, `-search`, `-results`, `-list`, `-more`,
`-empty`, `-error`, `-footer`, `-create-btn`, `-close`).

## Building it into a new application (Phase 2, with the shell)

1. Copy the PHP files from `examples/php/record-picker/` to their places; require `app/picker.php` from the bootstrap after
   `ui.php`; start `app/pickers.php` empty (`return [];`) — each slice adds its sources.
2. Put the `#record-picker` modal in the layout after `</main>` and `<script src="/assets/js/record-picker.js">` after
   `common-init.min.js`; the shell's unsaved-changes guard admits a picker's hidden input
   (`t.type !== "hidden" || t.hasAttribute("data-picker-value")`). The CSS section is already in `app-overrides.css`.
3. In every slice: a form that chooses a record uses `form_picker()` / `picker_field()`; the slice adds the source to
   `app/pickers.php` over the feature's `find_*()`; the manifest lists `/pick/` once under URL conventions, not per source.
4. Prove it with the slice: the endpoint's refusals, search, paging, the dependent param; the form's first render, a 422
   re-render keeping the label, a save through the hidden input. ProcessCore's `scripts/prove-picker.sh` is the model (46 checks).

## What the retrofits taught (2026-10-09 — eight applications in one day)

The contract bends in these places, and only these; every bend is written into the application's `app/pickers.php` comment:

- **The gate.** An application without a flat role column (Help Desk, GL, Knowledge — rights; HR, txtSchedules, Spaces — a test
  per source) replaces `roles` with `rights => [...]` (any one admits, through its `has_right()`) or a `gate => fn(array $user,
  array $params): bool` closure that applies the SAME test as the screens that use the field. The endpoint calls whichever the
  source carries. A people source in a scoped application takes the site/space/project as a declared param and the gate fails
  for one the caller does not hold.
- **Keys that are not integers.** Where records are UUIDs (Spaces' pages and databases), `params` is `name => 'int'|'uuid'` and
  the endpoint validates each by type; `selected` and `label()` accept the same. Integers everywhere else.
- **Paging.** When the feature's list query is un-paged or computes balances per row (HR, GL, Spaces), the source gets its own
  light paged query over the same view and the same ILIKE, or pages the list in PHP; a picker never runs the list screen's
  heavy query.
- **4xx into the modal.** If the shell's `htmx-config` swaps no 4xx but 422, a `htmx:beforeSwap` hook in the layout swaps 403/404
  into `#record-picker-results` only (GL), so refusals still show inside the dialog.
- **Labels.** A hand-written form's `<label for>` must move to `{id}-open`, or clicking the label does nothing (the hidden input
  cannot take focus). Pass `labelledby` so the opener has an accessible name.
- **Sentinels.** "Me", "Nobody", "Unassigned", "Keep" are not records: a select that offers them stays a select (Help Desk, Projects
  filters and bulk forms), or the source puts the caller first as a "You" row when the old select offered "Me" (txtSchedules'
  `self=1`). Multiple selects, arrays of selects per row and trees shown by indentation stay selects.
- **No JavaScript.** The picker needs it; a plain form cannot set the hidden input. Accepted for these applications; a no-JS proof
  pre-selects by query string.
- **Native `required` is lost** on a hidden input: the handler's own refusal does the stopping, as it always had to.
- **Scratch installs.** Several applications' `tests/setup_dev.sh` rewrite the database roles' passwords when the checkout has no
  `config/.env` — and the roles are SHARED with the live install. Put the scratch `config/.env` in place before any proof runs.

## Retrofitting an existing application

1. Add the files of step 1–2 above (the shell and bootstrap change once). Applications without `form_row_open()` skip
   `form_picker()` and call `picker_field()` inside their own label markup.
2. List the candidates: every `<select>` whose options come from a table with a list screen (grep the option loops —
   `$suppliers`, `$customers`, `$items`, `$members`, `$accounts`, `$people`, `$projects`…). Sites, departments, statuses, kinds,
   units stay selects unless they grow.
3. For each candidate: add the source (reuse the feature's list query; add a `label` lookup), replace the select with the picker
   under the **same name and id**, pass `label` when the screen already holds it, move any `hx-trigger="change"` refresh onto
   the hidden input through `extra`, and if the select was filtered by another field, declare the param and `include` the
   field. Applications with one-purpose helpers (`customer_select()`, `account_select()`) convert the helper once.
4. Keep inline create panels; wire them through `create`. Never put a form in the modal.
5. Prove as above; check a form at 375px: the dialog fills the screen, the search box stays above the keyboard.
