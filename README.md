# htmx-php-builder

A Claude plugin for building memory-first HTMX + PHP + Bootstrap 5.3 applications with a consistent look and feel, a fixed build order, and an Ask Me Anything feature in every app.

## Installation

In Claude Code:

```
/plugin marketplace add maludb/maludb-os-htmx-php-guidelines
/plugin install htmx-php-builder@maludb-os-htmx-php-guidelines
```

The repository is its own marketplace (see `.claude-plugin/marketplace.json`), with the plugin at the repository root.
It moved on 2026-10-04 from `maludb-ed/htmx-php-builder` (GitHub redirects the old name; a marketplace added under the old
name keeps working, and installs it as `htmx-php-builder@maludb-ed`). The plugin's name is unchanged: `htmx-php-builder`.

## Applications of the MaluDB Business OS

Every application built with this plugin for the MaluDB Business OS suite (HR, Projects, Help Desk, Cidery, …) is installed
beside the kernel by the kernel's installer, signed in by the kernel, and reached by its agents through MCP. The
`os-application` skill is the list of what to do differently from the first commit so nothing needs adopting afterwards;
the contract itself is the [`maludb-os-integration`](https://github.com/maludb/maludb-os-integration) plugin, and the kernel is
[`maludb-os-core`](https://github.com/maludb/maludb-os-core). An application built before this plugin learned the kernel is
adopted with `maludb-os-integration`'s `os-adopt` skill (the Cidery adoption is the worked example).

## Stack

Ubuntu 24.04 · PostgreSQL 17 + MaluDB (activity memory) · three MCP servers per app (two client-facing read servers + one localhost actions server; Python + FastMCP, Node.js as alternative) · one unified Claude Agent SDK assistant (Python) for AMA answers, chat actions, and navigation · Apache · vanilla PHP · Bootstrap 5.3 (nxl admin template) · HTMX. Mobile-first; no modal forms or display pages (`hx-confirm` guards destructive actions). Memories are served through MCP — the app's assistant and the client's own AI tools both read them via authenticated MCP endpoints (SaaS Plus+) — and every screen carries a voice-first command bar (dictation-friendly, e.g. Wispr Flow) for chat-driven data entry and navigation.

## Skills

| Skill | Purpose |
|---|---|
| `new-app` | The phased build workflow: memory-first planning → full database + memory design → auth + app shell → vertical feature slices → Ask Me Anything |
| `new-screen` | Generates each screen from the design system so consistency holds screen-to-screen |
| `mcp-servers` | The two MCP servers every app ships (record + activity memory): tool-surface design, FastMCP implementation, deployment, client-facing auth |
| `design-system` | The mandatory UI template distilled into layout skeletons, component patterns, and mobile rules |
| `php-patterns` | The mandatory PHP code structure (readable/maintainable for humans and agents) |
| `memory-first-planning` | Memory-first design, ask-me-anything, SaaS Plus+, the two-memory architecture |
| `php-session-auth` | Security rules for vanilla PHP session auth + CSRF, Google sign-in, TOTP 2FA |
| `malumail-send` | Transactional email via the MaluMail API — the default email channel for every app |
| `chat-actions` | The voice-first command bar on every screen: LLM router → actions MCP server → the app's own endpoints (voice-to-data, voice-to-navigation); beside the kernel, the kernel runs the assistant |
| `os-application` | How an application fits the MaluDB Business OS kernel from day one: config/.env, /srv/apps/<key>, the kernel's sign-on, roles as a set, the kernel's manifest shape and JSON-mode handlers, the MCP servers' kernel contract, the command bar through the kernel, maludb-os.json |

## Build order (enforced by new-app)

1. **Phase 0** — memory-first planning (memory model, question list, feature order)
2. **Phase 1** — database design for the ENTIRE application + activity-log/MaluDB design + the MCP tool surface + the action manifest (logging exists from day one)
3. **Phase 2** — authentication + application shell incl. the assistant command bar (after this phase the app looks exactly as intended, on desktop and at 375px mobile)
4. **Phase 3** — vertical feature slices, one at a time, each registered in the action manifest
5. **Phase 4** — the three MCP servers, the unified assistant (AMA + chat actions + navigation), and the client-facing read-MCP endpoints

## Requirements for the memory/agent layer

Python 3 with `mcp` (FastMCP) and read-only PostgreSQL/MaluDB roles for the MCP servers. A **standalone** product also needs `claude-agent-sdk` and an `ANTHROPIC_API_KEY` in its AMA service environment (the fallback PHP-only AMA variant needs `composer require "anthropic-ai/sdk"`); an application of the Business OS needs neither — the kernel runs its expert.
