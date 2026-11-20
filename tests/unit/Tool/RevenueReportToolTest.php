<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Swag\McpMerchantTools\Tool\RevenueReportTool;

/**
 * @internal
 */
#[CoversClass(RevenueReportTool::class)]
class RevenueReportToolTest extends TestCase
{
    public function testReturnsRevenueReport(): void
    {
        $tool = $this->createTool(1500.0, 10, 150.0, [
            new Bucket('2025-03-01', 5, new SumResult('dayRevenue', 800.0)),
            new Bucket('2025-03-02', 5, new SumResult('dayRevenue', 700.0)),
        ]);

        $output = ($tool)('2025-03-01', '2025-03-02');

        static::assertFalse($output->isError());
        static::assertSame(['from' => '2025-03-01', 'to' => '2025-03-02'], $output->data['period']);
        static::assertEqualsWithDelta(1500.0, $output->data['totalRevenue'], 0.01);
        static::assertSame(10, $output->data['orderCount']);
        static::assertEqualsWithDelta(150.0, $output->data['averageOrderValue'], 0.01);
        static::assertCount(2, $output->data['timeline']);
        static::assertSame('2025-03-01', $output->data['timeline'][0]['date']);
        static::assertEqualsWithDelta(800.0, $output->data['timeline'][0]['revenue'], 0.01);
        static::assertSame('2025-03-02', $output->data['timeline'][1]['date']);
        static::assertEqualsWithDelta(700.0, $output->data['timeline'][1]['revenue'], 0.01);
    }

    public function testInvalidGroupByReturnsError(): void
    {
        $tool = $this->createTool(0, 0, 0, []);
        $output = ($tool)('2025-01-01', '2025-01-31', 'quarter');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('quarter', $output->error->message);
        static::assertStringContainsString('day, week, month', $output->error->message);
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->expects($this->never())->method('getRepository');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new RevenueReportTool($registry, $contextProvider);
        $output = ($tool)('2025-01-01', '2025-01-31');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('order:read', $output->error->message);
    }

    public function testSalesChannelIdFilterIsApplied(): void
    {
        $tool = $this->createTool(500.0, 5, 100.0, [
            new Bucket('2025-03-01', 5, new SumResult('dayRevenue', 500.0)),
        ]);

        $output = ($tool)('2025-03-01', '2025-03-31', 'day', 'sc-123');

        static::assertFalse($output->isError());
        static::assertEqualsWithDelta(500.0, $output->data['totalRevenue'], 0.01);
    }

    public function testNonSumResultTimelineBucketFallsBackToZero(): void
    {
        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection([
            new SumResult('revenue', 100.0),
            new CountResult('orderCount', 1),
            new AvgResult('avgOrderValue', 100.0),
            new DateHistogramResult('revenueOverTime', [
                new Bucket('2025-03-01', 1, new CountResult('dayRevenue', 1)),
            ]),
        ]);

        $result = new EntitySearchResult('order', 0, new EntityCollection(), $aggregations, new Criteria(), $context);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repository);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new RevenueReportTool($registry, $contextProvider);
        $output = ($tool)('2025-03-01', '2025-03-31');

        static::assertFalse($output->isError());
        static::assertCount(1, $output->data['timeline']);
        static::assertSame(0, $output->data['timeline'][0]['revenue']);
    }

    public function testEmptyResultReturnsZeros(): void
    {
        $tool = $this->createTool(0, 0, 0, []);
        $output = ($tool)('2025-01-01', '2025-01-31');

        static::assertFalse($output->isError());
        static::assertEqualsWithDelta(0.0, $output->data['totalRevenue'], 0.01);
        static::assertSame(0, $output->data['orderCount']);
        static::assertEqualsWithDelta(0.0, $output->data['averageOrderValue'], 0.01);
        static::assertSame([], $output->data['timeline']);
    }

    /**
     * @param list<Bucket> $buckets
     */
    private function createTool(float $totalRevenue, int $orderCount, float $avgValue, array $buckets): RevenueReportTool
    {
        $context = Context::createDefaultContext();

        $aggregations = new AggregationResultCollection([
            new SumResult('revenue', $totalRevenue),
            new CountResult('orderCount', $orderCount),
            new AvgResult('avgOrderValue', $avgValue),
            new DateHistogramResult('revenueOverTime', $buckets),
        ]);

        $result = new EntitySearchResult(
            'order',
            0,
            new EntityCollection(),
            $aggregations,
            new Criteria(),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->with('order')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return new RevenueReportTool($registry, $contextProvider);
    }
}
