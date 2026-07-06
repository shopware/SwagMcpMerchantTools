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
    public function testToolGroup(string $toolClass, string $expectedGroup, bool $expectedVisible): void
    {
        $reflection = new \ReflectionClass($toolClass);

        $groupAttributes = $reflection->getAttributes(McpToolGroup::class);
        static::assertCount(1, $groupAttributes, \sprintf('%s must have exactly one McpToolGroup attribute.', $toolClass));
        static::assertSame($expectedGroup, $groupAttributes[0]->newInstance()->group);
    }

    /**
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    public function testToolDeferral(string $toolClass, string $expectedGroup, bool $expectedVisible): void
    {
        $reflection = new \ReflectionClass($toolClass);

        $toolAttributes = $reflection->getAttributes(McpTool::class);
        static::assertCount(1, $toolAttributes, \sprintf('%s must have exactly one McpTool attribute.', $toolClass));

        $meta = $toolAttributes[0]->newInstance()->meta;

        if ($expectedVisible) {
            static::assertIsArray($meta, \sprintf('%s must declare meta to stay visible.', $toolClass));
            static::assertFalse($meta['deferred'], \sprintf('%s must have meta[deferred] === false.', $toolClass));

            return;
        }

        static::assertTrue(
            $meta === null || ($meta['deferred'] ?? true) === true,
            \sprintf('%s must be deferred by default (no meta[deferred] === false).', $toolClass)
        );
    }

    /**
     * @return array<string, array{class-string, string, bool}>
     */
    public static function toolProvider(): array
    {
        return [
            'OrderSummaryTool' => [OrderSummaryTool::class, 'merchant-analytics', false],
            'CustomerLookupTool' => [CustomerLookupTool::class, 'merchant-analytics', false],
            'RevenueReportTool' => [RevenueReportTool::class, 'merchant-analytics', false],
            'BestsellerReportTool' => [BestsellerReportTool::class, 'merchant-analytics', false],
            'StorefrontSearchTool' => [StorefrontSearchTool::class, 'merchant-sales', true],
            'CheckoutMethodsTool' => [CheckoutMethodsTool::class, 'merchant-sales', false],
            'CartManageTool' => [CartManageTool::class, 'merchant-sales', false],
            'CartCheckoutTool' => [CartCheckoutTool::class, 'merchant-sales', false],
            'ProductCreateTool' => [ProductCreateTool::class, 'merchant-catalog', false],
        ];
    }
}
