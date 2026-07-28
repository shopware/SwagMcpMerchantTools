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
     * A boundary has to name the sibling, but it must not borrow the words a
     * user would type when they want that sibling. ToolSearch scores tools with
     * str_contains() over the lowercased description, so "the raw fallback for
     * editing existing records" makes this tool match "edit an existing
     * product" -- the exact request the boundary exists to hand away. Naming the
     * sibling is required; importing its verbs is the defect, so referenced tool
     * names are stripped before the check.
     *
     * Only cross-toolset pairs are listed. Same-toolset siblings (the two report
     * tools, cart-manage and cart-checkout) are advertised together, so the
     * model reads both descriptions side by side and the contrasting verbs are
     * what disambiguate them.
     *
     * @param class-string $toolClass
     * @param list<string> $forbidden
     */
    #[DataProvider('siblingVocabularyProvider')]
    public function testBoundaryDoesNotBorrowSiblingTriggerVocabulary(string $toolClass, array $forbidden): void
    {
        $withoutToolNames = preg_replace('/(merchant|shopware)-[a-z-]+/', '', mb_strtolower(self::description($toolClass)));
        static::assertIsString($withoutToolNames);

        foreach ($forbidden as $trigger) {
            static::assertStringNotContainsString(
                $trigger,
                $withoutToolNames,
                \sprintf(
                    '%s description contains "%s", which is vocabulary a user types when they want the sibling '
                    . 'tool this boundary hands the case to. ToolSearch matches by substring, so the boundary '
                    . 'pulls the tool into results for the request it means to exclude. Rephrase without the '
                    . 'sibling trigger words and keep the sibling name.',
                    $toolClass,
                    $trigger
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
     * Per tool, the sibling's trigger words that must not appear in its
     * description. Kept to verbs and qualifiers: the tool's own subject nouns
     * ("product", "order", "customer") legitimately appear and are not the
     * problem.
     *
     * @return array<string, array{class-string, list<string>}>
     */
    public static function siblingVocabularyProvider(): array
    {
        return [
            // shopware-entity-upsert: "edit an existing product", "update the price", "rename it"
            'ProductCreateTool' => [ProductCreateTool::class, ['edit', 'updat', 'modif', 'rename', 'existing']],
            // shopware-entity-search: "filter customers by segment", "list all customers"
            'CustomerLookupTool' => [CustomerLookupTool::class, ['filter', 'segment', 'list ']],
            // shopware-entity-search + shopware-order-state: "list all orders", "cancel it",
            // "move it into the shipped state". "state" is unavoidable inside the sibling's own
            // name, which is why names are stripped before this runs.
            'OrderSummaryTool' => [OrderSummaryTool::class, ['list ', 'cancel', 'move', 'transition', 'refund']],
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
