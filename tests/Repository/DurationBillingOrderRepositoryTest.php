<?php

namespace Tourze\DurationBillingBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\Exception\TestSetupException;
use Tourze\DurationBillingBundle\Repository\DurationBillingOrderRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingOrderRepository::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingOrderRepositoryTest extends AbstractRepositoryTestCase
{
    private static ?DurationBillingProduct $testProduct = null;

    protected function onSetUp(): void
    {
        if (null === self::$testProduct) {
            // 创建测试产品
            self::$testProduct = new DurationBillingProduct();
            self::$testProduct->setName('Test Product');
            self::$testProduct->setEnabled(true);
            self::$testProduct->setPricingRuleData(['class' => 'test']);
            self::getEntityManager()->persist(self::$testProduct);
            self::getEntityManager()->flush();
        }
    }

    public function testFindById(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $orderId = $order->getId();
        $this->assertNotNull($orderId);
        $found = $repository->findById($orderId);

        $this->assertNotNull($found);
        $this->assertEquals($order->getId(), $found->getId());

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindByOrderCode(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $found = $repository->findByOrderCode($order->getOrderCode());

        $this->assertNotNull($found);
        $this->assertEquals($order->getOrderCode(), $found->getOrderCode());

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testCountActiveOrders(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'USER123';
        $order1 = $this->createOrder($userId, OrderStatus::ACTIVE);
        $order2 = $this->createOrder($userId, OrderStatus::COMPLETED);
        $order3 = $this->createOrder('OTHER_USER', OrderStatus::ACTIVE);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->persist($order3);
        self::getEntityManager()->flush();

        $count = $repository->countActiveOrders($userId);

        $this->assertEquals(1, $count);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->remove($order3);
        self::getEntityManager()->flush();
    }

    public function testFindActiveOrdersByUser(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'USER123';
        $order1 = $this->createOrder($userId, OrderStatus::ACTIVE);
        $order2 = $this->createOrder($userId, OrderStatus::COMPLETED);
        $order3 = $this->createOrder('OTHER_USER', OrderStatus::ACTIVE);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->persist($order3);
        self::getEntityManager()->flush();

        $activeOrders = $repository->findActiveOrdersByUser($userId);

        $this->assertCount(1, $activeOrders);
        $this->assertEquals($order1->getId(), $activeOrders[0]->getId());

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->remove($order3);
        self::getEntityManager()->flush();
    }

    public function testFindByBusinessReference(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        $order->setMetadata([
            'business_type' => 'hotel_booking',
            'business_id' => 'BOOKING123',
        ]);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $found = $repository->findByBusinessReference('hotel_booking', 'BOOKING123');

        $this->assertNotNull($found);
        $this->assertEquals($order->getId(), $found->getId());

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindOrdersToEnd(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $now = new \DateTimeImmutable();
        $order1 = $this->createOrder();
        $order1->setStatus(OrderStatus::ACTIVE);
        $order1->setStartTime($now->modify('-2 hours'));

        $order2 = $this->createOrder();
        $order2->setStatus(OrderStatus::FROZEN);
        $order2->setStartTime($now->modify('-2 hours'));

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $ordersToEnd = $repository->findOrdersToEnd($now->modify('-1 hour'));

        // Filter to only include our test orders
        $testOrderIds = [$order1->getId(), $order2->getId()];
        $filteredOrders = array_filter($ordersToEnd, fn ($order) => in_array($order->getId(), $testOrderIds, true));

        $this->assertCount(1, $filteredOrders);
        $foundOrder = array_values($filteredOrders)[0];
        $this->assertEquals($order1->getId(), $foundOrder->getId());

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testFindExpiredFrozenOrders(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $now = new \DateTimeImmutable();
        $order = $this->createOrder();
        $order->setStatus(OrderStatus::FROZEN);
        $order->setStartTime($now->modify('-2 hours'));
        $order->setFrozenAt($now->modify('-1 hour')); // Frozen 1 hour ago
        $order->setFrozenMinutes(60);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $expiredOrders = $repository->findExpiredFrozenOrders(30);

        $this->assertCount(1, $expiredOrders);
        $this->assertEquals($order->getId(), $expiredOrders[0]->getId());

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testCount(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $count = $repository->count([]);
        $this->assertGreaterThanOrEqual(2, $count);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testCountByStatus(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order1 = $this->createOrder('USER1', OrderStatus::ACTIVE);
        $order2 = $this->createOrder('USER2', OrderStatus::COMPLETED);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $activeCount = $repository->count(['status' => OrderStatus::ACTIVE]);
        $this->assertGreaterThanOrEqual(1, $activeCount);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testCountByUserId(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'COUNT_USER_' . uniqid();
        $order1 = $this->createOrder($userId);
        $order2 = $this->createOrder($userId);
        $order3 = $this->createOrder('OTHER_USER');

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->persist($order3);
        self::getEntityManager()->flush();

        $userCount = $repository->count(['userId' => $userId]);
        $this->assertEquals(2, $userCount);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->remove($order3);
        self::getEntityManager()->flush();
    }

    public function testCountByMultipleCriteria(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'MULTI_USER_' . uniqid();
        $order1 = $this->createOrder($userId, OrderStatus::ACTIVE);
        $order2 = $this->createOrder($userId, OrderStatus::COMPLETED);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $count = $repository->count(['userId' => $userId, 'status' => OrderStatus::ACTIVE]);
        $this->assertEquals(1, $count);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testCountEmpty(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $count = $repository->count(['userId' => 'NONEXISTENT_USER_' . uniqid()]);
        $this->assertEquals(0, $count);
    }

    public function testFindBy(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'FINDBY_USER_' . uniqid();
        $order1 = $this->createOrder($userId, OrderStatus::ACTIVE);
        $order2 = $this->createOrder($userId, OrderStatus::COMPLETED);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $results = $repository->findBy(['userId' => $userId]);
        $this->assertCount(2, $results);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testFindByWithLimit(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'FINDBY_LIMIT_USER_' . uniqid();
        $order1 = $this->createOrder($userId);
        $order2 = $this->createOrder($userId);
        $order3 = $this->createOrder($userId);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->persist($order3);
        self::getEntityManager()->flush();

        $results = $repository->findBy(['userId' => $userId], null, 2);
        $this->assertCount(2, $results);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->remove($order3);
        self::getEntityManager()->flush();
    }

    public function testFindByWithOffset(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'FINDBY_OFFSET_USER_' . uniqid();
        $order1 = $this->createOrder($userId);
        $order2 = $this->createOrder($userId);
        $order3 = $this->createOrder($userId);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->persist($order3);
        self::getEntityManager()->flush();

        $results = $repository->findBy(['userId' => $userId], ['id' => 'ASC'], 2, 1);
        $this->assertCount(2, $results);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->remove($order3);
        self::getEntityManager()->flush();
    }

    public function testFindByEmpty(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $results = $repository->findBy(['userId' => 'NONEXISTENT_USER_' . uniqid()]);
        $this->assertEmpty($results);
    }

    public function testFindOneBy(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $orderCode = 'UNIQUE_CODE_' . uniqid();
        $order = $this->createOrder();
        $order->setOrderCode($orderCode);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['orderCode' => $orderCode]);
        $this->assertNotNull($found);
        $this->assertEquals($orderCode, $found->getOrderCode());

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindOneByWithOrderBy(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'FINDONE_ORDER_USER_' . uniqid();
        $order1 = $this->createOrder($userId);
        $order2 = $this->createOrder($userId);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['userId' => $userId], ['id' => 'ASC']);
        $this->assertNotNull($found);
        $this->assertEquals($userId, $found->getUserId());

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testFindOneByMultipleCriteria(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $userId = 'FINDONE_MULTI_USER_' . uniqid();
        $order1 = $this->createOrder($userId, OrderStatus::ACTIVE);
        $order2 = $this->createOrder($userId, OrderStatus::COMPLETED);

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['userId' => $userId, 'status' => OrderStatus::ACTIVE]);
        $this->assertNotNull($found);
        $this->assertEquals(OrderStatus::ACTIVE, $found->getStatus());

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testFindOneByNullableField(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        $order->setEndTime(null);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['endTime' => null]);
        $this->assertNotNull($found);
        $this->assertNull($found->getEndTime());

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindOneByNotFound(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $found = $repository->findOneBy(['orderCode' => 'NONEXISTENT_CODE_' . uniqid()]);
        $this->assertNull($found);
    }

    public function testFind(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $orderId = $order->getId();
        $found = $repository->find($orderId);
        $this->assertNotNull($found);
        $this->assertEquals($orderId, $found->getId());

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindNotFound(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $found = $repository->find(999999999);
        $this->assertNull($found);
    }

    public function testFindAll(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $all = $repository->findAll();
        $this->assertIsArray($all);
        $this->assertGreaterThanOrEqual(2, count($all));

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testFindByProduct(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $found = $repository->findBy(['product' => self::$testProduct]);
        $this->assertNotEmpty($found);
        $foundOrder = array_filter($found, fn ($o) => $o->getId() === $order->getId());
        $this->assertNotEmpty($foundOrder);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindByDateRange(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $now = new \DateTimeImmutable();
        $order = $this->createOrder();
        $order->setStartTime($now);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        // Doctrine doesn't support direct date range queries in findBy
        // This tests that we can find orders with specific start times
        $found = $repository->findBy(['startTime' => $now]);
        $this->assertNotEmpty($found);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindNullValues(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        $order->setEndTime(null);
        $order->setPaymentTime(null);
        $order->setFrozenAt(null);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $found = $repository->findBy(['endTime' => null]);
        $this->assertNotEmpty($found);
        $foundOrder = array_filter($found, fn ($o) => $o->getId() === $order->getId());
        $this->assertNotEmpty($foundOrder);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testDatabaseConnectionFailure(): void
    {
        // This test is conceptual - in real scenarios, we would mock the EntityManager
        // to throw connection exceptions. For integration tests, we assume DB is available.
        $repository = self::getService(DurationBillingOrderRepository::class);

        // Test that repository can handle basic operations
        $this->expectNotToPerformAssertions();
        try {
            $repository->findAll();
        } catch (\Exception $e) {
            self::fail('Unexpected database connection failure: ' . $e->getMessage());
        }
    }

    public function testNullFieldQueries(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        $order->setEndTime(null);
        $order->setPaymentTime(null);
        $order->setFrozenAt(null);
        $order->setActualAmount(null);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        // Test querying for null end time
        $nullEndTimeOrders = $repository->findBy(['endTime' => null]);
        $this->assertNotEmpty($nullEndTimeOrders);

        // Test querying for null payment time
        $nullPaymentTimeOrders = $repository->findBy(['paymentTime' => null]);
        $this->assertNotEmpty($nullPaymentTimeOrders);

        // Test querying for null frozen at
        $nullFrozenAtOrders = $repository->findBy(['frozenAt' => null]);
        $this->assertNotEmpty($nullFrozenAtOrders);

        // Test querying for null actual amount
        $nullAmountOrders = $repository->findBy(['actualAmount' => null]);
        $this->assertNotEmpty($nullAmountOrders);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testSave(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();

        $repository->save($order, true);
        $this->assertNotNull($order->getId());

        $repository->remove($order, true);
    }

    public function testRemove(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();
        $orderId = $order->getId();

        $repository->remove($order, true);

        $found = $repository->find($orderId);
        $this->assertNull($found);
    }

    public function testCountNullEndTime(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        $order->setEndTime(null);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $count = $repository->count(['endTime' => null]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testCountNullPaymentTime(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        $order->setPaymentTime(null);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $count = $repository->count(['paymentTime' => null]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testCountNullFrozenAt(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        $order->setFrozenAt(null);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $count = $repository->count(['frozenAt' => null]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testCountNullActualAmount(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();
        $order->setActualAmount(null);

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $count = $repository->count(['actualAmount' => null]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindByProductAssociation(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $found = $repository->findBy(['product' => self::$testProduct]);
        $this->assertNotEmpty($found);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testCountByProduct(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $count = $repository->count(['product' => self::$testProduct]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindOneByAssociationProductShouldReturnMatchingEntity(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order = $this->createOrder();

        self::getEntityManager()->persist($order);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['product' => self::$testProduct]);
        $this->assertNotNull($found);
        $this->assertInstanceOf(DurationBillingOrder::class, $found);
        $product = $found->getProduct();
        $this->assertNotNull($product);
        $this->assertInstanceOf(DurationBillingProduct::class, $product);
        if (null === self::$testProduct) {
            self::fail('Test product should not be null');
        }
        $testProductId = self::$testProduct->getId();
        $foundProductId = $product->getId();
        $this->assertEquals($testProductId, $foundProductId);

        self::getEntityManager()->remove($order);
        self::getEntityManager()->flush();
    }

    public function testFindByAssociationProductShouldReturnMatchingEntities(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $found = $repository->findBy(['product' => self::$testProduct], ['id' => 'ASC']);
        $this->assertNotEmpty($found);
        $this->assertGreaterThanOrEqual(2, count($found));

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    public function testCountByAssociationProductShouldReturnCorrectNumber(): void
    {
        $repository = self::getService(DurationBillingOrderRepository::class);
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();

        self::getEntityManager()->persist($order1);
        self::getEntityManager()->persist($order2);
        self::getEntityManager()->flush();

        $count = $repository->count(['product' => self::$testProduct]);
        $this->assertGreaterThanOrEqual(2, $count);

        self::getEntityManager()->remove($order1);
        self::getEntityManager()->remove($order2);
        self::getEntityManager()->flush();
    }

    private function createOrder(?string $userId = null, ?OrderStatus $status = null): DurationBillingOrder
    {
        $order = new DurationBillingOrder();
        if (null === self::$testProduct) {
            throw new TestSetupException('Test product is not initialized');
        }
        $order->setProduct(self::$testProduct);
        $order->setUserId($userId ?? 'USER123');
        $order->setOrderCode('ORDER_' . uniqid());
        $order->setStatus($status ?? OrderStatus::ACTIVE);
        $order->setStartTime(new \DateTimeImmutable());

        return $order;
    }

    /**
     * @return ServiceEntityRepository<DurationBillingOrder>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(DurationBillingOrderRepository::class);
    }

    protected function createNewEntity(): object
    {
        return $this->createOrder();
    }
}
