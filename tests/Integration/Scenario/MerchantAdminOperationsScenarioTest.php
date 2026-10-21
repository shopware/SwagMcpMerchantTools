<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Tests\Integration\Scenario;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MerchantAdminOperationsScenarioTest extends MerchantScenarioTestCase
{
    public function testUS4ProductCreateDryRun(): void
    {
        $output = ($this->productCreateTool)(
            name: 'MCP Test Shirt',
            productNumber: 'MCP-US4-' . Uuid::randomHex(),
            grossPrice: 29.99,
            taxRate: 19,
            dryRun: true,
        );

        $data = $this->decodeToolOutput($output);

        static::assertTrue($data['_meta']['dryRun']);
        static::assertSame('MCP Test Shirt', $data['data']['name']);
        static::assertIsString($data['data']['taxId']);
        static::assertNotSame('', $data['data']['taxId']);
        static::assertNotCount(0, $data['data']['price']);
        static::assertEqualsWithDelta(29.99 / 1.19, $data['data']['price'][0]['net'], 0.01);
    }

    public function testUS4ProductCreateCommit(): void
    {
        $productNumber = 'MCP-US4-' . Uuid::randomHex();

        $output = ($this->productCreateTool)(
            name: 'MCP Commit Shirt',
            productNumber: $productNumber,
            grossPrice: 29.99,
            taxRate: 19,
            stock: 50,
            dryRun: false,
        );

        $data = $this->decodeToolOutput($output);

        static::assertFalse($data['_meta']['dryRun']);
        static::assertIsString($data['data']['productId']);
        static::assertNotSame('', $data['data']['productId']);

        $readOutput = ($this->entityReadTool)('product', $data['data']['productId']);
        $readData = $this->decodeToolOutput($readOutput);

        static::assertSame('MCP Commit Shirt', $readData['data']['name']);
        static::assertSame(50, $readData['data']['stock']);
    }
}
