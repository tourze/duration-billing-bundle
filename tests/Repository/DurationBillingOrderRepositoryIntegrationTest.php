<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Tests\Repository;

use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Repository\DurationBillingOrderRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * 时长计费订单仓库集成测试
 *
 * @internal
 */
#[CoversClass(DurationBillingOrderRepository::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingOrderRepositoryIntegrationTest extends AbstractRepositoryTestCase
{
    private DurationBillingOrderRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getEntityManager()->getRepository(DurationBillingOrder::class);
        self::assertInstanceOf(DurationBillingOrderRepository::class, $repository);
        $this->repository = $repository;
    }

    protected function createNewEntity(): object
    {
        return $this->createTestOrder();
    }

    /**
     * @return ServiceEntityRepository<DurationBillingOrder>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    public function testFindById(): void
    {
        // 创建测试数据
        $order = $this->createTestOrder();
        $this->persistAndFlush($order);

        // 测试查找
        $orderId = $order->getId();
        $this->assertNotNull($orderId, 'Order ID should not be null');

        $foundOrder = $this->repository->findById($orderId);

        // 验证结果
        $this->assertNotNull($foundOrder);
        $this->assertSame($order->getId(), $foundOrder->getId());
        $this->assertSame($order->getOrderCode(), $foundOrder->getOrderCode());

        // 测试查找不存在的ID
        $notFound = $this->repository->findById(99999);
        $this->assertNull($notFound);
    }

    public function testFindByOrderCode(): void
    {
        // 创建测试数据
        $order = $this->createTestOrder();
        $orderCode = 'TEST-ORDER-' . uniqid();
        $order->setOrderCode($orderCode);
        $this->persistAndFlush($order);

        // 测试根据订单号查找
        $foundOrder = $this->repository->findByOrderCode($orderCode);

        // 验证结果
        $this->assertNotNull($foundOrder);
        $this->assertSame($order->getId(), $foundOrder->getId());
        $this->assertSame($orderCode, $foundOrder->getOrderCode());

        // 测试查找不存在的订单号
        $notFound = $this->repository->findByOrderCode('NONEXISTENT');
        $this->assertNull($notFound);
    }

    public function testFindActiveOrdersByUser(): void
    {
        // 创建测试用户
        $user1 = $this->createNormalUser('user1@test.com', 'password');
        $user2 = $this->createNormalUser('user2@test.com', 'password');

        // 为用户1创建多个订单
        $activeOrder1 = $this->createTestOrderForUser($user1->getUserIdentifier(), OrderStatus::ACTIVE);
        $activeOrder2 = $this->createTestOrderForUser($user1->getUserIdentifier(), OrderStatus::ACTIVE);
        $frozenOrder = $this->createTestOrderForUser($user1->getUserIdentifier(), OrderStatus::FROZEN);
        $completedOrder = $this->createTestOrderForUser($user1->getUserIdentifier(), OrderStatus::COMPLETED);

        // 为用户2创建一个活跃订单
        $user2ActiveOrder = $this->createTestOrderForUser($user2->getUserIdentifier(), OrderStatus::ACTIVE);

        self::getEntityManager()->persist($activeOrder1);
        self::getEntityManager()->persist($activeOrder2);
        self::getEntityManager()->persist($frozenOrder);
        self::getEntityManager()->persist($completedOrder);
        self::getEntityManager()->persist($user2ActiveOrder);
        self::getEntityManager()->flush();

        // 查找用户1的活跃订单
        $user1ActiveOrders = $this->repository->findActiveOrdersByUser($user1->getUserIdentifier());

        // 验证结果
        $this->assertCount(3, $user1ActiveOrders); // ACTIVE(2) + FROZEN(1)
        $orderIds = array_map(fn ($order) => $order->getId(), $user1ActiveOrders);
        $this->assertContains($activeOrder1->getId(), $orderIds);
        $this->assertContains($activeOrder2->getId(), $orderIds);
        $this->assertContains($frozenOrder->getId(), $orderIds);
        $this->assertNotContains($completedOrder->getId(), $orderIds);

        // 查找用户2的活跃订单
        $user2ActiveOrders = $this->repository->findActiveOrdersByUser($user2->getUserIdentifier());
        $this->assertCount(1, $user2ActiveOrders);
        $this->assertSame($user2ActiveOrder->getId(), $user2ActiveOrders[0]->getId());

        // 查找不存在用户的订单
        $noUserOrders = $this->repository->findActiveOrdersByUser('nonexistent-user');
        $this->assertEmpty($noUserOrders);
    }

    public function testCountActiveOrders(): void
    {
        // 创建测试用户
        $user = $this->createNormalUser('count-user@test.com', 'password');
        $userId = $user->getUserIdentifier();

        // 创建不同状态的订单
        $activeOrder1 = $this->createTestOrderForUser($userId, OrderStatus::ACTIVE);
        $activeOrder2 = $this->createTestOrderForUser($userId, OrderStatus::ACTIVE);
        $frozenOrder = $this->createTestOrderForUser($userId, OrderStatus::FROZEN);
        $completedOrder = $this->createTestOrderForUser($userId, OrderStatus::COMPLETED);

        self::getEntityManager()->persist($activeOrder1);
        self::getEntityManager()->persist($activeOrder2);
        self::getEntityManager()->persist($frozenOrder);
        self::getEntityManager()->persist($completedOrder);
        self::getEntityManager()->flush();

        // 统计活跃订单数量（ACTIVE + FROZEN + PREPAID + PENDING_PAYMENT）
        $activeCount = $this->repository->countActiveOrders($userId);

        // 验证结果：2个ACTIVE + 1个FROZEN = 3个活跃订单
        $this->assertSame(3, $activeCount);

        // 测试不存在的用户
        $noUserCount = $this->repository->countActiveOrders('nonexistent-user');
        $this->assertSame(0, $noUserCount);
    }

    public function testFindByBusinessReference(): void
    {
        // 创建带业务引用的订单
        $user = $this->createNormalUser('business-user@test.com', 'password');
        $order = $this->createTestOrderForUser($user->getUserIdentifier(), OrderStatus::ACTIVE);
        $order->setMetadata([
            'business_type' => 'rental',
            'business_id' => 'RENTAL-001',
            'device_id' => 'DEVICE-123',
        ]);

        $this->persistAndFlush($order);

        // 根据业务引用查找订单
        $foundOrder = $this->repository->findByBusinessReference('rental', 'RENTAL-001');

        // 验证结果
        $this->assertNotNull($foundOrder);
        $this->assertSame($order->getId(), $foundOrder->getId());

        // 测试查找不存在的业务引用
        $notFound = $this->repository->findByBusinessReference('rental', 'NONEXISTENT');
        $this->assertNull($notFound);

        // 测试查找不同的业务类型
        $notFound2 = $this->repository->findByBusinessReference('other', 'RENTAL-001');
        $this->assertNull($notFound2);
    }

    public function testFindExpiredFrozenOrders(): void
    {
        // 创建冻结订单
        $user = $this->createNormalUser('frozen-user@test.com', 'password');
        $frozenOrder = $this->createTestOrderForUser($user->getUserIdentifier(), OrderStatus::FROZEN);

        $this->persistAndFlush($frozenOrder);

        // 查找过期的冻结订单（测试基本功能性）
        $expiredOrders = $this->repository->findExpiredFrozenOrders(60);

        // 验证基本功能：返回数组且不会抛出异常
        $this->assertIsArray($expiredOrders);

        // 查找更短时间内过期的订单
        $recentExpiredOrders = $this->repository->findExpiredFrozenOrders(5);
        $this->assertIsArray($recentExpiredOrders);
    }

    public function testFindOrdersToEnd(): void
    {
        // 创建活跃订单
        $user = $this->createNormalUser('end-user@test.com', 'password');
        $activeOrder = $this->createTestOrderForUser($user->getUserIdentifier(), OrderStatus::ACTIVE);

        $this->persistAndFlush($activeOrder);

        // 查找需要结束的订单（测试基本功能性）
        $cutoffTime = new \DateTimeImmutable('-1 hour');
        $ordersToEnd = $this->repository->findOrdersToEnd($cutoffTime);

        // 验证基本功能：返回数组且不会抛出异常
        $this->assertIsArray($ordersToEnd);

        // 查找更早的截止时间
        $earlierCutoffTime = new \DateTimeImmutable('-3 hours');
        $noOrdersToEnd = $this->repository->findOrdersToEnd($earlierCutoffTime);
        $this->assertIsArray($noOrdersToEnd);
    }

    // testFindOrdersByStatus 方法已删除 - 功能未实现，遵循 YAGNI 原则

    // testFindOrdersByUserAndStatus 方法已删除 - 功能未实现，遵循 YAGNI 原则

    // testFindOrdersByDateRange 方法已删除 - 功能未实现，遵循 YAGNI 原则

    // testCountOrdersByStatus 方法已删除 - 功能未实现，遵循 YAGNI 原则

    // testFindOrdersWithExpiredFreeze 方法已删除 - 功能未实现，遵循 YAGNI 原则

    private function createTestOrder(OrderStatus $status = OrderStatus::ACTIVE): DurationBillingOrder
    {
        // 直接使用字符串用户ID，避免创建用户实体
        $userId = 'test-user-' . uniqid();

        return $this->createTestOrderForUser($userId, $status);
    }

    private function createTestOrderForUser(string $userId, OrderStatus $status = OrderStatus::ACTIVE): DurationBillingOrder
    {
        // 创建测试产品
        $product = new DurationBillingProduct();
        $product->setName('测试产品 - ' . uniqid());
        $product->setDescription('用于测试的产品');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);
        $product->setActive(true);

        self::getEntityManager()->persist($product);

        // 创建订单
        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId($userId);
        $order->setOrderCode('TEST-' . uniqid());
        $order->setStartTime(new \DateTimeImmutable());
        $order->setStatus($status);

        if (OrderStatus::COMPLETED === $status) {
            $order->setEndTime(new \DateTimeImmutable());
            $order->setActualAmount(15.0);
        } elseif (OrderStatus::FROZEN === $status) {
            $order->setFrozenMinutes(30);
            $order->setActualAmount(10.0);
        } elseif (OrderStatus::PREPAID === $status) {
            $order->setPrepaidAmount(50.0);
        }

        return $order;
    }
}
