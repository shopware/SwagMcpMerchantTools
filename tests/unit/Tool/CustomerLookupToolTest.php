<?php declare(strict_types=1);

namespace Swag\McpMerchantTools\Tests\Unit\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Swag\McpMerchantTools\Tool\CustomerLookupTool;

/**
 * @internal
 */
#[CoversClass(CustomerLookupTool::class)]
class CustomerLookupToolTest extends TestCase
{
    public function testLookupByEmail(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->buildCustomer($customerId);

        $tool = $this->createTool($customer);
        $output = ($tool)(email: 'john@example.com');

        static::assertFalse($output->isError());
        static::assertSame($customerId, $output->data['id']);
        static::assertSame('john@example.com', $output->data['email']);
        static::assertSame('SW10001', $output->data['customerNumber']);
        static::assertSame('John', $output->data['firstName']);
        static::assertSame('Doe', $output->data['lastName']);
        static::assertSame('Standard', $output->data['group']);
        static::assertSame(3, $output->data['orderCount']);
        static::assertCount(1, $output->data['recentOrders']);
        static::assertSame('10001', $output->data['recentOrders'][0]['orderNumber']);
    }

    public function testLookupByCustomerNumber(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->buildCustomer($customerId);

        $tool = $this->createTool($customer);
        $output = ($tool)(customerNumber: 'SW10001');

        static::assertFalse($output->isError());
        static::assertSame($customerId, $output->data['id']);
    }

    public function testLookupByCustomerId(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->buildCustomer($customerId);

        $tool = $this->createTool($customer);
        $output = ($tool)(customerId: $customerId);

        static::assertFalse($output->isError());
        static::assertSame($customerId, $output->data['id']);
    }

    public function testNotFoundReturnsError(): void
    {
        $tool = $this->createTool(null);
        $output = ($tool)(email: 'unknown@example.com');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertSame('Customer not found.', $output->error->message);
    }

    public function testNoIdentifierReturnsError(): void
    {
        $tool = $this->createTool(null);
        $output = ($tool)();

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('email, customerNumber, or customerId', $output->error->message);
    }

    public function testOrderCustomerWithNullOrderIsSkipped(): void
    {
        $customerId = Uuid::randomHex();
        $customer = $this->buildCustomer($customerId);

        $orderCustomerWithoutOrder = new OrderCustomerEntity();
        $orderCustomerWithoutOrder->setId(Uuid::randomHex());
        $orderCustomerWithoutOrder->setUniqueIdentifier(Uuid::randomHex());

        $customer->getOrderCustomers()?->add($orderCustomerWithoutOrder);

        $tool = $this->createTool($customer);
        $output = ($tool)(email: 'john@example.com');

        static::assertFalse($output->isError());
        static::assertCount(1, $output->data['recentOrders']);
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

        $tool = new CustomerLookupTool($registry, $contextProvider);
        $output = ($tool)(email: 'john@example.com');

        static::assertTrue($output->isError());
        static::assertNotNull($output->error);
        static::assertStringContainsString('customer:read', $output->error->message);
    }

    private function createTool(?CustomerEntity $customer): CustomerLookupTool
    {
        $context = Context::createDefaultContext();

        $collection = new CustomerCollection();

        if ($customer !== null) {
            $collection->add($customer);
        }

        $result = new EntitySearchResult(
            'customer',
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            $context,
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($result);

        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->with('customer')->willReturn($repository);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($context);

        return new CustomerLookupTool($registry, $contextProvider);
    }

    private function buildCustomer(string $id): CustomerEntity
    {
        $group = new CustomerGroupEntity();
        $group->setId(Uuid::randomHex());
        $group->setName('Standard');
        $group->setUniqueIdentifier(Uuid::randomHex());

        $state = new StateMachineStateEntity();
        $state->setId(Uuid::randomHex());
        $state->setTechnicalName('open');
        $state->setUniqueIdentifier(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber('10001');
        $order->setAmountTotal(99.99);
        $order->setOrderDateTime(new \DateTimeImmutable('2025-03-01T10:00:00+00:00'));
        $order->setStateMachineState($state);
        $order->setUniqueIdentifier(Uuid::randomHex());

        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId(Uuid::randomHex());
        $orderCustomer->setOrder($order);
        $orderCustomer->setUniqueIdentifier(Uuid::randomHex());

        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setEmail('john@example.com');
        $customer->setCustomerNumber('SW10001');
        $customer->setFirstName('John');
        $customer->setLastName('Doe');
        $customer->setGroup($group);
        $customer->setActive(true);
        $customer->setCreatedAt(new \DateTimeImmutable('2024-01-15T12:00:00+00:00'));
        $customer->setOrderCount(3);
        $customer->setOrderTotalAmount(299.97);
        $customer->setLastOrderDate(new \DateTimeImmutable('2025-03-01T10:00:00+00:00'));
        $customer->setOrderCustomers(new OrderCustomerCollection([$orderCustomer]));
        $customer->setUniqueIdentifier($id);

        return $customer;
    }
}
