# The record picker — a plan (2026-10-09, for the owner's checkpoint)

## The problem

Every form in every application chooses records with `<select class="form-select">`: the purchase order's supplier
(`/purchase-orders/new` in ProcessCore and the cidery), the line's item, the GL's vendor, customer and account, the
Help Desk's assignee, the Projects' member. A native select lists every row and offers no search beyond type-ahead on
the first letters. With a few dozen suppliers it is tolerable; with hundreds of suppliers, items, accounts or people it
is not — and it never worked at 375px, where the browser's own picker covers the screen and the list cannot be searched
at all.

The owner asked for one reusable element: a modal dialog with a searchable list, used wherever a form chooses a
record, defined once in the builder plugin so every new application has it and the existing applications can be
retrofitted.

## What exists today (the survey)

| Application | Files with a select | How selects are written | Entity lists chosen (candidates) |
|---|---|---|---|
| processcore | 37 | `form_select()` in `app/ui.php` (104 calls) | supplier, item (`$catalog`, in a line row that refreshes on change), product, production order, sales order, purchase order, equipment, location, lot |
| cidery | 46 | the same `app/ui.php` (130 calls) | supplier, customer, product, item, lot, vessel, recipe version, premises, location |
| gl | 51 | shared one-purpose helpers `account_select()` (20 uses), `vendor_select()` (8), `customer_select()` (8), `party_select()` (4) | accounts, vendors, customers, items, banks, members |
| helpdesk | 45 | inline `<select>` markup | members/assignees, organizations, queues, categories |
| consultant_tracking | 34 | inline | clients, engagements, members |
| projects | 31 | inline (one `data-select2-selector`) | members, projects, sprints |
| spaces | 31 | inline | members, pages, spaces |
| txtschedules | 23 | inline | people, positions, sites |
| hr | 22 | inline | people, managers, departments |
| inventory, knowledge | 4–5 | inline; Phase 2+ not built yet | will use the picker from the start |

Related things already in the estate: the Help Desk's inline article picker (`kb/partials/article-picker.php` — a
search input over a Pattern A fragment, the model for the list half), the GL's "the ONE picker" helpers (a good seam:
the picker replaces each in one file), the PO form's inline "New supplier" panel (kept: a form never goes in a modal).
The shell's `vendors.min.js` includes Bootstrap's bundle (Modal), the theme styles `.modal-content` in dark mode, and
`modal-fullscreen-md-down` exists in the Bootstrap build. htmx is 2.0.4.

## The element

**One shared modal per page, one search endpoint per application, one helper per form field.**

- The field. A hidden input carries the value under the field's existing name and id
  (`<input type="hidden" name="supplier_id" id="purchase-order-form-field-supplier-id">`), so handlers, `hx-include`
  selectors, smokes and the action manifest change nothing. Beside it, an input-group: a button styled as the control
  (`.form-control.text-start`, truncating) that shows the chosen record's label or the placeholder and opens the
  picker; a Clear button when the field is optional and holds a value; the search icon. Focusable, opens on
  Enter/Space, the usual `is-invalid` + feedback line when the handler refused.
- The modal. ONE `#record-picker` modal in the layout, outside `#page-content` so swaps never destroy it:
  `.modal-dialog.modal-dialog-scrollable.modal-fullscreen-md-down`; header = the field's title + close; body = a
  search input pinned at the top and a `list-group` of results with a visible scrollbar (rule 8). On a phone it fills
  the screen with the search box above the keyboard; on a desktop it is a centred dialog.
- The rows. The endpoint returns the list fragment (`shared/picker-rows.php`): one `button.list-group-item
  list-group-item-action` per row, 44px tall, a label line and a muted secondary line (code, email, city, a status
  badge), the current value marked; an empty state ("No supplier matches “x”.") with the optional "New supplier"
  action; a "Show more" row that fetches the next page in place. Page size 25.
- The script. `assets/js/record-picker.js` (~100 lines, vanilla, loaded after `common-init` on every page): open
  (read the field's `data-picker-*`, set the title, load the first page), search (debounced 300 ms, `htmx.ajax` GET
  with the field's fixed params and the live values of its `include` selectors), pick (write value + label into the
  field, dispatch `change` on the hidden input so dependent HTMX refreshes fire, mark the form dirty, close, return
  focus), keyboard (ArrowUp/Down across rows, Enter picks, Escape closes). No state outside the DOM.
- The endpoint. `GET /pick?source=<key>&q=&page=&<declared params>` (`html/pick/index.php`), login required, the
  source's role checked with `require_role()`, unknown source → 404, `q` capped at 80 characters, params
  whitelisted per source, no activity log (a search is not an action). It answers `shared/picker-rows.php` only.
- The registry. `app/pickers.php`: one entry per pickable record kind —
  `['role' => 'receiving', 'params' => ['site_id'], 'search' => fn(PDO, string $q, array $params, int $limit, int $offset): array, 'label' => fn(PDO, int $id): ?array]`.
  `search` reuses the feature's existing `find_<entity>()` query (every list screen already has one with ILIKE
  search); `label` is how a form shows the chosen record on first render and after a 422 (`picker_label($pdo, 'supplier', $id)`).
- The helper. `picker_field(array $spec): string` in `app/picker.php` emits the control for any markup style;
  `form_picker($prefix, $name, $label, $source, $value, $errors, $opts)` in `app/ui.php` wraps it in the standard
  `col-lg-4` / `col-lg-8` row for the applications that have `ui.php`. Options: `placeholder`, `title`, `params`
  (fixed filters), `include` (selectors whose live values filter the search — the PO line's item picker includes
  the supplier field), `required`, `disabled`, `create` (a panel selector to open or a URL to go to — never a form in
  the modal), `extra` (attributes on the hidden input, e.g. the `hx-get … hx-trigger="change"` the item select carries today).

A dialog that only chooses is neither a form nor a display surface; it joins `hx-confirm` as the second exemption to
the no-modal rule (D1).

## Decisions (the owner's; each with a recommendation)

| # | Question | Options | Recommendation |
|---|---|---|---|
| D1 | The no-modal rule (design-system rule 2, new-screen step 4, design-decisions "Interaction defaults") | (a) amend: confirmations and the record picker are the two exemptions; (b) build the picker as an offcanvas drawer instead | **(a)**. A picker chooses and closes; it shows no record and submits no form. The owner asked for a modal, and on a phone a full-screen modal with its own search box is the right shape (a dropdown combobox is covered by the keyboard; an offcanvas is for filters). |
| D2 | Where the rows come from | (a) a server search endpoint over the feature's existing `find_*()` query, paged; (b) the whole list embedded in the page and filtered in the browser | **(a)**. (b) still ships every row into every form — the thing that fails at scale. |
| D3 | One endpoint + a registry, or an endpoint per feature | (a) `html/pick/index.php` + `app/pickers.php` listing the sources; (b) `html/<feature>/pick.php` each | **(a)**. A retrofit is one file and one line per source; the modal script and the rows partial never vary. Each source still names its role and reuses the feature's own query function, so php-patterns' dependency direction holds. |
| D4 | The modal | (a) ONE shared modal in the layout, re-targeted per open; (b) one modal rendered per picker field | **(a)**. A PO with ten lines would otherwise carry ten modals. |
| D5 | The field's value | (a) hidden input under the select's old name and id + a display button; (b) a visible text input with the label and a separate id field | **(a)**. Nothing downstream changes: handlers, `hx-include` selectors, smokes, the action manifest. |
| D6 | Creating a record from the picker | (a) `create` option: open the screen's existing inline panel (the PO's "New supplier") or navigate to the create page with `?back=`; (b) a create form inside the modal | **(a)**. Rule 2 stays whole: no form in a modal. |
| D7 | The rule for when a select stays a select | A `<select>` is for an enumeration or a short configuration list (status, kind, unit, priority, a handful of sites or departments). A **picker** is for records: anything a person adds rows to without limit — suppliers, customers, items, lots, accounts, people, projects, tickets, pages. Mechanical test: if the options come from a table with its own CRUD list screen, it is a picker. | **adopt as written**, in design-decisions.md, new-screen step 4 and new-app's per-slice checklist. |
| D8 | Multiple selection | (a) single value in v1, multi (chips) a documented extension; (b) multi now | **(a)**. No form in the survey needs multi yet. |
| D9 | Ordering of the first page | (a) the source's own order (name, or code — name), the current value marked; (b) recently used first | **(a)** in v1; a source may order by relevance later without changing the contract. |
| D10 | Where the exemplar is built | (a) ProcessCore first: the PO supplier (plain) and the PO line item (dependent on the supplier, refreshes the row on change) — then the plugin, then the cidery (an identical fork of that form); (b) the plugin first from the HTML example alone | **(a)**. Proven code goes into the plugin, not the other way round. |
| D11 | The retrofit of the other nine applications | (a) one Sonnet worker per application following the reference's retrofit steps, the candidate fields listed per app in the step, the planning model reviewing each; (b) the planning model does them all | **(a)**, after the plugin ships. GL first (its four helpers become pickers in four files), then Help Desk, Projects, Consultant Tracking, HR, txtSchedules, Spaces; Inventory and Knowledge use the picker natively. |
| D12 | The kernel's React app | out of scope: a different stack with its own kit | **agree** — note it, do nothing. |

## The build order

**Status (2026-10-09):** approved as recommended; step 1 BUILT and live (ProcessCore commit 99d978e: 46 HTTP checks, 19 browser checks); step 2 the plugin 0.8.0 BUILT the same day; step 3 the cidery LIVE (c4e1c64, 55 + 19); step 4 by Sonnet workers in worktrees the same day — Projects 159280d (80 + 23), HR 34ae1bd (76 + 18), GL fa3d7b0 (167 incl. 27 browser), txtSchedules d41217c (67 + 50), Spaces 8386e83 (275 incl. 31 browser), Knowledge 11d2207 (shell only, 22), each merged to main after review; Help Desk df02303 proven (66 + browser), merge held until the owner restores the live roles' passwords its worker rotated; Consultant Tracking c8f719e (97 + 26) merged; Inventory has no shell yet and will take it from the plugin.

0. **This plan** — committed in the plugin repository (`docs/record-picker-plan.md`); the checkpoint.
1. **ProcessCore, the exemplar** (`/srv/apps/processcore`): `app/picker.php` (`picker_field`, `picker_label`),
   `app/pickers.php` (sources `supplier`, `item` with param `supplier_id`), `html/pick/index.php`,
   `app/views/shared/picker-rows.php`, the modal in `layout.php`, `assets/js/record-picker.js`, the CSS block in
   `app-overrides.css` ("RECORD PICKER"), `form_picker()` in `app/ui.php`, the shell guard accepting a picker's
   change; the PO supplier block and the PO line row converted. Proof `scripts/prove-picker.sh` (the endpoint: 404
   unknown source, 403 wrong role, search, escaping, paging, a declared param; the form renders the chosen label on
   first render and after a 422; a PO saves through the hidden field; the line row still refreshes on an item pick),
   wired into `prove-all.sh`; a screenshot pass at 375px and desktop in light and dark. Commit.
2. **The plugin 0.8.0** (`~/htmx-php-builder`): `skills/design-system/references/record-picker.md` (the component:
   markup, the helper API, the endpoint and registry contracts, the rows partial, the script's events, the rule of
   D7, the retrofit steps); `examples/assets/js/record-picker.js`; the CSS block in `examples/assets/css/app-overrides.css`;
   the modal in `examples/index.html` and `references/layout-skeleton.html`; a picker field in
   `examples/partials/customer-form.html` and `examples/partials/picker-rows.html`; `assets.md` (the script in the
   every-page list); `components.md` (a "Record picker field" section pointing at the reference); design-decisions.md
   (D1's amendment and D7's rule); SKILL.md rule 2 and the references list; new-screen step 4; new-app's per-slice
   checklist; php-patterns' pattern table (Pattern A's row names the picker endpoint). The PHP reference
   (`app/picker.php`, `html/pick/index.php`, `shared/picker-rows.php`) ships verbatim under
   `skills/design-system/examples/php/` since the plugin has no PHP examples yet. README, plugin.json. Commit.
3. **The cidery** (`/srv/apps/cidery`): the same files, the PO supplier and item fields, and the customer, product,
   lot and vessel pickers listed in the survey; `scripts/prove-picker.sh` on `cidery_dev`. Commit.
4. **The retrofit sweep** (D11), one application at a time, each a worker with the reference and the app's candidate
   list; the planning model reviews and commits.
5. **The owner's**: applying each application's assets on the live installs (no migration — the picker needs no
   schema), the Apache side needs nothing new (`/pick/` is an ordinary PHP path under the vhost).

Nothing here touches the kernel, a database or an MCP registry. The hand-off and actions contracts are unchanged.
