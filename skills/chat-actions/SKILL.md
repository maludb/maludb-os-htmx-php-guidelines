---
name: chat-actions
description: The voice-first chat command bar that ships on every screen of every application — natural language in (typically dictated via a tool like Wispr Flow), application actions and navigation out, powered by an LLM router activating MCP tools. Use when building or extending the assistant command bar, the actions MCP server, the action manifest, chat-driven navigation, or voice-to-data capture.
---

# Chat Actions — voice-to-data and voice-to-navigation

Every application ships a persistent chat command bar. The user says (or types) what they want — "record three sets of bench press at 45 pounds", "navigate to the screen to create a new exercise" — and the assistant does it, using **both the utterance and the context of the screen they're on**. Input is chat-shaped but voice-first: users dictate via tools like Wispr Flow, so utterances are conversational, unpunctuated, and reference the visible screen ("add ten pounds to that").

## Architecture: LLM router → MCP tools → the app's own endpoints

```
Every screen: fixed bottom command bar (#assistant-bar)
   │ hx-post /assistant/message.php  (+ screen context)
   ▼
PHP handler (session auth, CSRF, activity log; mints a signed action token)
   ▼
Unified assistant service — Python, Claude Agent SDK (the SAME agent as AMA)
   │ LLM processes the utterance and routes it by activating an MCP tool
   ▼
{app}_actions_mcp  (Python + FastMCP, localhost-only)      + the two read servers
   ├─ navigate(screen, …)      → resolves via the screen registry, returns a directive
   ├─ {entity}_{action}(…)     → POSTs to the app's own PHP endpoints (action token)
   └─ undo_last(…)             → inverse action from the manifest + activity log
```

- **One unified assistant.** The AMA page and the command bar are two surfaces of the same Agent SDK service: read tools (record/activity MCP servers), action tools, and navigation. One conversation memory per user; questions, actions, and navigation mix freely in a single utterance.
- **The router is the LLM; the tools are MCP.** The agent never navigates or writes by itself — it activates a tool on the actions MCP server. Tool descriptions carry the routing knowledge (which screen/action serves which intent), so routing quality is a Phase 1 design artifact, not prompt luck.
- **Actions go through the app's own controllers.** The actions server's tools call the same `html/{feature}/save.php`-style endpoints a human uses — same validation, same authorization, same activity logging. The **read MCP servers stay strictly read-only**; nothing ever writes around the PHP layer.
- **The actions server is localhost-only** — unlike the client-facing read servers, it acts *as a user* and is reachable only by the assistant service. Per-request authority comes from a short-lived HMAC-signed action token minted by the PHP handler (user id + expiry); internal endpoints accept it in place of the session and enforce the same authorization.

## Beside the Business OS kernel (every application from us)

When the application is an application of the MaluDB Business OS (the os-application skill, the default), **the
assistant is the kernel's, not the application's**: the command bar posts the utterance to the kernel's chat endpoint
(`POST {OS_INTERNAL_URL}/api/v1/agents/chat.php?agent=expert`, the application token, `X-Acting-Member`), the kernel runs ONE
turn of the application's expert with the application's tools attached, every model call ledgered, approvals paused in
the kernel's queue, and answers the reply and the actions taken. The application holds no model key and ships no
assistant service and no actions server; the **kernel's actions server** turns the application's registry
(`mcp/action_registry.json`, built from the manifest) into tools that POST the application's own handlers on its
loopback port with the tenant's signed token — so handlers answer in **JSON mode** (`emit_action_status()`,
`{ok, location}` / 422 `{error}`) as well as HTMX. The UI, the manifest discipline and everything below about
utterances, context and `HX-Trigger` refreshes are unchanged; only who runs the model and the tools moves. A product
that must also run standalone keeps its own service behind `OS_ENABLED=0`.

## The action manifest (designed in Phase 1)

The sibling of the MCP tool surface: a designed registry, not an afterthought.

- **Screen registry:** every screen's stable id, canonical URL, title, and a one-line "when the user wants…" description. This is what `navigate` resolves against — the navigation router's routing table.
- **Action registry:** every performable action — name (`exercise_record_set`), target endpoint, parameter schema, natural-language trigger description, **undo definition** (the inverse: delete the created row / restore prior values), and whether it may execute immediately or must confirm.
- Both derive from the Phase 0 question/feature list. A screen or action missing from the manifest is unreachable by voice — treat that as unfinished design, exactly like a question with no MCP tool.

Worked tool definitions — the `navigate` description/registry pattern and the slot-filling action-tool schema (unit-bearing field names, bounds, server-side entity resolution) — are in [references/tool-authoring.md](references/tool-authoring.md); read it before writing any actions-server tool.

## The `navigate` tool contract (the router's core)

The single-tool-then-end-turn loop is the pattern: `tool_use(navigate)` → structured success → the model confirms in one sentence and ends the turn. Two model calls per command; run it at low effort.

- **Input:** `screen` (a registry id) + optional `params` (dict). Params map to the canonical URL's query string — `navigate("exercise-add", {"name": "Bench Press"})` → `/exercises/new?name=Bench+Press`, and the GET controller pre-fills from query params like any request. Compound utterances ("go to create exercise and call it bench press") stay ONE call.
- **The tool registers navigation; it does not perform it.** It validates the screen against the registry and returns structured JSON — `{"status": "success", "navigate": {"path": "...", "target": "#page-content"}}`. The assistant service extracts the `navigate` directive from the tool result and hands it to the PHP handler, which emits `HX-Location`; the browser executes the swap after the loop ends. A bare `"success"` string is a bug — the directive must live in the result.
- **Terminal semantics, stated in the tool description:** "After success, confirm to the user in one short sentence and end the turn — no further tool calls. At most one navigation per message; a later call replaces an earlier one." This is what produces the clean `end_turn` instead of a wasted verify cycle. The tool must not claim post-navigation knowledge (annotations: `idempotentHint: true`, `destructiveHint: false` — it changes client view state, never records).
- **Fail usefully:** an unknown screen returns the closest registry matches with their descriptions ("No screen 'exercise builder'. Closest: exercise-add — create a new exercise"), so the model self-corrects in one retry or asks one short question.

Action tools follow the same shape: structured result (`status`, what was done, undo handle), terminal semantics, and any screen-refresh consequence expressed as an `HX-Trigger` event name in the directive rather than knowledge of the screen's markup.

## Confirmation policy (locked): act + undo

- **Creates and updates execute immediately.** The reply states what was done in the interpreted terms ("Logged 3 × bench press @ 45 lbs") with an **Undo** control; "undo that" by voice resolves the last action from the conversation and activity log.
- **Destructive actions always confirm first** (`hx-confirm` on the rendered confirm control, or a spoken "yes" turn).
- **Ambiguity asks, briefly.** If the utterance can't be resolved against the manifest + screen context ("which exercise?"), the assistant asks one short question rather than guessing.

## How results reach the browser (pure HTMX — no new machinery)

| Assistant outcome | Response mechanism |
|---|---|
| Navigation | `HX-Location: {"path": "<canonical URL>", "target": "#page-content"}` — the shell swaps and pushes the explicit URL (never `hx-push-url="true"`) |
| Data action | Reply bubble + `HX-Trigger: {entity}Changed` — screens listening per Pattern D refresh their own regions; the assistant knows nothing about their markup |
| Answer (AMA-style) | Reply bubble in the command bar thread (full conversations live on the AMA page) |

## The command bar UI (locked design)

- **Fixed bottom bar** on every screen, rendered by the shell: `#assistant-bar` with input `#assistant-input`, send `#assistant-send-btn`. Slim, always visible; the shell footer (`#page-footer`) is pinned directly above it; full-width and thumb-reachable on mobile; never overlaps `#page-content` scroll (the shell measures the bar into `--assistant-bar-height`, which pads the body and sizes the footer pin, per the design system's `app-overrides.css`).
- **Send appears only while the bar is in use** — when anything in `#assistant-form` has focus or `#assistant-input` holds text; otherwise the bar is just the input (plus the transcript button). A permanently visible Send at the bottom of every screen was mistaken for the page's Save button, which sits at the top of the form. Pure CSS in the design system's `app-overrides.css` (no JS): the form has class `assistant-form`, `#assistant-send-btn` follows `#assistant-input` in the same `.input-group`, and the input has a non-empty placeholder (the rule keys on `:placeholder-shown`). Focus on the button itself keeps it visible, so a click is never lost; Enter submits as always.

```html
<form id="assistant-form" class="assistant-form" hx-post="/assistant/message" hx-target="#assistant-reply" hx-swap="innerHTML">
    <div class="input-group">
        <span class="input-group-text"><i class="feather-message-circle"></i></span>
        <input type="text" class="form-control" id="assistant-input" name="message" autocomplete="off"
               placeholder="Say what you want to do, or ask a question (Ctrl+K)" />
        <button type="submit" class="btn btn-primary" id="assistant-send-btn"><i class="feather-send"></i><span class="d-none d-sm-inline ms-2">Send</span></button>
        <button type="button" class="btn btn-light-brand" id="assistant-transcript-btn" data-bs-toggle="offcanvas" data-bs-target="#assistant-transcript" title="Conversation"><i class="feather-list"></i></button>
    </div>
</form>
```
- The latest exchange renders in `#assistant-reply` expanding upward from the bar; full history in the offcanvas transcript `#assistant-transcript` (full-width on mobile). No modals.
- `Ctrl/Cmd+K` (and `/` outside inputs) focuses the input — dictation tools type into the focused field, so focus-fast matters more than any mic button. Enter submits; the input clears and refocuses for the next utterance.
- **Screen context travels with every message:** each screen partial stamps `#page-content` with `data-screen`, `data-entity`, `data-record-id`; the bar submits them via `hx-vals` (js) alongside the text. This is how "that" resolves.

## Voice-first behaviors (bake into the assistant's system prompt)

- Parse dictation tolerantly: spelled-out numbers ("forty five"), units ("pounds", "lbs"), missing punctuation, filler ("uh, log, um, three sets").
- Always echo the interpretation in the reply — the confirmation *is* the readback.
- Support correction verbs as first-class: "undo that", "make it 50", "no, dumbbell press".
- Keep replies to one short sentence; this surface is a command bar, not a chat page.
- Latency matters: run this route at low effort, and prefer the project's fastest acceptable model for router-only turns (configurable in one place, like AMA's model).

## Streaming and live updates (locked): SSE from the assistant service — no SPA framework

- **The view layer stays HTMX/server-rendered.** No React or other SPA framework for the assistant surfaces — persistent connections are a transport concern, not a view-layer one, and a second rendering paradigm would fork the design system.
- **Long-lived connections belong to the Python assistant service, never PHP.** Apache/PHP pins a worker per held connection; the Agent SDK streams natively. Apache reverse-proxies `/assistant/stream` directly to the assistant service.
- **The browser subscribes with the htmx SSE extension** (`hx-ext="sse"`): the AMA page streams long answers into its thread; the command bar may stream progress ("still working…") into `#assistant-reply` during multi-second agent loops. Command-bar router turns (two model calls at low effort) normally need no stream — a plain POST with an htmx indicator suffices.
- If a future app needs a genuinely rich live surface (collaborative editing, realtime dashboards), that screen may take a JS island per the tech-stack upgrade rules — the application shell never becomes an SPA.

## Build order integration

- **Phase 1:** design the action manifest (screen + action registries, undo definitions, confirm flags) alongside the schema and MCP tool surface; checkpoint covers all three.
- **Phase 2:** the shell ships the command bar (ids above) wired to a stub handler, so the surface exists from the first screen.
- **Phase 3:** each vertical slice registers its screens and actions in the manifest as part of "done".
- **Phase 4:** build the actions MCP server + unified assistant service; verify with a spoken-style test script: every manifest action and every screen reachable by a one-sentence utterance.
