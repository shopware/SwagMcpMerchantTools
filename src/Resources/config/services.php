<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Swag\McpMerchantTools\Mcp\Prompt\MerchantContextPrompt;
use Swag\McpMerchantTools\Tool\BestsellerReportTool;
use Swag\McpMerchantTools\Tool\CartCheckoutTool;
use Swag\McpMerchantTools\Tool\CartManageTool;
use Swag\McpMerchantTools\Tool\CheckoutMethodsTool;
use Swag\McpMerchantTools\Tool\CustomerLookupTool;
use Swag\McpMerchantTools\Tool\OrderSummaryTool;
use Swag\McpMerchantTools\Tool\ProductCreateTool;
use Swag\McpMerchantTools\Tool\RevenueReportTool;
use Swag\McpMerchantTools\Tool\StorefrontSearchTool;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(MerchantContextPrompt::class)
        ->tag('shopware.mcp.prompt');

    foreach ([OrderSummaryTool::class, CustomerLookupTool::class, ProductCreateTool::class, RevenueReportTool::class, BestsellerReportTool::class] as $tool) {
        $services->set($tool)
            ->args([
                service(DefinitionInstanceRegistry::class),
                service(McpContextProvider::class),
            ])
            ->tag('shopware.mcp.tool');
    }

    $services->set(StorefrontSearchTool::class)
        ->args([
            service(SalesChannelContextService::class),
            service('sales_channel.product.repository'),
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(JsonEntityEncoder::class),
            service(McpContextProvider::class),
            service(Connection::class),
        ])
        ->tag('shopware.mcp.tool');

    $services->set(CartManageTool::class)
        ->args([
            service(SalesChannelContextService::class),
            service(CartService::class),
            service(McpContextProvider::class),
        ])
        ->tag('shopware.mcp.tool');

    $services->set(CartCheckoutTool::class)
        ->args([
            service(SalesChannelContextService::class),
            service(CartService::class),
            service(McpContextProvider::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.mcp.tool');

    $services->set(CheckoutMethodsTool::class)
        ->args([
            service(SalesChannelContextService::class),
            service(PaymentMethodRoute::class),
            service(ShippingMethodRoute::class),
            service(McpContextProvider::class),
        ])
        ->tag('shopware.mcp.tool');
};
