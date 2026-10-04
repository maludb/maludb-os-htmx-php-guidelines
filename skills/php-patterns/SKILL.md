---
name: php-patterns
description: The mandatory PHP code structure for applications built with this plugin — a feature-oriented Page Controller pattern with procedural PDO query functions, full-page + partial templates, and five standard HTMX response patterns. Use before writing or editing ANY PHP file in an application built with this plugin.
---

# PHP Design Pattern

Every application uses the **feature-oriented Page Controller pattern** defined in [references/pattern-guide.md](references/pattern-guide.md) — read it before writing PHP. This SKILL.md is the enforcement summary plus the plugin-integration rules.

## The pattern in one view

| Responsibility | Implementation |
|---|---|
| Controller | Small procedural PHP endpoint under `/var/www/html/{feature}/` (the Apache DocumentRoot on Ubuntu 24.04) |
| Data access | Procedural functions taking `PDO` as first param, in `app/features/{feature}/queries.php` |
| Full-page view | `app/views/{feature}/page.php` (wrapped by `app/views/layout.php`) |
| Fragment view | `app/views/{feature}/partials/*.php` |
| Infrastructure | `app/bootstrap.php`, `app/db.php`, `app/http.php` |

Vertical slices: one feature = one `/var/www/html/{feature}/` endpoint set + one `/var/www/app/features/{feature}/queries.php` + one `/var/www/app/views/{feature}/` view set. **The web root is `html/` under the application root** — `/srv/apps/<key>/html` for an application of the Business OS (the kernel's installer puts the repository at `/srv/apps/<key>` and renders the vhost), `/var/www/html` for a standalone product on a bare Ubuntu 24.04 host — build the application there, not in a `public/` directory; `app/` and `config/` sit beside `html/`, outside the DocumentRoot, and must never be placed inside it. Configuration is `config/.env` (`env()`), the file the kernel's installer writes; `config/application.php` holds defaults and maps env keys onto them. **No classes for controllers, repositories, services, DTOs, or entities** — extract shared procedural helpers only when duplication becomes substantial.

## Controller sequence (every endpoint)

```
bootstrap → authenticate/authorize → read input → validate
        → query functions → log activity → render page or partial
```

- State-changing endpoints start with `require_post(); verify_csrf();`.
- Controllers pass explicit values to query functions; query functions never see `$_GET`/`$_POST`, HTMX, HTTP codes, or templates.
- Views receive data, never fetch it. Dependency direction is always endpoint → queries → PDO → PostgreSQL, and endpoint → template.
- **Log activity** (the plugin's activity-log/MaluDB requirement) in the controller after a successful action — inside the same transaction when the action writes (see the transaction example in the guide).

## The five HTMX patterns (pick deliberately)

| Pattern | Use for |
|---|---|
| A — dedicated fragment endpoint | Dropdown options, search results, table bodies, dashboard widgets |
| B — dual full-page/partial endpoint (`is_htmx_request()` + `Vary: HX-Request`) | Index/list screens, pagination, filters, sortable tables — the default for navigable screens |
| C — replace nearest component (`hx-target="closest tr"`) | Row delete, status toggle, inline edit, expanding details |
| D — server-triggered refresh (`HX-Trigger` event + listening region) | Cross-component updates after saves — the default over manual targeting |
| E — out-of-band swaps (`hx-swap-oob`) | Sparingly: counts, toasts, nav badges alongside a primary update |

Responses return the smallest *meaningful* component (a whole badge, row, or form container — never a bare text fragment). Keep conventional `href`/`method`/`action` attributes alongside `hx-*` for progressive enhancement.

## Helper contracts (fixed names — use, don't reinvent)

`db(): PDO` (static singleton, env-config DSN, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, real prepares) · `view(string $template, array $data): string` (path-confined to `app/views`, template names never from request input) · `e(mixed): string` (escape every dynamic value) · `is_htmx_request(): bool` · `require_post(): void` · `request_integer(string): ?int` · `csrf_token()` / `verify_csrf()`.

Writes use SQL `RETURNING`; multi-operation actions use explicit `beginTransaction`/`commit`/`rollBack`; a lone INSERT/UPDATE/DELETE does not.

## Security floor (from the guide — non-negotiable)

Escape everything dynamic with `e()`; prepared statements for values; **allowlists for SQL identifiers** (ORDER BY columns etc. — placeholders can't parameterize identifiers); POST + CSRF for all state changes; authorization checked in every endpoint (`HX-Request` is never an authorization signal); no database exception details to users; validate/constrain ids, sort fields, limits, search strings.

## Plugin integration (where this pattern meets the other skills)

1. **Markup comes from the design system.** The guide's HTML examples are generic Bootstrap; real screens use the design-system skill's canonical markup (Traffic Reports table, page-header buttons, nxl-* shell). The pattern governs *files and code flow*; design-system governs *what the templates emit*.
2. **The CRUD contract governs primary add/edit flows:** the Add button and per-row Edit button load the full-container form partial into `#page-content` (Pattern B endpoints with `hx-push-url` set to an explicit canonical URL — never `hx-push-url="true"`, which pushes the partial's fetch path and breaks relative URLs). The guide's in-page editor aside and form-replaces-itself flow (Patterns A + D) is the standard for *secondary* inline editing where a full navigation would be heavy — validation errors still re-render the form partial in place in both flows.
3. **`hx-confirm` guards destructive actions** (delete, irreversible state changes), exactly as the pattern guide shows. The no-modal rule applies to forms and display pages, not confirmations.
4. **CSRF over HTMX:** `verify_csrf()` accepts the hidden field *or* the `X-CSRF-Token` header stamped by the shell's `htmx:configRequest` listener (see php-session-auth). Both are the same session token.
5. **IDs and targeting coexist:** every meaningful element still carries its unique scheme id (`customer-row-{id}`, per design-decisions.md) — that's the change-request vocabulary and the hook for cross-component targets (Patterns D/E). For element-local operations, still prefer `closest` targeting so components stay uncoupled from their own ids.
6. **Activity logging is part of the controller sequence** — never optional, per the new-app skill.
