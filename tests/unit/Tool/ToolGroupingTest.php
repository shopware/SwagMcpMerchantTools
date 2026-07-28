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
     * Visibility derives entirely from the group: only core's `discovery` group
     * is advertised, so every merchant tool is deferred and reachable through
     * shopware-tool-search or shopware-toolset-enable. No tool may re-open the
     * default surface by declaring a visibility hint of its own.
     *
     * @param class-string $toolClass
     */
    #[DataProvider('toolClassProvider')]
    public function testToolDeclaresNoVisibilityMeta(string $toolClass): void
    {
        $reflection = new \ReflectionClass($toolClass);

        $toolAttributes = $reflection->getAttributes(McpTool::class);
        static::assertCount(1, $toolAttributes, \sprintf('%s must have exactly one McpTool attribute.', $toolClass));

        $meta = $toolAttributes[0]->newInstance()->meta;

        static::assertTrue(
            $meta === null || !\array_key_exists('deferred', $meta),
            \sprintf('%s must not declare meta[deferred]; deferral follows from McpToolGroup.', $toolClass)
        );
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function toolClassProvider(): array
    {
        return array_map(static fn (array $row): array => [$row[0]], self::toolProvider());
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
