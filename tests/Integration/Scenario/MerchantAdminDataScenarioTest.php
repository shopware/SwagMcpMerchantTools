<?php declare(strict_types=1);

namespace Swag\McpMerchantAssistant\Tests\Integration\Scenario;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class MerchantAdminDataScenarioTest extends MerchantScenarioTestCase
{
    public function testUS1OrderSummaryByOrderNumber(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $orderNumber = 'MCP-US1-' . Uuid::randomHex();

        $customer = (new CustomerBuilder($ids, 'US1-cust'))
            ->add('email', 'mcp-us1@example.com')
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->build();

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = static::getContainer()->get('customer.repository');
        $customerRepository->create([$customer], $context);

        $order = (new OrderBuilder($ids, $orderNumber))
            ->add('orderCustomer', [
                'id' => $ids->get('orderCustomer'),
                'customerId' => $ids->get('US1-cust'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'email' => 'mcp-us1@example.com',
            ])
            ->addAddress('billing-address')
            ->addTransaction('transaction')
            ->add('lineItems', [[
                'id' => $ids->create('line-item'),
                'identifier' => $ids->get('line-item'),
                'label' => 'Test Product',
                'quantity' => 2,
                'type' => 'custom',
                'price' => ['unitPrice' => 10, 'totalPrice' => 20, 'quantity' => 2, 'calculatedTaxes' => [], 'taxRules' => []],
                'position' => 1,
            ]])
            ->add('deliveries', [[
                'id' => $ids->create('delivery'),
                'stateId' => $this->getStateMachineState(
                    OrderDeliveryStates::STATE_MACHINE,
                    OrderDeliveryStates::STATE_OPEN,
                ),
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingCosts' => ['unitPrice' => 0, 'totalPrice' => 0, 'quantity' => 1, 'calculatedTaxes' => [], 'taxRules' => []],
                'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingOrderAddress' => [
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'city' => 'Berlin',
                    'street' => 'Teststr. 1',
                    'zipcode' => '10115',
                    'country' => ['id' => $ids->create('country'), 'name' => 'Germany'],
                ],
            ]])
            ->build();

        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        $orderRepository->upsert([$order], Context::createDefaultContext());

        $output = ($this->orderSummaryTool)(orderNumber: $orderNumber);
        $data = $this->decodeToolOutput($output);

        static::assertSame($orderNumber, $data['data']['orderNumber']);
        static::assertNotNull($data['data']['status']);
        static::assertIsFloat($data['data']['amountTotal']);
        static::assertSame('mcp-us1@example.com', $data['data']['customer']['email']);
        static::assertNotEmpty($data['data']['lineItems']);
        static::assertSame('Test Product', $data['data']['lineItems'][0]['label']);
        static::assertNotEmpty($data['data']['transactions']);
        static::assertNotEmpty($data['data']['deliveries']);
    }
}
