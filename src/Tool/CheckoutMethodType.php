<?php declare(strict_types=1);

namespace Swag\McpMerchantAssistant\Tool;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @codeCoverageIgnore
 */
enum CheckoutMethodType: string
{
    case Payment = 'payment';
    case Shipping = 'shipping';
    case All = 'all';
}
