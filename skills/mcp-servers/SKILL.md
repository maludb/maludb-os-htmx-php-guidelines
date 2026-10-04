---
name: mcp-servers
description: Design and build the two MCP servers every application ships — the record-memory server over PostgreSQL 17 and the activity-memory server over MaluDB — in Python with FastMCP, exposed to the app's AMA agent and to clients' own AI tools. Use during Phase 1 (tool-surface design) and when implementing, deploying, or extending an application's MCP servers.
---

# Memory MCP Servers

Every application ships two MCP servers. They are designed with the schema in Phase 1 and are the only way agents read the memories — the AMA agent, the client's own AI tools, and future automations all go through them.

| Server | Name | Memory | Backing store |
|---|---|---|---|
| Record memory | `{app}_records_mcp` | Entities, facts, current truth | PostgreSQL 17, read-only role |
| Activity memory | `{app}_activity_mcp` | What happened, who, when | MaluDB, read-only role |

(A third, **localhost-only** server — `{app}_actions_mcp` for chat-driven navigation and actions — is specified in the **chat-actions** skill. This skill covers the two client-facing read servers; the read servers never write, and the actions server is never exposed.)

## Designing the tool surface (Phase 1)

The tool surface is derived from the Phase 0 question list — features are pre-packaged answers to questions, and MCP tools are the same thing for agents.

1. **Purposeful tools first.** Each recurring record question becomes a named tool (`find_customer`, `revenue_by_period`, `open_invoices_for`); each recurring activity question becomes one on the activity server (`actor_timeline`, `record_history`, `who_touched`). Tool names are snake_case, action-oriented, and prefixed consistently. Descriptions state *when* to call the tool, not just what it does.
2. **One guarded long-tail tool per server.** `records_search` / `activity_search` accepts a single SELECT statement, validated in code (reject anything else), run under the read-only role with `statement_timeout` and a row cap. The tool description embeds a schema summary so agents write correct queries.
3. **Read-only, always.** Every tool carries `readOnlyHint: true`; no tool on either server ever writes. Writes happen only through the PHP application (which is what produces the activity log).
4. **Filter and paginate.** Tools return focused results with limit/offset or cursor parameters — never unbounded dumps.

## Implementation (Python + FastMCP)

Python is the default runtime (Node.js + the MCP TypeScript SDK is the documented alternative — pick one per project, never both).

```python
from mcp.server.fastmcp import FastMCP
from pydantic import BaseModel, Field, ConfigDict

mcp = FastMCP("acme_records_mcp")

class FindCustomerInput(BaseModel):
    model_config = ConfigDict(str_strip_whitespace=True, extra="forbid")
    query: str = Field(..., description="Name, email, or customer number fragment", min_length=2)
    limit: int = Field(default=10, ge=1, le=50)

@mcp.tool(
    name="find_customer",
    annotations={"title": "Find Customer", "readOnlyHint": True, "openWorldHint": False},
)
async def find_customer(params: FindCustomerInput) -> str:
    """Look up customers by name, email, or customer number. Call this before any
    tool that needs a customer_id and you only have a human description of the customer."""
    rows = await db.fetch(FIND_CUSTOMER_SQL, params.query, params.limit)  # read-only pool
    return to_json(rows)
```

Conventions:

- Pydantic v2 input models (`model_config = ConfigDict(...)`, `extra="forbid"`), constraints on every field.
- Async database access via a connection pool built on the **read-only role's** credentials — the server process never holds write credentials.
- Errors return actionable messages ("no customer matched 'smith'; try a shorter fragment or an email") rather than raw exceptions.
- Transport: **streamable HTTP, stateless JSON** — these are remote, multi-client servers. stdio only for local development.
- Log every tool call (tool, caller identity, arguments, duration) to the activity log — MCP usage is itself activity memory.

## Deployment and exposure (client-facing by design)

- Each server runs as a **systemd service** on the Ubuntu 24.04 host, bound to localhost.
- **Apache reverse-proxies** them under TLS at stable paths, e.g. `https://{client}.example.com/mcp/records` and `/mcp/activity`.
- **Auth:** per-client bearer tokens issued by the application (revocable, stored hashed, checked by the MCP service on every request). SaaS Plus+ gives each client a dedicated database, so the endpoint's credentials scope to exactly one client's memory — tenant isolation at the database level, not the query level.
- Clients connect their own tools (Claude Desktop, Claude Code, their agents) to these endpoints. Publish the two URLs plus token instructions in the app's settings screen.

## Beside the Business OS kernel (every application from us)

The two read servers are what the kernel's agents reach (`maludb-os-integration`, `mcp-and-api.md`). Beyond a person's
own token, accept: the **kernel's token** (`kernel.{exp}.{app_key}.{nonce}.{hmac}` over `ACTION_TOKEN_KEY`), admitted to
one tool, `app_roles` (the roles and the rights each gives, `os.app-roles/1`, from the schema's catalogue); an **agent's
run token** (`{member}.{exp}.{run}.{hmac}` over `"run:…"`), admitted to exactly the tools the kernel's run-facts call
names for this endpoint — a token the kernel does not vouch for lists and calls nothing; a **person's action token**
(`{member}.{exp}.{hmac}`). Ship `find_<entity>` resolvers answering `{"rows": [{"<entity>_id", "label"}]}` for every
entity an action names — the kernel's actions server resolves labels through them. On `mcp` 1.x copy the kernel's
`db.py` and `server_common.py`; on 2.x, Cidery's `services/common/os_kernel.py` (`maludb-os-cidery`) wraps
`MCPServer.list_tools`/`call_tool`. Ports come from `config/.env` (`MCP_RECORDS_PORT`, `MCP_ACTIVITY_PORT`); the installer
assigns them.

## Testing

- Exercise every tool with **MCP Inspector** (`npx @modelcontextprotocol/inspector`) before wiring the AMA agent to it.
- Turn the Phase 0 question list into the evaluation set: each record/activity question should be answerable by an agent using only the server's tools. A question that can't be answered means a missing tool or a bad description — fix the surface, not the question.

## When building or heavily extending a server

If a full MCP build is underway and the `mcp-builder` skill is available in the session, load it — it carries the current SDK documentation, best practices, and evaluation methodology. This skill defines *what* these two servers must be; mcp-builder is the deeper *how*.
