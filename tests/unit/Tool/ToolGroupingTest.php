<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Swag\McpMerchantTools\Tool\BestsellerReportTool;
use Swag\McpMerchantTools\Tool\CartCheckoutTool;
use Swag\McpMerchantTools\Tool\CartManageTool;
use Swag\McpMerchantTools\Tool\CheckoutMethodsTool;
use Swag\McpMerchantTools\Tool\CustomerLookupTool;
use Swag\McpMerchantTools\Tool\OrderSummaryTool;
use Swag\McpMerchantTools\Tool\ProductCreateTool;
use Swag\McpMerchantTools\Tool\RevenueReportTool;
use Swag\McpMerchantTools\Tool\StorefrontSearchTool;

/**
 * @internal
 */
final class ToolGroupingTest extends TestCase
{
    /**
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    public function testToolGroup(string $toolClass, string $expectedGroup): void
    {
        $reflection = new \ReflectionClass($toolClass);

        $groupAttributes = $reflection->getAttributes(McpToolGroup::class);
        static::assertCount(1, $groupAttributes, \sprintf('%s must have exactly one McpToolGroup attribute.', $toolClass));
        static::assertSame($expectedGroup, $groupAttributes[0]->newInstance()->group);
    }

    /**
     * Core derives the advertised (non-deferred) surface from the `discovery`
     * tool group, so every tool in this plugin is deferred and none may
     * resurrect the retired per-tool meta[deferred] flag.
     *
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    public function testToolIsDeferred(string $toolClass, string $expectedGroup): void
    {
        $reflection = new \ReflectionClass($toolClass);

        $toolAttributes = $reflection->getAttributes(McpTool::class);
        static::assertCount(1, $toolAttributes, \sprintf('%s must have exactly one McpTool attribute.', $toolClass));

        $meta = $toolAttributes[0]->newInstance()->meta;

        static::assertArrayNotHasKey(
            'deferred',
            $meta ?? [],
            \sprintf(
                '%s must not use the retired meta[deferred] flag; deferral follows the "%s" toolset group.',
                $toolClass,
                $expectedGroup
            )
        );
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function toolProvider(): array
    {
        return [
            'OrderSummaryTool' => [OrderSummaryTool::class, 'merchant-analytics'],
            'CustomerLookupTool' => [CustomerLookupTool::class, 'merchant-analytics'],
            'RevenueReportTool' => [RevenueReportTool::class, 'merchant-analytics'],
            'BestsellerReportTool' => [BestsellerReportTool::class, 'merchant-analytics'],
            'StorefrontSearchTool' => [StorefrontSearchTool::class, 'merchant-sales'],
            'CheckoutMethodsTool' => [CheckoutMethodsTool::class, 'merchant-sales'],
            'CartManageTool' => [CartManageTool::class, 'merchant-sales'],
            'CartCheckoutTool' => [CartCheckoutTool::class, 'merchant-sales'],
            'ProductCreateTool' => [ProductCreateTool::class, 'merchant-catalog'],
        ];
    }
}
