# SwagMcpMerchantAssistant — Agent Guide

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

The bootstrap class (`SwagMcpMerchantAssistant.php`) guards `build()` with `Feature::has('MCP_SERVER')` so no services are loaded when the feature flag is off.

## Coding patterns

- Extend `McpToolResponse` (core) — provides `$this->success()`, `$this->error()`, ACL helpers, and the 100 KB response guard
- Inject `McpContextProvider` to get the authenticated `Context`
- Write tools: accept `bool $dryRun = true` and use `executeWithDryRun()` for automatic transaction rollback and exception handling
- Prefer human-readable inputs (tax rate as float, currency as ISO code, category as name) — resolve to UUIDs internally
- Validate entity existence with `$registry->has($entity)` before ACL checks to produce clear error messages
- Make rarely-supplied parameters optional in the PHP signature (`string $salesChannelId = ''`) and validate inside the method. Required-but-missing parameters cause some agents (notably GPT-4o) to refuse the tool call entirely instead of asking the user for the value. `StorefrontSearchTool` follows this pattern: `salesChannelId` defaults to `''` and the tool returns an actionable error if empty
- Declare tool prerequisites with `#[McpToolDependsOn('merchant-tool-name')]` when one tool only works after another has been called (e.g., `merchant-cart-checkout` depends on `merchant-cart-manage`)
- Declare required ACL privileges with `#[McpToolRequires('entity:operation')]` (repeatable) — this is informational only and helps operators configure roles correctly. Still call `$this->requirePrivilege($context, ...)` inside `__invoke()` for actual runtime enforcement. See `src/Core/Framework/Mcp/Tool/AGENTS.md` for both static and dynamic forms

## Tests

Unit tests live in `tests/unit/Tool/`. Mock DAL services and `McpContextProvider`. Do not use integration test infrastructure — unit tests only.

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
