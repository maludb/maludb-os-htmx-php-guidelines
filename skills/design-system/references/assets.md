# Duralux asset manifest — what every page must include, and in what order

Paths are relative to the site root (pages live next to `assets/`).

## Required on EVERY page (app and auth), exact order

Head CSS (in `<head>`, in this order):

```html
<link rel="shortcut icon" type="image/png" href="assets/images/favicon.png">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">   <!-- 1. Bootstrap 5.3 (theme build) -->
<link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css"> <!-- 2. vendor bundle -->
<!-- 3. page-specific vendor CSS here (see per-page table) -->
<link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">       <!-- 4. theme overrides -->
<link rel="stylesheet" type="text/css" href="assets/css/app-overrides.css">  <!-- 5. ALWAYS LAST: sanctioned plugin overrides (visible scrollbars etc.) -->
```

Bottom JS (end of `<body>`, in this order):

```html
<script src="assets/vendors/js/vendors.min.js"></script>  <!-- 1. ALWAYS FIRST ("always must need to be top"): jQuery + Bootstrap + PerfectScrollbar + pace + nxlNavigation + full-screen-helper etc. -->
<!-- 2. page-specific vendor JS here (see per-page table) -->
<script src="assets/js/common-init.min.js"></script>          <!-- 3. shared shell init (required on every page) -->
<script src="assets/js/record-picker.js"></script>          <!-- 4. the record picker (references/record-picker.md) — every page that has a form -->
<!-- 5. page-specific init here, e.g. customers-init.min.js -->
<script src="assets/js/theme-customizer-init.min.js"></script> <!-- 6. ALWAYS LAST: skins/dark-mode (also powers the header dark/light toggle) -->
```

Ordering rules that matter:
- `theme.min.css` must come after all vendor CSS or component skins break; `app-overrides.css` comes after `theme.min.css` so the sanctioned overrides (visible scrollbars) win the cascade.
- `vendors.min.js` must load before anything else (jQuery lives there).
- `common-init.min.js` must come before the page init and after all vendor JS.
- `theme-customizer-init.min.js` is included on every page, even pages without the customizer
  panel, because it applies the persisted skin/font from localStorage on load and drives the
  header `.dark-button`/`.light-button` toggle. Omit it and dark mode stops working.

## Page-specific vendor files (from the actual pages)

| Page type | Extra head CSS (after vendors.min.css) | Extra JS (after vendors.min.js) | Page init |
|---|---|---|---|
| Dashboard (index.html) | daterangepicker.min.css | daterangepicker.min.js, apexcharts.min.js, circle-progress.min.js | dashboard-init.min.js |
| List/table (customers.html) | dataTables.bs5.min.css, select2.min.css, select2-theme.min.css | dataTables.min.js, dataTables.bs5.min.js, select2.min.js, select2-active.min.js | customers-init.min.js |
| Create/edit form (customers-create.html) | select2.min.css, select2-theme.min.css, datepicker.min.css | select2.min.js, select2-active.min.js, datepicker.min.js, lslstrength.min.js | customers-create-init.min.js |
| Detail/view (customers-view.html) | select2.min.css, select2-theme.min.css | select2.min.js, select2-active.min.js | customers-view-init.min.js |
| Auth (auth-login-minimal.html) | (none) | (none) | (none — only common-init) |

Notes:
- `select2-active.min.js` is what turns `data-select2-selector="status|tag|country|..."`
  selects into colored pills/tags — required wherever those selects appear.
- Every template page has its own `<name>-init.min.js` in `assets/js/` (customers-init,
  leads-init, projects-init, invoice-create-init, apps-*-init, widgets-*-init, settings-init,
  reports-*-init, analytics-init...). These are tiny: e.g. customers-init.min.js just does
  `$("#customerList").DataTable({pageLength:10,lengthMenu:[10,20,50,100,200,500]})` plus
  check-all-checkbox wiring. For generated screens, write equivalent small init snippets.
- HTMX caveat: all init scripts run on `$(document).ready` — content swapped in via HTMX will
  NOT be initialized (DataTables, select2, tooltips). Re-run the relevant init on
  `htmx:afterSwap` or scope initialization to the swapped node.

## Shared vs page-specific init

- Shared (every page): `assets/js/common-init.min.js` — sidebar mini/expand (`minimenu` class
  on `<html>`), mega-menu open/close, page-header-right mobile offcanvas toggles, tooltip init,
  responsive logo swap. Plus `assets/vendors/js/vendors.min.js` (which bundles
  `nxlNavigation.min.js`: sidebar accordion behavior, `#mobile-collapse` mobile drawer,
  PerfectScrollbar on `.navbar-content`).
- Shared (every page): `assets/js/record-picker.js` — the record picker's modal (references/record-picker.md);
  no init to re-run after a swap, it delegates from the document.
- Shared (every page): `assets/js/theme-customizer-init.min.js` — reads/writes localStorage keys
  `app-skin` / `app-skin-dark` / `font-family` / `app-navigation` / `app-header` and applies them
  as classes on `<html>` (e.g. `app-skin-dark`, `app-font-family-inter`, `minimenu`).
- Page-specific: everything else in `assets/js/` (one init per page, named `<page>-init.min.js`).

## What lives in assets/vendors

`assets/vendors/css/` (each with .map): animate, bsicon, dataTables.bs5, datepicker,
daterangepicker, emojionearea, feather, flagicon, fontawesome, jquery-jvectormap, jquery.steps,
jquery.time-to, quill, select2 + select2-theme, sweetalert2, tagify + tagify-data, tui-calendar
family (tui-calendar, tui-date-picker, tui-theme, tui-time-picker), and the aggregate
`vendors.min.css` (bundles the always-needed subset: feather icons, bsicon, fontawesome,
animate, pace, perfect-scrollbar styling, etc.).

`assets/vendors/js/` (each with .map): apexcharts, bootstrap, chance, circle-progress, cleave,
dataTables + dataTables.bs5, datepicker, daterangepicker, emojionearea, full-screen-helper,
jquery, jquery-ui, jquery-jvectormap + jvectormap-world, jquery.calendar, jquery.print,
jquery.steps, jquery.time-to, jquery.validate, lslstrength, moment, nxlNavigation, pace,
perfect-scrollbar, quill, select2 + select2-active, sweetalert2, tagify + tagify-data,
time-tracker, tui-* calendar family, and the aggregate `vendors.min.js`.

Only reference the individual files listed in the per-page table; everything "always needed" is
already inside `vendors.min.css` / `vendors.min.js`.

`assets/vendors/fonts/` and `assets/vendors/img/` back the vendor CSS (icon fonts, flags).
Other asset dirs: `assets/images/` (logo-full.png, logo-abbr.png, favicon.png, avatar/*.png,
brand/*.png), `assets/scss/` (source, not shipped).

## Theme customizer partial

`partials/customizer.html` (164 lines) contains the reusable `div.theme-customizer` markup
(floating gear handle + settings sidebar: navigation/header light-dark, skins light/dark,
typography choices). In the template pages this block is pasted inline before the footer
scripts (index.html has a longer inline version; auth pages a shorter skins+typography one).

Is it required? **No.** The panel is optional chrome. But keep including
`theme-customizer-init.min.js` regardless — it is the script that applies saved skin/font
classes to `<html>` on page load and powers the header moon/sun dark-mode toggle. If you drop
the panel, the only lost feature is the visual settings sidebar.
