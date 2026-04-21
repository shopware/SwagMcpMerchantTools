<?php declare(strict_types=1);

namespace Swag\McpMerchantAssistant\Tests\Unit\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Swag\McpMerchantAssistant\Tool\BestsellerReportTool;
use Swag\McpMerchantAssistant\Tool\CartCheckoutTool;
use Swag\McpMerchantAssistant\Tool\CartManageTool;
use Swag\McpMerchantAssistant\Tool\CheckoutMethodsTool;
use Swag\McpMerchantAssistant\Tool\CustomerLookupTool;
use Swag\McpMerchantAssistant\Tool\OrderSummaryTool;
use Swag\McpMerchantAssistant\Tool\ProductCreateTool;
use Swag\McpMerchantAssistant\Tool\RevenueReportTool;
use Swag\McpMerchantAssistant\Tool\StorefrontSearchTool;

/**
 * @internal
 */
#[CoversClass(McpToolResponse::class)]
class AclEnforcementTest extends TestCase
{
    public function testOrderSummaryToolDenied(): void
    {
        $tool = new OrderSummaryTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('10001'), 'order:read');
    }

    public function testCustomerLookupToolDenied(): void
    {
        $tool = new CustomerLookupTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('test@example.com'), 'customer:read');
    }

    public function testProductCreateToolDenied(): void
    {
        $tool = new ProductCreateTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('Test', 'SW-001', 19.99), 'product:create');
    }

    public function testBestsellerReportToolDenied(): void
    {
        $tool = new BestsellerReportTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('2025-01-01', '2025-12-31'), 'order:read');
    }

    public function testRevenueReportToolDenied(): void
    {
        $tool = new RevenueReportTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('2025-01-01', '2025-12-31'), 'order:read');
    }

    public function testCartManageToolDenied(): void
    {
        $tool = new CartManageTool(
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(CartService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('sc-1', 'create'), 'sales_channel:read');
    }

    public function testCartCheckoutToolDenied(): void
    {
        $tool = new CartCheckoutTool(
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(CartService::class),
            $this->createDeniedContextProvider(),
            static::createStub(DefinitionInstanceRegistry::class),
        );

        $this->assertAclDenied(($tool)('sc-1', 'token', 'cust-1'), 'sales_channel:read');
    }

    public function testCheckoutMethodsToolDenied(): void
    {
        $tool = new CheckoutMethodsTool(
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(AbstractPaymentMethodRoute::class),
            $this->createMock(AbstractShippingMethodRoute::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('sc-1'), 'sales_channel:read');
    }

    public function testStorefrontSearchToolDenied(): void
    {
        $tool = new StorefrontSearchTool(
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(RequestCriteriaBuilder::class),
            $this->createMock(JsonEntityEncoder::class),
            $this->createDeniedContextProvider(),
            $this->createMock(Connection::class),
        );

        $this->assertAclDenied(($tool)('sc-1'), 'sales_channel:read');
    }

    private function createDeniedContextProvider(): McpContextProvider
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $provider = $this->createMock(McpContextProvider::class);
        $provider->method('getContext')->willReturn($context);

        return $provider;
    }

    private function assertAclDenied(string $output, string $expectedPrivilege): void
    {
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege', $data['error']);
        static::assertStringContainsString($expectedPrivilege, $data['error']);
    }
}
