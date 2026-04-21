<?php declare(strict_types=1);

namespace Swag\McpMerchantAssistant\Tests\Integration\Scenario;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Tests\Integration\Core\Framework\Mcp\Scenario\McpScenarioTestCase;
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
abstract class MerchantScenarioTestCase extends McpScenarioTestCase
{
    protected OrderSummaryTool $orderSummaryTool;

    protected ProductCreateTool $productCreateTool;

    protected CustomerLookupTool $customerLookupTool;

    protected RevenueReportTool $revenueReportTool;

    protected BestsellerReportTool $bestsellerReportTool;

    protected CartManageTool $cartManageTool;

    protected CartCheckoutTool $cartCheckoutTool;

    protected CheckoutMethodsTool $checkoutMethodsTool;

    protected StorefrontSearchTool $storefrontSearchTool;

    protected function setUp(): void
    {
        parent::setUp();

        $container = static::getContainer();

        /** @var DefinitionInstanceRegistry $registry */
        $registry = $container->get(DefinitionInstanceRegistry::class);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $this->orderSummaryTool = new OrderSummaryTool($registry, $contextProvider);
        $this->customerLookupTool = new CustomerLookupTool($registry, $contextProvider);
        $this->productCreateTool = new ProductCreateTool($registry, $contextProvider);
        $this->revenueReportTool = new RevenueReportTool($registry, $contextProvider);
        $this->bestsellerReportTool = new BestsellerReportTool($registry, $contextProvider);

        /** @var SalesChannelContextService $salesChannelContextService */
        $salesChannelContextService = $container->get(SalesChannelContextService::class);

        /** @var CartService $cartService */
        $cartService = $container->get(CartService::class);
        $this->cartManageTool = new CartManageTool($salesChannelContextService, $cartService, $contextProvider);
        /** @var DefinitionInstanceRegistry $registry */
        $registry = $container->get(DefinitionInstanceRegistry::class);
        $this->cartCheckoutTool = new CartCheckoutTool($salesChannelContextService, $cartService, $contextProvider, $registry);

        /** @var PaymentMethodRoute $paymentMethodRoute */
        $paymentMethodRoute = $container->get(PaymentMethodRoute::class);

        /** @var ShippingMethodRoute $shippingMethodRoute */
        $shippingMethodRoute = $container->get(ShippingMethodRoute::class);
        $this->checkoutMethodsTool = new CheckoutMethodsTool($salesChannelContextService, $paymentMethodRoute, $shippingMethodRoute, $contextProvider);

        /** @var RequestCriteriaBuilder $criteriaBuilder */
        $criteriaBuilder = $container->get(RequestCriteriaBuilder::class);

        /** @var JsonEntityEncoder $encoder */
        $encoder = $container->get(JsonEntityEncoder::class);

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        /** @var SalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = $container->get('sales_channel.product.repository');

        $this->storefrontSearchTool = new StorefrontSearchTool(
            $salesChannelContextService,
            $productRepository,
            $registry,
            $criteriaBuilder,
            $encoder,
            $contextProvider,
            $connection,
        );
    }
}
