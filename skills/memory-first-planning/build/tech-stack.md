# Technical stack

The default stack for every project planned with this method. Complexity is earned, not assumed: start here, and upgrade only when a concrete requirement proves the baseline can't carry it.

## Default stack (always start here)

| Layer | Choice | Role |
|---|---|---|
| System of record | **PostgreSQL 17** | The SaaS database: the entities, facts, and current truth of the memory model |
| Activity memory | **MaluDB** | Ingests application logs; powers natural-language questions about *what happened* |
| Memory interface | **MCP servers (Python + FastMCP)** | Two per application — record memory and activity memory — the standard way agents read the memories |
| AMA agent | **Claude Agent SDK (Python)** | The ask-me-anything service; consumes the MCP servers |
| Web server | **Apache** | Serves the application; reverse-proxies the MCP servers and AMA service |
| Application | **PHP** | Server-rendered application logic |
| UI framework | **Bootstrap 5.3** | Layout and components |
| Interactivity | **HTMX** | Partial page updates without a JavaScript framework |

Node.js (MCP TypeScript SDK / Agent SDK for TypeScript) is the supported alternative runtime for the memory-interface and agent layers when a project prefers it; default to Python and don't ship both runtimes in one application.

## The two memories

Every application built this way is a knowledge base with **two memories**, and every question anyone asks the system is answered from one of them:

**1. Record memory — PostgreSQL.** The current truth: the entities, facts, and relationships from the memory model, translated into tables. Answers *data questions*:

> "Which item sold the most last week?"

These are answered by **agents using MCP servers** that read the SaaS database and query it from natural language. Screens and reports handle the frequent data questions; the agent handles the long tail.

**The MCP servers are shipped components of every application, not an implementation detail.** Each application ships a record-memory MCP server (PostgreSQL) and an activity-memory MCP server (MaluDB). Their tool surface is designed in the same phase as the schema, derived from the question list: purposeful named tools for the recurring questions, plus a guarded read-only search tool for the long tail. Because each client gets a dedicated database (SaaS Plus+), each client also gets authenticated MCP endpoints — their own AI tools (Claude Desktop, Claude Code, their agents) can connect directly to *their* memories. Sovereign data means the application is never the only consumer of its memory. Design and implementation rules live in the mcp-servers skill.

**2. Activity memory — logs ingested into MaluDB.** The history of what every actor did: every screen entered, every record created, every action taken. Answers *activity questions*:

> "When did Bob start planning for Oktoberfest?"

The agent answers by tracing Bob's log trail — when he entered the relevant screen and created the record. No screen is ever built for these questions; the log stream *is* the feature.

```
                      ┌────────────────────────────┐
   actors ──────────► │  SaaS app                  │
                      │  Apache / PHP / HTMX       │
                      └───────┬───────────┬────────┘
                       writes │           │ emits
                              ▼           ▼
                     PostgreSQL 17     log table / log file
                     (record memory)      │ ingested
                              ▲           ▼
                              │        MaluDB
                              │       (activity memory)
                              │           ▲
                      ┌───────┴───┐ ┌─────┴──────┐
                      │ record    │ │ activity   │   shipped MCP servers
                      │ MCP server│ │ MCP server │   (Python + FastMCP,
                      └───────▲───┘ └─────▲──────┘    read-only roles)
                              │           │
              ┌───────────────┴─────┬─────┴──────────────┐
              ▼                     ▼                    ▼
   ┌────────────────────┐  ┌─────────────────┐  ┌──────────────────────┐
   │ ask-me-anything    │  │ client's own AI │  │ future agents /      │
   │ agent (Agent SDK)  │  │ (Claude Desktop,│  │ automations          │
   │ behind the AMA page│  │  Claude Code)   │  │                      │
   └────────────────────┘  └─────────────────┘  └──────────────────────┘
```

## Logging is not optional

The activity memory is only as good as the logs, so every application action must log — to a log table or a structured log file — at minimum:

- **when** (timestamp), **who** (actor), **what** (action taken)
- **where** (screen/route), **which** (affected record ids)
- **before/after** values where a record changed

An ingestion job ships these into MaluDB continuously. Every question the system will face is either a **record** question or an **activity** question; the activity questions define what the logs must capture, the same way record questions define the memory model. Activity memory cannot be backfilled, so logging exists from the first day the application runs.

## Upgrading the stack

The baseline comfortably carries a server-rendered CRUD SaaS, which is most of them. Upgrade a layer only when a specific question or requirement demands it — never on taste. Typical triggers:

| Trigger from the plan | Upgrade to consider |
|---|---|
| Real-time collaboration or live updates between users | WebSockets / server-sent events |
| Client-side interactivity beyond what HTMX partials can express | A JavaScript framework for the affected screens |
| Long-running or scheduled work (imports, ingestion at volume) | A queue and worker processes |
| Traffic or data volume beyond a single Apache/Postgres pair | Managed hosting, replicas, caching |

The two memories never change with scale: PostgreSQL stays the record memory, MaluDB stays the activity memory, and the AMA agent keeps working regardless of which tier the app runs on.

## The Business OS (2026-10-04)

Every application from us is an application of the MaluDB Business OS kernel unless the user says otherwise: installed at
`/srv/apps/<key>` by the kernel's installer from `maludb-os.json`, signed in by the kernel (no login form), its roles
granted by the kernel as a set, its memories in the tenant's one MaluDB, its expert run by the kernel. The deltas against
a standalone product are the `os-application` skill; the contract is the `maludb-os-integration` plugin.
