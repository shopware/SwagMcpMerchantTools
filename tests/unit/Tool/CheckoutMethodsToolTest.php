<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\McpMerchantTools\Tool\CheckoutMethodsTool;

/**
 * @internal
 */
#[CoversClass(CheckoutMethodsTool::class)]
class CheckoutMethodsToolTest extends TestCase
{
    public function testPaymentTypeReturnsOnlyPaymentMethods(): void
    {
        $tool = $this->createTool(
            paymentMethods: $this->createPaymentMethods(),
            shippingMethods: $this->createShippingMethods(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'payment');

        static::assertFalse($output->isError());
        static::assertArrayHasKey('paymentMethods', $output->data);
        static::assertArrayNotHasKey('shippingMethods', $output->data);
        static::assertCount(2, $output->data['paymentMethods']);
        static::assertSame('Invoice', $output->data['paymentMethods'][0]['name']);
    }

    public function testShippingTypeReturnsOnlyShippingMethods(): void
    {
        $tool = $this->createTool(
            paymentMethods: $this->createPaymentMethods(),
            shippingMethods: $this->createShippingMethods(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'shipping');

        static::assertFalse($output->isError());
        static::assertArrayNotHasKey('paymentMethods', $output->data);
        static::assertArrayHasKey('shippingMethods', $output->data);
        static::assertCount(1, $output->data['shippingMethods']);
        static::assertSame('Standard Shipping', $output->data['shippingMethods'][0]['name']);
    }

    public function testAllTypeReturnsBoth(): void
    {
        $tool = $this->createTool(
            paymentMethods: $this->createPaymentMethods(),
            shippingMethods: $this->createShippingMethods(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'all');

        static::assertFalse($output->isError());
        static::assertArrayHasKey('paymentMethods', $output->data);
        static::assertArrayHasKey('shippingMethods', $output->data);
    }

    public function testEmptyMethodsLists(): void
    {
        $tool = $this->createTool(
            paymentMethods: new PaymentMethodCollection(),
            shippingMethods: new ShippingMethodCollection(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'all');

        static::assertFalse($output->isError());
        static::assertSame([], $output->data['paymentMethods']);
        static::assertSame([], $output->data['shippingMethods']);
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new CheckoutMethodsTool(
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(AbstractPaymentMethodRoute::class),
            static::createStub(AbstractShippingMethodRoute::class),
            $contextProvider,
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'all');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Missing privilege', $output->error->message);
        static::assertSame(McpToolError::MISSING_PRIVILEGE, $output->error->code);
    }

    public function testExceptionInRouteReturnsError(): void
    {
        $contextService = static::createStub(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willThrowException(new \RuntimeException('Sales channel not found'));

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $tool = new CheckoutMethodsTool(
            $contextService,
            static::createStub(AbstractPaymentMethodRoute::class),
            static::createStub(AbstractShippingMethodRoute::class),
            $contextProvider,
        );

        $output = ($tool)(salesChannelId: 'bad-id', type: 'all');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Sales channel not found', $output->error->message);
    }

    public function testInvalidTypeReturnsError(): void
    {
        $tool = $this->createTool(
            paymentMethods: new PaymentMethodCollection(),
            shippingMethods: new ShippingMethodCollection(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'invalid');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Invalid type', $output->error->message);
    }

    private function createTool(PaymentMethodCollection $paymentMethods, ShippingMethodCollection $shippingMethods): CheckoutMethodsTool
    {
        $context = $this->createMock(SalesChannelContext::class);

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willReturn($context);

        $paymentResult = new EntitySearchResult(
            'payment_method',
            $paymentMethods->count(),
            $paymentMethods,
            null,
            new Criteria(),
            $context->getContext(),
        );
        $paymentResponse = new PaymentMethodRouteResponse($paymentResult);

        $paymentRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentRoute->method('load')->willReturn($paymentResponse);

        $shippingResult = new EntitySearchResult(
            'shipping_method',
            $shippingMethods->count(),
            $shippingMethods,
            null,
            new Criteria(),
            $context->getContext(),
        );
        $shippingResponse = new ShippingMethodRouteResponse($shippingResult);

        $shippingRoute = $this->createMock(AbstractShippingMethodRoute::class);
        $shippingRoute->method('load')->willReturn($shippingResponse);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        return new CheckoutMethodsTool($contextService, $paymentRoute, $shippingRoute, $contextProvider);
    }

    private function createPaymentMethods(): PaymentMethodCollection
    {
        $pm1 = new PaymentMethodEntity();
        $pm1->setId(Uuid::randomHex());
        $pm1->setTranslated(['name' => 'Invoice', 'description' => 'Pay by invoice']);
        $pm1->setActive(true);
        $pm1->setPosition(1);

        $pm2 = new PaymentMethodEntity();
        $pm2->setId(Uuid::randomHex());
        $pm2->setTranslated(['name' => 'Credit Card', 'description' => 'Pay by credit card']);
        $pm2->setActive(true);
        $pm2->setPosition(2);

        return new PaymentMethodCollection([$pm1, $pm2]);
    }

    private function createShippingMethods(): ShippingMethodCollection
    {
        $sm = new ShippingMethodEntity();
        $sm->setId(Uuid::randomHex());
        $sm->setTranslated(['name' => 'Standard Shipping', 'description' => '3-5 business days']);
        $sm->setActive(true);

        return new ShippingMethodCollection([$sm]);
    }
}
