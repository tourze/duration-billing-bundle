<?php

namespace Tourze\DurationBillingBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Contract\DurationBillingOrderRepositoryInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Repository\DurationBillingOrderRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingOrderRepository::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingOrderRepositoryInterfaceTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
    }

    protected function getRepository(): DurationBillingOrderRepository
    {
        return self::getService(DurationBillingOrderRepository::class);
    }

    protected function createNewEntity(): object
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setPricingRuleData(['class' => 'TestRule', 'data' => []]);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user-123');
        $order->setOrderCode('ORDER-' . uniqid());
        $order->setStartTime(new \DateTimeImmutable());
        $order->setPrepaidAmount(100.0);

        return $order;
    }

    public function testOrderRepositoryInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(DurationBillingOrderRepositoryInterface::class));
    }

    public function testRepositoryImplementsInterface(): void
    {
        // 测试Repository实现了正确的接口
        $repository = $this->getRepository();
        $this->assertInstanceOf(DurationBillingOrderRepositoryInterface::class, $repository);
    }

    /**
     * 测试 countActiveOrders 方法接口定义
     */
    public function testCountActiveOrders(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $count = $repository->countActiveOrders('test-user');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * 测试 findActiveOrdersByUser 方法接口定义
     */
    public function testFindActiveOrdersByUser(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $orders = $repository->findActiveOrdersByUser('test-user');
        $this->assertIsArray($orders);
    }

    /**
     * 测试 findByBusinessReference 方法接口定义
     */
    public function testFindByBusinessReference(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $order = $repository->findByBusinessReference('test-type', 'test-id');
        $this->assertNull($order); // 没有数据时返回null
    }

    /**
     * 测试 findExpiredFrozenOrders 方法接口定义
     */
    public function testFindExpiredFrozenOrders(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $orders = $repository->findExpiredFrozenOrders(60);
        $this->assertIsArray($orders);
    }

    /**
     * 测试 findOrdersToEnd 方法接口定义
     */
    public function testFindOrdersToEnd(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $cutoffTime = new \DateTimeImmutable('-1 hour');
        $orders = $repository->findOrdersToEnd($cutoffTime);
        $this->assertIsArray($orders);
    }

    /**
     * 测试 findById 方法接口定义
     */
    public function testFindById(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $order = $repository->findById(99999);
        $this->assertNull($order); // 不存在的ID返回null
    }

    /**
     * 测试 findByOrderCode 方法接口定义
     */
    public function testFindByOrderCode(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $order = $repository->findByOrderCode('NONEXISTENT-ORDER');
        $this->assertNull($order); // 不存在的订单编号返回null
    }
}
