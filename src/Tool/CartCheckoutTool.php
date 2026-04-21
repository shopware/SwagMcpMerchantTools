<?php declare(strict_types=1);

namespace Swag\McpMerchantAssistant\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'merchant-cart-checkout', description: 'Place an order from an existing cart. Requires a cart token (from merchant-cart-manage "create"), a salesChannelId, and a customerId (registered customer). Optionally override the payment and shipping method (use merchant-checkout-methods to list available methods). Defaults to dryRun=true for order preview.')]
class CartCheckoutTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly CartService $cartService,
        private readonly McpContextProvider $contextProvider,
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(
        string $salesChannelId,
        string $token,
        string $customerId,
        string $paymentMethodId = '',
        string $shippingMethodId = '',
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'sales_channel:read', 'order:create')) {
            return $error;
        }

        try {
            $salesChannelContext = $this->contextService->get(new SalesChannelContextServiceParameters(
                salesChannelId: $salesChannelId,
                token: $token,
                customerId: $customerId,
            ));

            if ($paymentMethodId !== '') {
                $criteria = new Criteria([$paymentMethodId]);
                $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
                if ($this->registry->getRepository('payment_method')->searchIds($criteria, $context)->firstId() === null) {
                    return $this->error(\sprintf('Payment method "%s" does not exist or is not available in this sales channel.', $paymentMethodId));
                }
            }

            if ($shippingMethodId !== '') {
                $criteria = new Criteria([$shippingMethodId]);
                $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannelId));
                if ($this->registry->getRepository('shipping_method')->searchIds($criteria, $context)->firstId() === null) {
                    return $this->error(\sprintf('Shipping method "%s" does not exist or is not available in this sales channel.', $shippingMethodId));
                }
            }

            $cart = $this->cartService->getCart($token, $salesChannelContext);

            if ($cart->getLineItems()->count() === 0) {
                return $this->error('Cart is empty. Add items with merchant-cart-manage first.');
            }

            $lineItems = [];
            foreach ($cart->getLineItems()->getElements() as $item) {
                $lineItems[] = [
                    'id' => $item->getId(),
                    'label' => $item->getLabel(),
                    'quantity' => $item->getQuantity(),
                    'totalPrice' => $item->getPrice()?->getTotalPrice(),
                ];
            }

            if ($dryRun) {
                $effectivePaymentMethodId = $paymentMethodId !== '' ? $paymentMethodId : $salesChannelContext->getPaymentMethod()->getId();
                $effectiveShippingMethodId = $shippingMethodId !== '' ? $shippingMethodId : $salesChannelContext->getShippingMethod()->getId();

                $meta = ['dryRun' => true];
                if ($shippingMethodId !== '' && $shippingMethodId !== $salesChannelContext->getShippingMethod()->getId()) {
                    $meta['note'] = 'Shipping cost in this preview reflects the context default, not the requested shippingMethodId. Totals may differ on commit.';
                }

                return $this->success([
                    'token' => $token,
                    'customerId' => $customerId,
                    'lineItems' => $lineItems,
                    'totalPrice' => $cart->getPrice()->getTotalPrice(),
                    'netPrice' => $cart->getPrice()->getNetPrice(),
                    'taxAmount' => $cart->getPrice()->getCalculatedTaxes()->getAmount(),
                    'paymentMethodId' => $effectivePaymentMethodId,
                    'shippingMethodId' => $effectiveShippingMethodId,
                ], $meta);
            }

            $data = [];
            if ($paymentMethodId !== '') {
                $data['paymentMethodId'] = $paymentMethodId;
            }
            if ($shippingMethodId !== '') {
                $data['shippingMethodId'] = $shippingMethodId;
            }

            $orderId = $this->cartService->order($cart, $salesChannelContext, new RequestDataBag($data));

            return $this->success([
                'orderId' => $orderId,
                'totalPrice' => $cart->getPrice()->getTotalPrice(),
                'itemCount' => \count($lineItems),
            ], ['dryRun' => false]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
