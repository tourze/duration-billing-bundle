<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Tests\Service;

use BizUserBundle\Entity\BizUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\Exception\InvalidOrderStateException;
use Tourze\DurationBillingBundle\Exception\OrderNotFoundException;
use Tourze\DurationBillingBundle\Exception\ProductNotFoundException;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\PricingRule\TieredPricingRule;
use Tourze\DurationBillingBundle\Service\DurationBillingService;
use Tourze\DurationBillingBundle\Service\DurationBillingServiceInterface;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 按时长计费服务集成测试
 *
 * @internal
 */
#[CoversClass(DurationBillingService::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingServiceIntegrationTest extends AbstractIntegrationTestCase
{
    private DurationBillingServiceInterface $billingService;

    protected function onSetUp(): void
    {
        $this->billingService = self::getService(DurationBillingServiceInterface::class);
    }

    public function testStartBillingWithBasicProduct(): void
    {
        // 创建计费产品
        $product = new DurationBillingProduct();
        $product->setName('基础计费');
        $product->setDescription('每小时10元的基础计费');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);
        $product->setActive(true);

        $this->persistAndFlush($product);

        // 创建测试用户
        $user = $this->createNormalUser('billing-user@test.com', 'password');

        // 类型安全：强制转换为 BizUser
        self::assertInstanceOf(BizUser::class, $user);

        // 开始计费
        $productId = $product->getId();
        $this->assertNotNull($productId, 'Product ID should not be null');

        $order = $this->billingService->startBilling($productId, (string) $user->getId());

        // 验证订单创建成功
        $this->assertInstanceOf(DurationBillingOrder::class, $order);
        $this->assertSame(OrderStatus::ACTIVE, $order->getStatus());
        $this->assertSame($product->getId(), $order->getProduct()->getId());
        $this->assertSame((string) $user->getId(), $order->getUserId());
        $this->assertNotNull($order->getOrderCode());
        $this->assertNotNull($order->getStartTime());
        $this->assertNull($order->getEndTime());
        $this->assertSame(0.0, $order->getPrepaidAmount());
    }

    public function testStartBillingWithPrepaidAmount(): void
    {
        // 创建计费产品
        $product = new DurationBillingProduct();
        $product->setName('预付费计费');
        $product->setDescription('支持预付费的计费产品');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 5.0,
            'rounding_mode' => 'nearest',
        ]);
        $product->setActive(true);

        $this->persistAndFlush($product);

        // 创建测试用户
        $user = $this->createNormalUser('prepaid-user@test.com', 'password');

        // 类型安全：强制转换为 BizUser
        self::assertInstanceOf(BizUser::class, $user);

        // 开始预付费计费
        $prepaidAmount = 20.0;
        $productId = $product->getId();
        $this->assertNotNull($productId, 'Product ID should not be null');

        $order = $this->billingService->startBilling(
            $productId,
            (string) $user->getId(),
            ['prepaid_amount' => $prepaidAmount]
        );

        // 验证预付费订单
        $this->assertSame(OrderStatus::PREPAID, $order->getStatus());
        $this->assertSame($prepaidAmount, $order->getPrepaidAmount());
    }

    public function testStartBillingWithMetadata(): void
    {
        // 创建计费产品
        $product = new DurationBillingProduct();
        $product->setName('带元数据的计费');
        $product->setDescription('支持元数据的计费产品');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 8.0,
            'rounding_mode' => 'down',
        ]);
        $product->setActive(true);

        $this->persistAndFlush($product);

        // 创建测试用户
        $user = $this->createNormalUser('metadata-user@test.com', 'password');

        // 类型安全：强制转换为 BizUser
        self::assertInstanceOf(BizUser::class, $user);

        // 带元数据开始计费
        $metadata = [
            'device_id' => 'DEV-001',
            'location' => '北京朝阳区',
            'session_type' => 'premium',
        ];
        $productId = $product->getId();
        $this->assertNotNull($productId, 'Product ID should not be null');

        $order = $this->billingService->startBilling(
            $productId,
            (string) $user->getId(),
            ['metadata' => $metadata]
        );

        // 验证元数据
        $this->assertSame($metadata, $order->getMetadata());
    }

    public function testStartBillingThrowsExceptionWhenProductNotFound(): void
    {
        $user = $this->createNormalUser('test-user@test.com', 'password');

        // 类型安全：强制转换为 BizUser
        self::assertInstanceOf(BizUser::class, $user);

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessage('Product with ID 99999 not found');

        $this->billingService->startBilling(99999, (string) $user->getId());
    }

    public function testFreezeBillingFlow(): void
    {
        // 准备测试数据
        [$order, $product] = $this->createActiveOrder();

        // 手动设置开始时间为2分钟前，模拟2分钟的使用时长
        $startTime = new \DateTimeImmutable('-2 minutes');
        $order->setStartTime($startTime);
        $this->persistAndFlush($order);

        // 冻结计费
        $orderId = $order->getId();
        $this->assertNotNull($orderId, 'Order ID should not be null');

        $frozenOrder = $this->billingService->freezeBilling($orderId);

        // 验证冻结状态
        $this->assertSame(OrderStatus::FROZEN, $frozenOrder->getStatus());
        $this->assertGreaterThan(0, $frozenOrder->getActualAmount());
        $this->assertSame($order->getId(), $frozenOrder->getId());

        // 验证价格计算正确性
        $this->assertGreaterThanOrEqual(0, $frozenOrder->getActualAmount());
    }

    public function testResumeBillingFlow(): void
    {
        // 准备测试数据
        [$order, $product] = $this->createActiveOrder();

        // 先冻结订单
        $orderId = $order->getId();
        $this->assertNotNull($orderId, 'Order ID should not be null');

        $frozenOrder = $this->billingService->freezeBilling($orderId);
        $this->assertSame(OrderStatus::FROZEN, $frozenOrder->getStatus());

        // 恢复计费
        $frozenOrderId = $frozenOrder->getId();
        $this->assertNotNull($frozenOrderId, 'Frozen order ID should not be null');

        $resumedOrder = $this->billingService->resumeBilling($frozenOrderId);

        // 验证恢复状态
        $this->assertSame(OrderStatus::ACTIVE, $resumedOrder->getStatus());
        $this->assertSame($frozenOrder->getId(), $resumedOrder->getId());
    }

    public function testEndBillingFlow(): void
    {
        // 准备测试数据
        [$order, $product] = $this->createActiveOrder();

        // 手动设置开始时间为5分钟前，模拟5分钟的使用时长
        $startTime = new \DateTimeImmutable('-5 minutes');
        $order->setStartTime($startTime);
        $this->persistAndFlush($order);

        // 结束计费
        $orderId = $order->getId();
        $this->assertNotNull($orderId, 'Order ID should not be null');

        $result = $this->billingService->endBilling($orderId);

        // 验证返回结果结构
        $this->assertIsArray($result);
        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('price', $result);

        $completedOrder = $result['order'];
        self::assertInstanceOf(DurationBillingOrder::class, $completedOrder);
        $priceResult = $result['price'];
        self::assertInstanceOf(PriceResult::class, $priceResult);

        // 验证订单完成状态
        $this->assertSame(OrderStatus::COMPLETED, $completedOrder->getStatus());
        $this->assertNotNull($completedOrder->getEndTime());
        $this->assertGreaterThan(0, $completedOrder->getActualAmount());

        // 验证价格结果
        $this->assertInstanceOf(PriceResult::class, $priceResult);
        $this->assertGreaterThan(0, $priceResult->finalPrice);
        $this->assertGreaterThan(0, $priceResult->billableMinutes);
        $this->assertSame($completedOrder->getActualAmount(), $priceResult->finalPrice);
    }

    public function testEndBillingWithRefundScenario(): void
    {
        // 创建计费产品
        $product = new DurationBillingProduct();
        $product->setName('退款测试产品');
        $product->setDescription('用于测试退款场景');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);
        $product->setActive(true);

        $this->persistAndFlush($product);

        // 创建测试用户
        $user = $this->createNormalUser('refund-user@test.com', 'password');

        // 类型安全：强制转换为 BizUser
        self::assertInstanceOf(BizUser::class, $user);

        // 开始预付费计费（预付100元）
        $productId = $product->getId();
        $this->assertNotNull($productId, 'Product ID should not be null');

        $order = $this->billingService->startBilling(
            $productId,
            (string) $user->getId(),
            ['prepaid_amount' => 100.0]
        );

        $this->assertSame(OrderStatus::PREPAID, $order->getStatus());

        // 手动设置开始时间为1分钟前，模拟短时间使用（应该产生退款）
        $startTime = new \DateTimeImmutable('-1 minutes');
        $order->setStartTime($startTime);
        $this->persistAndFlush($order);

        $orderId = $order->getId();
        $this->assertNotNull($orderId, 'Order ID should not be null');

        $result = $this->billingService->endBilling($orderId);

        $completedOrder = $result['order'];
        self::assertInstanceOf(DurationBillingOrder::class, $completedOrder);
        $priceResult = $result['price'];
        self::assertInstanceOf(PriceResult::class, $priceResult);

        // 验证实际金额小于预付金额（应该有退款）
        $this->assertLessThan(100.0, $completedOrder->getActualAmount());
        $this->assertSame(OrderStatus::COMPLETED, $completedOrder->getStatus());
    }

    public function testGetCurrentPrice(): void
    {
        // 准备测试数据
        [$order, $product] = $this->createActiveOrder();

        // 手动设置开始时间为3分钟前，模拟3分钟的使用时长
        $startTime = new \DateTimeImmutable('-3 minutes');
        $order->setStartTime($startTime);
        $this->persistAndFlush($order);

        // 获取当前价格
        $orderId = $order->getId();
        $this->assertNotNull($orderId, 'Order ID should not be null');

        $priceResult = $this->billingService->getCurrentPrice($orderId);

        // 验证价格结果
        $this->assertInstanceOf(PriceResult::class, $priceResult);
        $this->assertGreaterThan(0, $priceResult->finalPrice);
        $this->assertGreaterThan(0, $priceResult->billableMinutes);
        $this->assertIsArray($priceResult->breakdown);
    }

    public function testFindActiveOrders(): void
    {
        // 创建多个用户和订单
        $user1 = $this->createNormalUser('user1@test.com', 'password');
        $user2 = $this->createNormalUser('user2@test.com', 'password');

        // 类型安全：强制转换为 BizUser
        self::assertInstanceOf(BizUser::class, $user1);
        self::assertInstanceOf(BizUser::class, $user2);

        // 为用户1创建两个活跃订单
        [$order1] = $this->createActiveOrderForUser((string) $user1->getId());
        [$order2] = $this->createActiveOrderForUser((string) $user1->getId());

        // 为用户2创建一个活跃订单
        [$order3] = $this->createActiveOrderForUser((string) $user2->getId());

        // 查找用户1的活跃订单
        $user1ActiveOrders = $this->billingService->findActiveOrders((string) $user1->getId());

        // 验证结果
        $this->assertCount(2, $user1ActiveOrders);
        $orderIds = array_map(fn ($order) => $order->getId(), $user1ActiveOrders);
        $this->assertContains($order1->getId(), $orderIds);
        $this->assertContains($order2->getId(), $orderIds);

        // 查找用户2的活跃订单
        $user2ActiveOrders = $this->billingService->findActiveOrders((string) $user2->getId());
        $this->assertCount(1, $user2ActiveOrders);
        $this->assertSame($order3->getId(), $user2ActiveOrders[0]->getId());
    }

    public function testFindOrderByCode(): void
    {
        // 准备测试数据
        [$order, $product] = $this->createActiveOrder();
        $orderCode = $order->getOrderCode();

        // 根据订单号查找订单
        $foundOrder = $this->billingService->findOrderByCode($orderCode);

        // 验证结果
        $this->assertNotNull($foundOrder);
        $this->assertSame($order->getId(), $foundOrder->getId());
        $this->assertSame($orderCode, $foundOrder->getOrderCode());

        // 测试查找不存在的订单
        $notFoundOrder = $this->billingService->findOrderByCode('NONEXISTENT');
        $this->assertNull($notFoundOrder);
    }

    public function testTieredPricingIntegration(): void
    {
        // 创建阶梯定价产品
        $product = new DurationBillingProduct();
        $product->setName('阶梯定价产品');
        $product->setDescription('使用阶梯定价规则');
        $product->setPricingRuleData([
            'class' => TieredPricingRule::class,
            'tiers' => [
                ['start_minutes' => 0, 'end_minutes' => 60, 'price_per_hour' => 5.0],    // 第一小时5元/小时
                ['start_minutes' => 60, 'end_minutes' => 120, 'price_per_hour' => 8.0],  // 第二小时8元/小时
                ['start_minutes' => 120, 'end_minutes' => null, 'price_per_hour' => 10.0], // 后续时间10元/小时
            ],
        ]);
        $product->setActive(true);

        $this->persistAndFlush($product);

        // 创建测试用户
        $user = $this->createNormalUser('tiered-user@test.com', 'password');

        // 类型安全：强制转换为 BizUser
        self::assertInstanceOf(BizUser::class, $user);

        // 开始计费
        $productId = $product->getId();
        $this->assertNotNull($productId, 'Product ID should not be null');

        $order = $this->billingService->startBilling($productId, (string) $user->getId());

        // 手动设置开始时间为90分钟前，确保跨越多个价格层级
        $startTime = new \DateTimeImmutable('-90 minutes');
        $order->setStartTime($startTime);
        $this->persistAndFlush($order);

        // 获取当前价格
        $orderId = $order->getId();
        $this->assertNotNull($orderId, 'Order ID should not be null');

        $priceResult = $this->billingService->getCurrentPrice($orderId);

        // 验证阶梯定价正确应用
        $this->assertInstanceOf(PriceResult::class, $priceResult);
        $this->assertGreaterThan(0, $priceResult->finalPrice);
        $this->assertSame(90, $priceResult->billableMinutes);
        $this->assertIsArray($priceResult->breakdown);

        // 90分钟：前60分钟5元/小时 + 后30分钟8元/小时 = 5 + 4 = 9元
        $expectedPrice = 5.0 + (30.0 / 60.0 * 8.0);
        $this->assertEqualsWithDelta($expectedPrice, $priceResult->finalPrice, 0.01);
    }

    public function testFreezeBillingThrowsExceptionWhenOrderNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);
        $this->expectExceptionMessage('Order with ID 99999 not found');

        $this->billingService->freezeBilling(99999);
    }

    public function testFreezeBillingThrowsExceptionWhenInvalidState(): void
    {
        // 创建并完成一个订单
        [$order, $product] = $this->createActiveOrder();
        $orderId = $order->getId();
        $this->assertNotNull($orderId, 'Order ID should not be null');

        $this->billingService->endBilling($orderId);

        // 尝试冻结已完成的订单
        $this->expectException(InvalidOrderStateException::class);

        $this->billingService->freezeBilling($orderId);
    }

    /**
     * 创建一个活跃的订单用于测试
     *
     * @return array{DurationBillingOrder, DurationBillingProduct}
     */
    private function createActiveOrder(): array
    {
        $user = $this->createNormalUser('test-user-' . uniqid() . '@test.com', 'password');

        // 类型安全：强制转换为 BizUser
        self::assertInstanceOf(BizUser::class, $user);

        return $this->createActiveOrderForUser((string) $user->getId());
    }

    /**
     * 为指定用户创建一个活跃的订单
     *
     * @param string $userId
     * @return array{DurationBillingOrder, DurationBillingProduct}
     */
    private function createActiveOrderForUser(string $userId): array
    {
        // 创建计费产品
        $product = new DurationBillingProduct();
        $product->setName('测试计费产品');
        $product->setDescription('用于集成测试的计费产品');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);
        $product->setActive(true);

        $this->persistAndFlush($product);

        // 开始计费
        $productId = $product->getId();
        $this->assertNotNull($productId, 'Product ID should not be null');

        $order = $this->billingService->startBilling($productId, $userId);

        return [$order, $product];
    }
}
