<?php

namespace Tourze\DurationBillingBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingProduct::class)]
final class DurationBillingProductTest extends AbstractEntityTestCase
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
        $product->setDescription('产品描述');
        $product->setFreeMinutes(30);
        $product->setFreezeMinutes(15);
        $product->setMinAmount(10.0);
        $product->setMaxAmount(100.0);
        $product->setEnabled(true);
        $product->setMetadata(['key' => 'value']);

        $this->assertSame('测试产品', $product->getName());
        $this->assertSame('产品描述', $product->getDescription());
        $this->assertSame(30, $product->getFreeMinutes());
        $this->assertSame(15, $product->getFreezeMinutes());
        $this->assertSame(10.0, $product->getMinAmount());
        $this->assertSame(100.0, $product->getMaxAmount());
        $this->assertTrue($product->isEnabled());
        $this->assertSame(['key' => 'value'], $product->getMetadata());
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

        return $product;
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'name' => ['name', '测试产品名称'];
        yield 'description' => ['description', '产品描述'];
        yield 'freeMinutes' => ['freeMinutes', 30];
        yield 'freezeMinutes' => ['freezeMinutes', 15];
        yield 'minAmount' => ['minAmount', 10.0];
        yield 'maxAmount' => ['maxAmount', 100.0];
        yield 'enabled' => ['enabled', true];
        yield 'metadata' => ['metadata', ['key' => 'value']];
    }

    public function testPricingRuleDataManagement(): void
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

        $this->assertSame([
            'type' => 'hourly',
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ], $product->getPricingRuleData());
    }

    public function testOptionalProperties(): void
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

        // 测试可选属性的默认值
        $this->assertNull($product->getDescription());
        $this->assertSame(0, $product->getFreeMinutes());
        $this->assertNull($product->getFreezeMinutes());
        $this->assertNull($product->getMinAmount());
        $this->assertNull($product->getMaxAmount());
        $this->assertTrue($product->isEnabled());
        $this->assertSame([], $product->getMetadata());
    }
}
