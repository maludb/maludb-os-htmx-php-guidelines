---
name: memory-first-planning
description: Background context for planning or brainstorming a new application or SaaS product — memory-first design, the ask-me-anything view of applications, SaaS Plus+ data sovereignty, and the default technical stack (PostgreSQL 17 + MaluDB, Apache/PHP/Bootstrap 5.3/HTMX). Provides answers and direction, not a workflow to run.
---

# Memory-first planning context

The files in this skill give you context about the kind of application being planned. They are **answers to questions you may have while planning — not a process to follow**. Do not treat them as a workflow, a script, or a roadmap; do not walk the user through phases or checklists derived from them. Plan with the user however the conversation naturally goes, and consult these files when you need to know the direction of the project.

## The three ideas

1. **Memory-first** — an application is designed by deciding *what it remembers* before deciding *what it does*. The memory model comes first; features are views over memory. Full explanation: [concepts/memory-first.md](concepts/memory-first.md).
2. **Ask-me-anything** — every SaaS application is secretly a knowledge base. A feature is a pre-packaged answer to a question someone asks the system, and planning is the work of discovering those questions. Full explanation: [concepts/ask-me-anything.md](concepts/ask-me-anything.md).
3. **SaaS Plus+** (pronounced "SaaS Plus Plus") — a dedicated memory-first, ask-me-anything application in which each user or client gets a dedicated database and memory system. Built on sovereign data: clients can build and use AI systems without worrying that the SaaS provider will use their data to train additional models.

## The technical stack

The stack is already decided — don't reopen it unless the user does. PostgreSQL 17 is the system of record; MaluDB is the activity memory, fed by application logs; the application baseline is Apache + PHP + Bootstrap 5.3 + HTMX, upgraded only when a concrete requirement demands it. Details, the two-memory architecture diagram, and the logging requirements: [build/tech-stack.md](build/tech-stack.md).

Two kinds of questions, two memories: *record* questions ("which item sold the most last week?") are answered by agents reading PostgreSQL through MCP servers; *activity* questions ("when did Bob start planning for Oktoberfest?") are answered from application logs ingested into MaluDB. Activity memory cannot be backfilled — logging exists from day one.

On a server that already carries sibling applications of the MaluDB Business OS, the memory model starts from theirs: the estate has one data model expressed in many databases. Something another application remembers (employment, the chart of accounts, tickets) is read through the kernel, not remembered again; a concept several applications keep rows of (attachments, notes, notifications, billing documents) takes the same table, verbatim, in this application's own database. The rule and the catalogue are `maludb-os-integration`'s `shared-schema.md`; `new-app` applies them in Phase 1.
