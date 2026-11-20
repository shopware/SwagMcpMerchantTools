<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\McpMerchantTools\Tool\CartCheckoutTool;

/**
 * @internal
 */
#[CoversClass(CartCheckoutTool::class)]
class CartCheckoutToolTest extends TestCase
{
    public function testDryRunReturnsPreview(): void
    {
        $productId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();

        $cart = $this->createCart($productId, 'Blue T-Shirt', 2, 29.99, 59.98);

        $tool = $this->createTool($cart, $paymentMethodId, $shippingMethodId);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            token: 'test-token',
            customerId: Uuid::randomHex(),
            dryRun: true,
        );

        static::assertFalse($output->isError());
        static::assertTrue($output->meta['dryRun']);
        static::assertSame('test-token', $output->data['token']);
        static::assertCount(1, $output->data['lineItems']);
        static::assertSame('Blue T-Shirt', $output->data['lineItems'][0]['label']);
        static::assertEqualsWithDelta(59.98, $output->data['totalPrice'], 0.001);
        static::assertSame($paymentMethodId, $output->data['paymentMethodId']);
        static::assertSame($shippingMethodId, $output->data['shippingMethodId']);
    }

    public function testOrderPlacementReturnsOrderId(): void
    {
        $productId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $cart = $this->createCart($productId, 'Blue T-Shirt', 1, 29.99, 29.99);

        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($cart);
        $cartService->expects($this->once())->method('order')->willReturn($orderId);

        $tool = $this->createTool($cart, Uuid::randomHex(), Uuid::randomHex(), $cartService);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            token: 'test-token',
            customerId: Uuid::randomHex(),
            dryRun: false,
        );

        static::assertFalse($output->isError());
        static::assertFalse($output->meta['dryRun']);
        static::assertSame($orderId, $output->data['orderId']);
    }

    public function testOrderPlacementWithCustomPaymentAndShippingIds(): void
    {
        $productId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $customPaymentId = Uuid::randomHex();
        $customShippingId = Uuid::randomHex();

        $cart = $this->createCart($productId, 'Blue T-Shirt', 1, 29.99, 29.99);

        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($cart);
        $cartService->expects($this->once())
            ->method('order')
            ->with(
                $cart,
                static::anything(),
                static::callback(function (RequestDataBag $bag) use ($customPaymentId, $customShippingId): bool {
                    static::assertSame($customPaymentId, $bag->get('paymentMethodId'));
                    static::assertSame($customShippingId, $bag->get('shippingMethodId'));

                    return true;
                }),
            )
            ->willReturn($orderId);

        $tool = $this->createTool($cart, Uuid::randomHex(), Uuid::randomHex(), $cartService);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            token: 'test-token',
            customerId: Uuid::randomHex(),
            paymentMethodId: $customPaymentId,
            shippingMethodId: $customShippingId,
            dryRun: false,
        );

        static::assertFalse($output->isError());
        static::assertFalse($output->meta['dryRun']);
        static::assertSame($orderId, $output->data['orderId']);
    }

    public function testEmptyCartReturnsError(): void
    {
        $cart = new Cart('test-token');
        $cart->setLineItems(new LineItemCollection());
        $cart->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
        ));

        $tool = $this->createTool($cart, Uuid::randomHex(), Uuid::randomHex());

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            token: 'test-token',
            customerId: Uuid::randomHex(),
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Cart is empty', $output->error->message);
    }

    public function testCustomPaymentMethodIdIsUsedInPreview(): void
    {
        $customPaymentId = Uuid::randomHex();
        $cart = $this->createCart(Uuid::randomHex(), 'Product', 1, 10.0, 10.0);

        $tool = $this->createTool($cart, Uuid::randomHex(), Uuid::randomHex());

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            token: 'test-token',
            customerId: Uuid::randomHex(),
            paymentMethodId: $customPaymentId,
            dryRun: true,
        );

        static::assertSame($customPaymentId, $output->data['paymentMethodId']);
    }

    public function testOrderExceptionReturnsError(): void
    {
        $cart = $this->createCart(Uuid::randomHex(), 'Product', 1, 10.0, 10.0);

        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($cart);
        $cartService->method('order')->willThrowException(new \RuntimeException('Payment failed'));

        $tool = $this->createTool($cart, Uuid::randomHex(), Uuid::randomHex(), $cartService);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            token: 'test-token',
            customerId: Uuid::randomHex(),
            dryRun: false,
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Payment failed', $output->error->message);
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new CartCheckoutTool(
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(CartService::class),
            $contextProvider,
            static::createStub(DefinitionInstanceRegistry::class),
        );

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            token: 'test-token',
            customerId: Uuid::randomHex(),
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Missing privilege', $output->error->message);
        static::assertSame(McpToolError::MISSING_PRIVILEGE, $output->error->code);
    }

    private function createTool(Cart $cart, string $paymentMethodId, string $shippingMethodId, (CartService&MockObject)|null $cartService = null): CartCheckoutTool
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId($paymentMethodId);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($shippingMethodId);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getPaymentMethod')->willReturn($paymentMethod);
        $context->method('getShippingMethod')->willReturn($shippingMethod);

        $contextService = static::createStub(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willReturn($context);

        $cartService ??= $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($cart);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        $idSearchResult = static::createStub(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn(Uuid::randomHex());

        $methodRepository = static::createStub(EntityRepository::class);
        $methodRepository->method('searchIds')->willReturn($idSearchResult);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($methodRepository);

        return new CartCheckoutTool($contextService, $cartService, $contextProvider, $registry);
    }

    private function createCart(string $productId, string $label, int $quantity, float $unitPrice, float $totalPrice): Cart
    {
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, $quantity);
        $lineItem->setLabel($label);
        $lineItem->setPrice(new CalculatedPrice(
            $unitPrice,
            $totalPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            $quantity,
        ));

        $cart = new Cart('test-token');
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        $cart->setPrice(new CartPrice(
            $totalPrice * 0.84,
            $totalPrice,
            $totalPrice,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
        ));

        return $cart;
    }
}
