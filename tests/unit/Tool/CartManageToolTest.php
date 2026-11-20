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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Swag\McpMerchantTools\Tool\CartManageTool;

/**
 * @internal
 */
#[CoversClass(CartManageTool::class)]
class CartManageToolTest extends TestCase
{
    private SalesChannelContextServiceInterface $contextService;

    private CartService&MockObject $cartService;

    private SalesChannelContext $salesChannelContext;

    public function testCreateReturnsToken(): void
    {
        $tool = $this->createTool();

        $this->cartService->expects($this->once())->method('createNew');

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'create',
        );

        static::assertFalse($output->isError());
        static::assertIsString($output->data['token']);
        static::assertNotSame('', $output->data['token']);
        static::assertSame([], $output->data['lineItems']);
        static::assertEqualsWithDelta(0.0, $output->data['totalPrice'], 0.001);
        static::assertSame(0, $output->data['itemCount']);
    }

    public function testAddReturnsFormattedCart(): void
    {
        $tool = $this->createTool();
        $productId = Uuid::randomHex();

        $cart = $this->createCartWithItems([
            ['id' => $productId, 'label' => 'Blue T-Shirt', 'quantity' => 2, 'unitPrice' => 29.99, 'totalPrice' => 59.98],
        ], 59.98, 50.40);

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('add')->willReturn($cart);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'add',
            token: 'test-token',
            productId: $productId,
            quantity: 2,
        );

        static::assertFalse($output->isError());
        static::assertSame('test-token', $output->data['token']);
        static::assertCount(1, $output->data['lineItems']);
        static::assertSame('Blue T-Shirt', $output->data['lineItems'][0]['label']);
        static::assertSame(2, $output->data['lineItems'][0]['quantity']);
        static::assertEqualsWithDelta(59.98, $output->data['totalPrice'], 0.001);
        static::assertSame(1, $output->data['itemCount']);
    }

    public function testRemoveReturnsFormattedCart(): void
    {
        $tool = $this->createTool();

        $cart = $this->createCartWithItems([], 0.0, 0.0);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('remove')->willReturn($cart);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'remove',
            token: 'test-token',
            lineItemId: 'some-item-id',
        );

        static::assertFalse($output->isError());
        static::assertSame(0, $output->data['itemCount']);
    }

    public function testUpdateReturnsFormattedCart(): void
    {
        $tool = $this->createTool();
        $productId = Uuid::randomHex();

        $cart = $this->createCartWithItems([
            ['id' => $productId, 'label' => 'Blue T-Shirt', 'quantity' => 5, 'unitPrice' => 29.99, 'totalPrice' => 149.95],
        ], 149.95, 126.01);

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('changeQuantity')->willReturn($cart);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'update',
            token: 'test-token',
            lineItemId: $productId,
            quantity: 5,
        );

        static::assertFalse($output->isError());
        static::assertSame(5, $output->data['lineItems'][0]['quantity']);
    }

    public function testGetReturnsCurrentCartState(): void
    {
        $tool = $this->createTool();

        $cart = $this->createCartWithItems([], 0.0, 0.0);
        $this->cartService->method('getCart')->willReturn($cart);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'get',
            token: 'test-token',
        );

        static::assertFalse($output->isError());
        static::assertSame('test-token', $output->data['token']);
    }

    public function testMissingTokenReturnsError(): void
    {
        $tool = $this->createTool();

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'add',
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Token is required', $output->error->message);
    }

    public function testMissingProductIdForAddReturnsError(): void
    {
        $tool = $this->createTool();

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'add',
            token: 'test-token',
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('productId is required', $output->error->message);
    }

    public function testInvalidActionReturnsError(): void
    {
        $tool = $this->createTool();

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'invalid',
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Invalid action', $output->error->message);
    }

    public function testExceptionInCartServiceReturnsError(): void
    {
        $tool = $this->createTool();

        $this->cartService->method('getCart')->willThrowException(new \RuntimeException('Cart expired'));

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'get',
            token: 'expired-token',
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Cart expired', $output->error->message);
    }

    public function testDeniesAccessWithoutPermission(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $contextProvider = static::createStub(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        $tool = new CartManageTool(
            static::createStub(SalesChannelContextServiceInterface::class),
            static::createStub(CartService::class),
            $contextProvider,
        );

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'create',
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('Missing privilege', $output->error->message);
        static::assertSame(McpToolError::MISSING_PRIVILEGE, $output->error->code);
    }

    public function testMissingLineItemIdForRemoveReturnsError(): void
    {
        $tool = $this->createTool();

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'remove',
            token: 'test-token',
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('lineItemId is required', $output->error->message);
    }

    public function testMissingLineItemIdForUpdateReturnsError(): void
    {
        $tool = $this->createTool();

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'update',
            token: 'test-token',
        );

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('lineItemId is required', $output->error->message);
    }

    private function createTool(): CartManageTool
    {
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);

        $this->contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $this->contextService->method('get')->willReturn($this->salesChannelContext);

        $this->cartService = $this->createMock(CartService::class);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        return new CartManageTool($this->contextService, $this->cartService, $contextProvider);
    }

    /**
     * @param list<array{id: string, label: string, quantity: int, unitPrice: float, totalPrice: float}> $items
     */
    private function createCartWithItems(array $items, float $totalPrice, float $netPrice): Cart
    {
        $lineItems = [];
        foreach ($items as $item) {
            $lineItem = new LineItem($item['id'], LineItem::PRODUCT_LINE_ITEM_TYPE, $item['id'], $item['quantity']);
            $lineItem->setLabel($item['label']);
            $lineItem->setPrice(new CalculatedPrice(
                $item['unitPrice'],
                $item['totalPrice'],
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                $item['quantity'],
            ));
            $lineItems[] = $lineItem;
        }

        $cart = new Cart('test-token');
        $cart->setLineItems(new LineItemCollection($lineItems));

        $taxCollection = new CalculatedTaxCollection();
        $cart->setPrice(new CartPrice(
            $netPrice,
            $totalPrice,
            $totalPrice,
            $taxCollection,
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
        ));

        return $cart;
    }
}
