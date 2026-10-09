# The record picker — the PHP side, verbatim from the exemplar (ProcessCore's purchase order, 2026-10-09)

Copy into a new application in Phase 2 (the shell) and keep the names:

| File here | Where it goes | What it is |
|---|---|---|
| `picker.php` | `app/picker.php` (required by `app/bootstrap.php` after `ui.php`) | `picker_field()`, `picker_label()`, `picker_source()`, `picker_sources()` |
| `pickers.php` | `app/pickers.php` | the registry — replace the example sources with the application's; one entry per record kind |
| `pick-index.php` | `html/pick/index.php` | the endpoint `GET /pick/?source=&q=&page=&<declared params>` (the browser asks for `/pick/` with the slash, so Apache serves the directory index without a redirect) |
| `picker-rows.php` | `app/views/shared/picker-rows.php` | the rows fragment |
| `form_picker.php` | appended to `app/ui.php` when the application has `form_row_open()` | the row helper |
| `picker-browser.mjs` | a scratch directory beside the checkout | the Playwright pass (19 checks on the exemplar): open, focus, search, keyboard pick, the dependent refresh, Show more, 375px, dark mode |

The browser side is `examples/assets/js/record-picker.js`, the RECORD PICKER section of `examples/assets/css/app-overrides.css`
and the `#record-picker` modal in `examples/index.html` (and `references/layout-skeleton.html`). The contract and the rules:
`references/record-picker.md`.
