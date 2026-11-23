<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Mcp\Tool;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;

/**
 * @internal
 *
 * Plugin-local stand-in for McpToolResponse::requirePrivilegeResult(), which only exists in Shopware
 * after 6.7.16. Builds the same failure (message "Missing privilege: <privilege>", code
 * McpToolError::MISSING_PRIVILEGE) using API available since 6.7.16.
 */
trait McpToolResultHelpers
{
    /**
     * @return McpToolResult|null a failure for the first missing privilege, null if all are granted
     */
    protected function missingPrivilegeResult(Context $context, string ...$privileges): ?McpToolResult
    {
        foreach ($privileges as $privilege) {
            if (!$context->isAllowed($privilege)) {
                return McpToolResult::failure(\sprintf('Missing privilege: %s', $privilege), McpToolError::MISSING_PRIVILEGE);
            }
        }

        return null;
    }
}
