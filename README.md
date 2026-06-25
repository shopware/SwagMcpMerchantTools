# SwagMcpMerchantTools

Merchant workflow MCP tools for Shopware. Extends the built-in MCP server with higher-level tools designed for merchant operators — order management, customer lookup, analytics, storefront search, and cart/checkout flows.

> [!WARNING]
> **Experimental. May be removed at any time.** This plugin is a proof of concept and is **not** covered by any stability or backwards-compatibility guarantee. It can be discontinued, archived, or removed without notice, particularly in favor of **Shopware Copilot**, which is the official, supported direction for AI-assisted merchant workflows. Do not rely on it for production-critical processes.

## Requirements

- Shopware 6.7+
- `MCP_SERVER=1` in `.env`

## Tools

| Tool | Description |
|------|-------------|
| `merchant-order-summary` | Pre-formatted order overview with customer, line items, payment and delivery status |
| `merchant-customer-lookup` | Find a customer by email, number, or UUID with order history |
| `merchant-product-create` | Create a product with human-readable inputs (price, tax rate, currency, categories) |
| `merchant-revenue-report` | Revenue totals by date range, grouped by day / week / month |
| `merchant-bestseller-report` | Top-N products by quantity sold in a date range |
| `merchant-storefront-search` | Search with storefront context — resolved prices, customer-specific pricing, human-readable property filters |
| `merchant-cart-manage` | Create a cart and add / remove / update line items |
| `merchant-cart-checkout` | Place an order from an existing cart |
| `merchant-checkout-methods` | List available payment and shipping methods for a sales channel |

All write tools default to `dryRun=true`. Always preview changes before committing.

## What merchant operators can do

**Order management**
- "Show me the 10 most recent orders with their status and totals" — `shopware-entity-search` on `order`; `merchant-order-summary` for detail
- "Ship order #12345 and send the customer a notification" — `shopware-order-state` with `deliveryAction: "ship"`
- "Cancel all line items from order X and process the refund" — `shopware-order-state` with `orderAction: "cancel"`, `transactionAction: "refund"`

**Customer insights**
- "Track order status for customer email john@example.com" — `merchant-customer-lookup` with `email`
- "Show full purchase history for customer X" — `merchant-customer-lookup` with `customerId`

**Product management**
- "Create a new product with price 29.99, tax rate 19%, assigned to category 'Clothing'" — `merchant-product-create`

**Analytics**
- "What was the revenue for last month?" — `merchant-revenue-report` with `from`, `to`, optional `groupBy`
- "Which products are bestsellers this week?" — `merchant-bestseller-report` with `from`, `to`, optional `limit`

**Storefront / checkout**
- "Help a customer find red shoes in size 42" — `merchant-storefront-search` with `properties: '{"Color": "Red", "Size": "42"}'`
- "Create a cart, add 2x product SW-001, and place the order" — `merchant-cart-manage` then `merchant-cart-checkout`
- "What payment/shipping methods are available in sales channel X?" — `merchant-checkout-methods`

For platform/developer stories (entity search, schema, configuration, flow discovery) see `src/Core/Framework/Mcp/docs/agent-user-stories.md` in the [shopware/shopware](https://github.com/shopware/shopware) repository.

## Installation

**Via Composer** (recommended):

```bash
composer require swag/mcp-merchant-tools
bin/console plugin:refresh
bin/console plugin:install --activate SwagMcpMerchantTools
bin/console cache:clear
```

**Manual** (place in `custom/plugins/SwagMcpMerchantTools`):

```bash
bin/console plugin:refresh
bin/console plugin:install --activate SwagMcpMerchantTools
bin/console cache:clear
```

Verify the tools are registered:

```bash
bin/console debug:mcp
```

## Development

Run all checks from the plugin directory (`custom/plugins/SwagMcpMerchantTools`):

```bash
# All CI checks (PHPStan + CS + tests)
composer run ci

# Individual steps
composer run phpstan
composer run cs
composer run test
composer run cs-fix   # auto-fix code style
```

## Further reading

Core MCP architecture and tool authoring guide: see `src/Core/Framework/Mcp/AGENTS.md` in the [shopware/shopware](https://github.com/shopware/shopware) repository.

## Repository

[github.com/shopware/SwagMcpMerchantTools](https://github.com/shopware/SwagMcpMerchantTools)
