---
name: os-application
description: Build an application with this plugin so that it fits the MaluDB Business OS kernel from day one — the kernel signs people in (no login form), the kernel's installer installs it from maludb-os.json, its MCP servers and handlers accept the tenant's tokens, its command bar runs its expert in the kernel, and nothing in it holds a model key. Use at the start of every new application (Phase 0) unless the user says the product is standalone-only, and whenever a decision touches auth, configuration, deployment paths, the action manifest, MCP auth or the assistant.
---

# An application of the Business OS, from the first commit

The MaluDB Business OS is a **kernel**: it manages the AI agents, the super-admins, the company's directory and
single sign-on, and runs no business application. Applications from us (HR, Projects, Help Desk, Cidery, …) are
separate Apache/PHP/HTMX applications built with THIS plugin, installed beside the kernel by its installer, signed in
by the kernel, and reached by its agents through MCP. The contract is the `maludb-os-integration` plugin
(`github.com/maludb/maludb-os-integration`: skills `os-integration`, `os-adopt`, `os-install`). This skill is the short
list of what to do differently *while building* so that nothing needs adopting afterwards. Where the two plugins
disagree, `maludb-os-integration` wins — it is the kernel's word.

## Decide first (Phase 0)
- **OS application or standalone product?** The default is an OS application. A product that must also run with no
  kernel keeps its own login behind ONE flag, `OS_ENABLED` (the `os-adopt` shape); an OS-only application has no login
  at all.
- **Catalog key** (`cidery`): the DNS label `<key>.<domain>`, the install path `/srv/apps/<key>`, the database
  `<tenant>_<key>`, the roles `<key>_rw`, `<key>_records_ro`, `<key>_activity_ro`.
- **Scope kind**: `none` (one business), `location` (per site), `department`.
- **The roles and the rights each gives**, in the application's words — published to the kernel, granted there as a SET.
- **Which of its tables the estate already has.** Survey the sibling applications' schemas (`/srv/apps/*/db/*.sql`) before
  the memory model is final; every table is `read` (another application's data, through the kernel), `reuse` (the
  canonical definition verbatim) or `new` (`maludb-os-integration`, `shared-schema.md`).

## What changes in each phase

| Where | Standalone habit | OS application |
|---|---|---|
| Configuration | `config/application.php` + `config/local.php`; `services.env` for Python | **`config/.env`** read by PHP (`env()`) and Python alike — the kernel's installer writes it (`DB_*`, `MCP_*_PORT`, `APP_INTERNAL_PORT`, `ACTION_TOKEN_KEY`, `ACTIONS_RELAY_KEY`, `OS_*`, `MALUDB_*`). Keep `application.php` for defaults; map env keys onto it. |
| Root | `/var/www`, web root `/var/www/html` | `/srv/apps/<key>`, web root `/srv/apps/<key>/html`; every unit and vhost in `deploy/` is a **template** with `{{APP_DIR}}`, `{{APP_FQDN}}`, `{{APP_INTERNAL_PORT}}`, `{{MCP_RECORDS_PORT}}`, `{{MCP_ACTIVITY_PORT}}` |
| Schema (Phase 1) | designed from the domain alone | **designed against the estate**: a table a sibling application already has is reused verbatim (same name, columns, types; extend by appending only); data another application owns is read through the kernel's K7, never copied; a new table is the last resort and becomes canonical. The definition still lives in THIS repository's `db/*.sql` — installations differ in which applications they hold, and the installer creates every table from here (`shared-schema.md`, with the catalogue of canonical tables) |
| Database | one database per client, an operator registry, `provision-client.sh` | one database per **tenant** (the installer creates it and the three roles, runs `db/*.sql` in order as postgres). If the schema needs more (files as the app role, an extension's memory schema, a seed row), ship an idempotent `deploy/os-provision.sh` and name it `database.provision` |
| Identity (Phase 2) | `php-session-auth`: password + TOTP + Google, invitations, reset, a users admin | **No login form.** `/sso` receives the kernel's hand-off (verify two signatures, TTL, audience, single-use nonce), mirrors the member (`members`, `department_members` with the kernel's ids — or, standalone-too, `users.os_member_id`), opens the app's own hardened session; `/sso/logout` ends the member's sessions; a directory sync every minute; people and roles are never created in the application. Copy the receivers from `php-sign-on-kit.md`. |
| Roles | `users.role`, one value | the kernel grants a **set**: `members.roles text[]` (or `users.os_roles`); gates ask for a right (`app_has_right('pay.write')`), the catalogue lives in the database and is published by `app_roles` |
| Activity log | `receipt_posted`, sources screen/command_bar/ama/mcp/system, in-database memory | `entity.verb` (`receipt.post`), sources incl. `agent`, `request_id` honoured from `X-Request-Id`, `agent_run_id`; shipped to the **tenant's one MaluDB** through its API with `"application": "<key>"` first in the payload, checkpointed in `activity_ingest_state` |
| Action manifest (Phase 1) | 6 columns for the app's own actions server | the kernel's **8 columns** (`Action · File · Params · Undo · Confirm · Agent approval · Log · Who`) and a `bin/build_action_registry.php` → `mcp/action_registry.json`; an endpoint with a path parameter is written `/vessels/{vessel}/status` (the kernel resolves and substitutes); `deploy/kernel-registry-<key>.json` names a `find_<entity>` resolver per entity |
| Handlers | HTMX only | HTMX **and JSON mode**: `emit_action_status(true, ['id' => …, 'location' => "/x/$id"])` on success, `emit_action_status(false, ['errors' => …])` on refusal; the tenant's `X-Action-Token` (+ `X-Action-Relay` for an agent) stands in for the session and CSRF; `source = 'agent'` under a run token. An existing HTMX-only application gets the shim instead (`os-adopt`, `htmx-php-builder-profile.md`) |
| MCP servers (Phase 4) | two read servers with named bearer tokens | the same two, plus: the kernel's token (`kernel.…`) admitted to `app_roles` alone; an agent's run token admitted to exactly the tools the kernel's **run-facts** call names for this endpoint (fail closed when the kernel is unreachable); a person's action token; `find_<entity>` resolvers answering `{"rows": [...]}`. Copy `db.py` + `server_common.py` from the kernel when on `mcp` 1.x; the 2.x adapter is Cidery's `services/common/os_kernel.py` |
| The assistant (Phase 4) | one Agent SDK service holding `ANTHROPIC_API_KEY`; a localhost actions server | **none of it beside the kernel.** The command bar posts the utterance to the kernel's chat endpoint as the acting person; the kernel runs the application's expert with the application's tools; the kernel's actions server executes the registry. A standalone-too product keeps its own service for `OS_ENABLED=0` only. The application never holds a model key. |
| Registration | — | `maludb-os.json` at the root from Phase 1 (schema `maludb-os.application/1`): catalog_key, vhost, database, env, services, endpoints, actions, skills, sso, directory, assistant, agents[] (the expert first, with its tool grants and skills), approvals[]; `os/expert.md`; `skills/<name>/SKILL.md` (a runbook says `kind: runbook`); `/api/v1/health` |
| Install | README steps by hand | `sudo php /var/www/bin/app_install.php plan|apply <repository>` — the kernel's installer does every step from the manifest; `plan` is read-only and the first proof of a Phase 2 shell |

## Proving it without a kernel
A development `ACTION_TOKEN_KEY`, `bin/dev_handoff.php` (a hand-off signed the kernel's way) and `bin/dev_directory.json`
(claims and a feed in the kernel's schema; `directory_sync.php --from-file`) prove sign-on, replay refusal, the audience
check, roles, sign-out and revocation on any server (`testing-without-a-kernel.md`). The installer replaces the key at
install.

## Non-negotiables
- Never a login form, a password or an account of the application's own in an OS application; never a second way in
  while `OS_ENABLED` is on in a standalone-too product.
- Never a model key in the application; never a call to a model from it.
- Never a connection to the kernel's database; MaluDB episodes, MCP, the hand-off token and the kernel's endpoints are the only crossings.
- Never a new shape for a table a sibling application already keeps, and never a migration that depends on a sibling being
  installed (no `\i` of its files, no reference into its database, no look at its install path).
- Never fail open on an agent's grants or an unknown member id.
- Ports, hostnames and keys come from `config/.env`; nothing that differs per tenant is a constant in code or in `deploy/`.
