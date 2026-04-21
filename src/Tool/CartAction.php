<?php declare(strict_types=1);

namespace Swag\McpMerchantAssistant\Tool;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @codeCoverageIgnore
 */
enum CartAction: string
{
    case Create = 'create';
    case Add = 'add';
    case Remove = 'remove';
    case Update = 'update';
    case Get = 'get';
}
