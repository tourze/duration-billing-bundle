<?php

namespace Tourze\DurationBillingBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingOrder::class)]
final class DurationBillingOrderTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
    }

    public function testSetBasicProperties(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);

        $this->assertSame('test-user', $order->getUserId());
        $this->assertSame('TEST-001', $order->getOrderCode());
        $this->assertSame(OrderStatus::ACTIVE, $order->getStatus());
        $this->assertSame(100.0, $order->getPrepaidAmount());
    }

    protected function createEntity(): object
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);

        return $order;
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'userId' => ['userId', 'test-user'];
        yield 'orderCode' => ['orderCode', 'TEST-001'];
        yield 'startTime' => ['startTime', new \DateTimeImmutable('2024-01-20 10:00:00')];
        yield 'endTime' => ['endTime', new \DateTimeImmutable('2024-01-20 12:00:00')];
        yield 'status' => ['status', OrderStatus::ACTIVE];
        yield 'prepaidAmount' => ['prepaidAmount', 100.0];
        yield 'actualAmount' => ['actualAmount', 80.0];
        yield 'frozenMinutes' => ['frozenMinutes', 30];
        yield 'metadata' => ['metadata', ['device_id' => 'DEV-123']];
    }

    public function testGetActualBillingMinutesWithoutEndTime(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);

        $this->assertSame(0, $order->getActualBillingMinutes());
    }

    public function testGetActualBillingMinutesWithEndTime(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');
        $product->setFreezeMinutes(15);

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setEndTime(new \DateTimeImmutable('2024-01-20 12:30:00'));
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);

        // 150分钟 - 15分钟冻结时间 = 135分钟
        $this->assertSame(135, $order->getActualBillingMinutes());
    }

    public function testGetActualBillingMinutesWithFrozenTime(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setEndTime(new \DateTimeImmutable('2024-01-20 12:00:00'));
        $order->setFrozenMinutes(30);
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);

        // 120分钟 - 30分钟冻结时间 = 90分钟
        $this->assertSame(90, $order->getActualBillingMinutes());
    }

    public function testGetActualBillingMinutesNeverNegative(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setEndTime(new \DateTimeImmutable('2024-01-20 10:10:00'));
        $order->setFrozenMinutes(30);
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);

        // 10分钟 - 30分钟冻结时间 = -20分钟，应该返回0
        $this->assertSame(0, $order->getActualBillingMinutes());
    }

    public function testGetRefundAmountWithNoActualAmount(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);

        $this->assertSame(0.0, $order->getRefundAmount());
    }

    public function testGetRefundAmountWhenPrepaidGreaterThanActual(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);
        $order->setActualAmount(70.0);

        $this->assertSame(30.0, $order->getRefundAmount());
    }

    public function testRequiresAdditionalPaymentWhenActualGreaterThanPrepaid(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(50.0);
        $order->setActualAmount(80.0);

        $this->assertTrue($order->requiresAdditionalPayment());
    }

    public function testMetadataManagement(): void
    {
        $product = new DurationBillingProduct();

        // 使用反射设置必需的属性
        $reflection = new \ReflectionClass($product);
        $pricingRuleDataProperty = $reflection->getProperty('pricingRuleData');
        $pricingRuleDataProperty->setAccessible(true);
        $pricingRuleDataProperty->setValue($product, [
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);

        $product->setName('测试产品');

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-001');
        $order->setStartTime(new \DateTimeImmutable('2024-01-20 10:00:00'));
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setPrepaidAmount(100.0);

        $metadata = [
            'device_id' => 'DEV-123',
            'location' => '北京市朝阳区',
            'extra' => ['key' => 'value'],
        ];

        $order->setMetadata($metadata);
        $this->assertSame($metadata, $order->getMetadata());
    }
}
