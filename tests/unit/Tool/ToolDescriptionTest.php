<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
 * Guards the routing rules from src/Core/Framework/Mcp/Tool/AGENTS.md,
 * "Description quality (LLM routing)". Descriptions are what an LLM reads to
 * pick a tool, so a regression here misroutes calls without breaking anything
 * a normal test would notice.
 *
 * Pins rules and tool names, never prose: any reword that keeps the contrast
 * keeps the sibling's name, and tool names are stable public identifiers.
 * Trigger vocabulary is deliberately not asserted here -- that is measured
 * end to end by shopware-mcp-evals.
 *
 * @internal
 */
final class ToolDescriptionTest extends TestCase
{
    /**
     * Descriptions ship on every tools/list once a toolset is enabled, so they
     * are a token budget, not a docblock.
     */
    private const MAX_LENGTH = 700;

    /**
     * Naming a sibling as an imperative prerequisite trains the model to call
     * that sibling as its first action, even when the user asked for this tool
     * and already supplied everything it needs. Real prerequisites belong in
     * #[McpToolDependsOn]; in prose, keep them conditional.
     */
    private const IMPERATIVE_PATTERNS = [
        '/\buse\s+(merchant|shopware)-[a-z-]+\s+to\b/i',
        '/\b(call|run|use)\s+(merchant|shopware)-[a-z-]+\s+(first|before)\b/i',
        '/\bfirst,?\s+(use|call|run)\b/i',
    ];

    /**
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    public function testDescriptionHasNoImperativeSiblingPrerequisite(string $toolClass): void
    {
        $description = self::description($toolClass);

        foreach (self::IMPERATIVE_PATTERNS as $pattern) {
            static::assertDoesNotMatchRegularExpression(
                $pattern,
                $description,
                \sprintf(
                    '%s names another tool as an imperative prerequisite. Declare real prerequisites with '
                    . '#[McpToolDependsOn] and keep the prose conditional, e.g. "if you don\'t already know the '
                    . 'method IDs, merchant-checkout-methods will tell you" (see core EntitySearchTool).',
                    $toolClass
                )
            );
        }
    }

    /**
     * @param class-string $toolClass
     * @param list<string> $expectedSiblings
     */
    #[DataProvider('boundaryProvider')]
    public function testDescriptionNamesItsBoundaryCounterpart(string $toolClass, array $expectedSiblings): void
    {
        $description = self::description($toolClass);

        foreach ($expectedSiblings as $sibling) {
            static::assertStringContainsString(
                $sibling,
                $description,
                \sprintf(
                    '%s must state what it is NOT for and name %s as the tool that handles that case. '
                    . 'Without the contrast the model picks this tool for adjacent tasks.',
                    $toolClass,
                    $sibling
                )
            );
        }
    }

    /**
     * @param class-string $toolClass
     */
    #[DataProvider('toolProvider')]
    public function testDescriptionStaysWithinBudget(string $toolClass): void
    {
        $description = self::description($toolClass);

        static::assertLessThan(
            self::MAX_LENGTH,
            mb_strlen($description),
            \sprintf('%s description exceeds %d characters. Tighten it instead of appending.', $toolClass, self::MAX_LENGTH)
        );
    }

    /**
     * Every sibling referenced in a boundary must be a real tool, so a rename
     * cannot leave a dangling cross-reference behind.
     */
    public function testBoundarySiblingsAreKnownToolNames(): void
    {
        $merchantTools = [];
        foreach (array_keys(self::toolProvider()) as $key) {
            /** @var class-string $toolClass */
            $toolClass = self::toolProvider()[$key][0];
            $merchantTools[] = self::tool($toolClass)->name;
        }

        // Core tool names are not resolvable from this plugin's unit suite, so
        // only the merchant-* half of the graph can be verified here. Core
        // names are covered by McpCapabilityDiscoveryTest in the platform.
        foreach (self::boundaryProvider() as [$toolClass, $expectedSiblings]) {
            foreach ($expectedSiblings as $sibling) {
                if (!str_starts_with($sibling, 'merchant-')) {
                    continue;
                }

                static::assertContains(
                    $sibling,
                    $merchantTools,
                    \sprintf('%s references unknown merchant tool "%s".', $toolClass, $sibling)
                );
            }
        }
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function toolProvider(): array
    {
        return [
            'OrderSummaryTool' => [OrderSummaryTool::class],
            'CustomerLookupTool' => [CustomerLookupTool::class],
            'RevenueReportTool' => [RevenueReportTool::class],
            'BestsellerReportTool' => [BestsellerReportTool::class],
            'StorefrontSearchTool' => [StorefrontSearchTool::class],
            'CheckoutMethodsTool' => [CheckoutMethodsTool::class],
            'CartManageTool' => [CartManageTool::class],
            'CartCheckoutTool' => [CartCheckoutTool::class],
            'ProductCreateTool' => [ProductCreateTool::class],
        ];
    }

    /**
     * The intended routing graph: which tool must point at which sibling for
     * the case it does not handle.
     *
     * @return array<string, array{class-string, list<string>}>
     */
    public static function boundaryProvider(): array
    {
        return [
            'OrderSummaryTool' => [OrderSummaryTool::class, ['shopware-entity-search', 'shopware-order-state']],
            'CustomerLookupTool' => [CustomerLookupTool::class, ['shopware-entity-search']],
            'RevenueReportTool' => [RevenueReportTool::class, ['merchant-bestseller-report']],
            'BestsellerReportTool' => [BestsellerReportTool::class, ['merchant-revenue-report']],
            'StorefrontSearchTool' => [StorefrontSearchTool::class, ['shopware-entity-search']],
            'CheckoutMethodsTool' => [CheckoutMethodsTool::class, ['merchant-cart-checkout']],
            'CartManageTool' => [CartManageTool::class, ['merchant-cart-checkout']],
            'CartCheckoutTool' => [CartCheckoutTool::class, ['merchant-checkout-methods']],
            'ProductCreateTool' => [ProductCreateTool::class, ['shopware-entity-upsert']],
        ];
    }

    /**
     * @param class-string $toolClass
     */
    private static function description(string $toolClass): string
    {
        $description = self::tool($toolClass)->description;

        static::assertNotNull($description, \sprintf('%s must declare a description.', $toolClass));

        return $description;
    }

    /**
     * @param class-string $toolClass
     */
    private static function tool(string $toolClass): McpTool
    {
        $attributes = (new \ReflectionClass($toolClass))->getAttributes(McpTool::class);

        static::assertCount(1, $attributes, \sprintf('%s must have exactly one McpTool attribute.', $toolClass));

        return $attributes[0]->newInstance();
    }
}
