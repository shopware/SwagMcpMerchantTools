# SwagMcpMerchantTools — Agent Guide

## Purpose

This plugin provides merchant-facing MCP workflow tools for Shopware. Its tools sit on top of the platform primitives in core and offer higher-level, human-readable operations that a merchant operator would perform: looking up orders, creating products, running revenue reports, managing carts, and searching the storefront.

## What belongs here

- Merchant operator workflows: order management, customer lookup, analytics, storefront, cart/checkout
- Higher-level abstractions over core entity tools (e.g., `merchant-product-create` resolves tax rate → tax ID and builds the nested price structure automatically)
- Tools that require Storefront context (SalesChannelContext, Store API layer)

## What does NOT belong here

- Platform primitives (entity CRUD, schema, aggregations, system config) — stay in core
- Developer tooling (PHPStan, cache, deployment) — see [shopwareLabs/ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools)
- Media upload and theme config — those stay in core (`shopware-media-upload`) and Storefront (`shopware-theme-config`) respectively

## Tool naming

All tools use the `merchant-` prefix:

```
merchant-order-summary
merchant-customer-lookup
merchant-product-create
merchant-revenue-report
merchant-bestseller-report
merchant-storefront-search
merchant-cart-manage
merchant-cart-checkout
merchant-checkout-methods
```

Never use `shopware-` — that prefix is reserved for core.

## Registration

Tag services with `shopware.mcp.tool` in `src/Resources/config/services.xml`. `McpToolCompilerPass` in core handles DI wiring and MCP server registration automatically — no `scan_dirs` entry needed.

The bootstrap class (`SwagMcpMerchantTools.php`) guards `build()` with `Feature::has('MCP_SERVER')` so no services are loaded when the feature flag is off.

## Coding patterns

- Extend `McpToolResponse` (core) — provides `$this->success()`, `$this->error()`, ACL helpers, and the 100 KB response guard
- Inject `McpContextProvider` to get the authenticated `Context`
- Write tools: accept `bool $dryRun = true` and use `executeWithDryRun()` for automatic transaction rollback and exception handling
- Prefer human-readable inputs (tax rate as float, currency as ISO code, category as name) — resolve to UUIDs internally
- Validate entity existence with `$registry->has($entity)` before ACL checks to produce clear error messages
- Make rarely-supplied parameters optional in the PHP signature (`string $salesChannelId = ''`) and validate inside the method. Required-but-missing parameters cause some agents (notably GPT-4o) to refuse the tool call entirely instead of asking the user for the value. `StorefrontSearchTool` follows this pattern: `salesChannelId` defaults to `''` and the tool returns an actionable error if empty
- Declare tool prerequisites with `#[McpToolDependsOn('merchant-tool-name')]` when one tool only works after another has been called (e.g., `merchant-cart-checkout` depends on `merchant-cart-manage`)
- Declare required ACL privileges with `#[McpToolRequires('entity:operation')]` (repeatable) — this is informational only and helps operators configure roles correctly. Still call `$this->requirePrivilege($context, ...)` inside `__invoke()` for actual runtime enforcement. See `src/Core/Framework/Mcp/Tool/AGENTS.md` for both static and dynamic forms

## Description quality

The `description` on `#[McpTool]` is what the LLM reads to pick a tool. It is a routing surface, not a docblock. The general rules live in [`src/Core/Framework/Mcp/Tool/AGENTS.md`](../../src/Core/Framework/Mcp/Tool/AGENTS.md) under "Description quality (LLM routing)". Read those first. Three things are specific to this plugin, because every merchant tool sits next to a core tool that does something similar:

- **Every tool states what it is NOT for and names the sibling that handles that case.** Without the contrast the model picks the merchant tool for adjacent work: `merchant-order-summary` was chosen for "List the last 10 orders" until it said "Single order only: shopware-entity-search handles multi-order queries". `ToolDescriptionTest` pins the required sibling per tool and doubles as documentation of the intended routing graph.
- **Keep sibling references conditional, never imperative.** "use `merchant-checkout-methods` to list available methods" makes the model call that sibling as its first action, even when the user's prompt already supplied everything. Use the shape from core `EntitySearchTool`: "if you don't already know the method IDs, `merchant-checkout-methods` will tell you". Real prerequisites go in `#[McpToolDependsOn]`, which the allowlist UI and `debug:mcp` already understand.
- **A cross-toolset boundary must not reuse the sibling's trigger vocabulary.** All merchant tools are deferred, so the model reaches them through `shopware-tool-search`, and `ToolSearch::scoreTool()` ranks by plain `str_contains` on the lowercased description (+0.6 per token hit, +0.8 per unique match). A boundary phrased "for listing multiple orders use shopware-entity-search" substring-matches `list` and pulls the tool back into the results for the very prompt it must lose. "multi-order queries" carries the same meaning and scores nothing for `orders`. Same-toolset siblings are exempt: they are advertised together after one `shopware-toolset-enable`, so retrieval cannot separate them and only the description can.

Behaviour is graded end to end by [shopware-mcp-evals](https://github.com/shopware/shopware-mcp-evals), which scores the first non-meta tool call against an expected tool. Run it after any description change. Remember `bin/console cache:clear` first: descriptions are baked into the compiled DI container, so both `debug:mcp` and the eval keep scoring the old text until the cache is rebuilt.

## Tests

Unit tests live in `tests/unit/Tool/`. Mock DAL services and `McpContextProvider`. Do not use integration test infrastructure — unit tests only.

`ToolDescriptionTest` and `ToolGroupingTest` are attribute-level guards rather than behaviour tests: they assert the routing rules above and the group/deferral invariants, so a description or grouping regression fails locally instead of only showing up as a lower eval pass rate.

## Security layers

Requests to merchant tools pass through the same three layers as all MCP tools:

1. **Authentication** — credentials from `.mcp.json` (`sw-access-key` + `sw-secret-access-key`)
2. **Per-integration allowlist** — tool must be enabled in Settings → Integrations → Edit MCP Tools; error: `Tool "X" is not in the allowlist for this integration. Enable it under Settings → Integrations → Edit MCP Tools.`
3. **ACL** — integration role must have the required privileges; error: `Missing privilege: sales_channel:read`

See `src/Core/Framework/Mcp/docs/security.md` for the full layer reference and troubleshooting table.

## Core conventions

General tool patterns, response format, ACL, dryRun, `McpEntityIncludes`, and registration mechanics are documented in core:

- [`src/Core/Framework/Mcp/AGENTS.md`](../../src/Core/Framework/Mcp/AGENTS.md) — architecture overview
- [`src/Core/Framework/Mcp/Tool/AGENTS.md`](../../src/Core/Framework/Mcp/Tool/AGENTS.md) — tool authoring rules
- [`src/Core/Framework/Mcp/docs/extensibility.md`](../../src/Core/Framework/Mcp/docs/extensibility.md) — plugin registration walkthrough
