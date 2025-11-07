<?php

namespace Tourze\DurationBillingBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Service\PriceCalculator;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PriceCalculator::class)]
#[RunTestsInSeparateProcesses] final class PriceCalculatorTest extends AbstractIntegrationTestCase
{
    private PriceCalculator $calculator;

    public function testCalculateWithZeroMinutes(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        $product->setFreeMinutes(30);

        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->never())
            ->method('calculatePrice')
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 0);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertSame(0.0, $result->basePrice);
        $this->assertSame(0.0, $result->finalPrice);
        $this->assertSame(0.0, $result->getDiscount());
        $this->assertSame(0, $result->billableMinutes);
        $this->assertEmpty($result->breakdown);
    }

    public function testCalculateWithinFreeMinutes(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        $product->setFreeMinutes(30);

        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->never())
            ->method('calculatePrice')
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 25);

        $this->assertSame(0.0, $result->basePrice);
        $this->assertSame(0.0, $result->finalPrice);
        $this->assertSame(0.0, $result->getDiscount());
        $this->assertSame(0, $result->billableMinutes);
        $this->assertSame('免费时长', $result->breakdown['adjustment_reason']);
    }

    public function testCalculateExceedingFreeMinutes(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        $product->setFreeMinutes(30);

        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->once())
            ->method('calculatePrice')
            ->with(30) // 60 - 30 = 30 minutes billed
            ->willReturn(15.0)
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 60);

        $this->assertSame(15.0, $result->basePrice);
        $this->assertSame(15.0, $result->finalPrice);
        $this->assertSame(0.0, $result->getDiscount());
        $this->assertSame(30, $result->billableMinutes);
        $this->assertEmpty($result->breakdown);
    }

    public function testCalculateWithMinimumAmount(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        $product->setMinAmount(10.0);

        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->once())
            ->method('calculatePrice')
            ->with(30)
            ->willReturn(5.0)
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 30);

        $this->assertSame(5.0, $result->basePrice);
        $this->assertSame(10.0, $result->finalPrice);
        $this->assertSame(-5.0, $result->getDiscount());
        $this->assertSame(30, $result->billableMinutes);
        $this->assertSame('最低消费', $result->breakdown['adjustment_reason']);
    }

    public function testCalculateWithMaximumAmount(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        $product->setMaxAmount(50.0);

        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->once())
            ->method('calculatePrice')
            ->with(300)
            ->willReturn(75.0)
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 300);

        $this->assertSame(75.0, $result->basePrice);
        $this->assertSame(50.0, $result->finalPrice);
        $this->assertSame(25.0, $result->getDiscount());
        $this->assertSame(300, $result->billableMinutes);
        $this->assertSame('最高限价', $result->breakdown['adjustment_reason']);
    }

    public function testCalculateWithAllConstraints(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        $product->setFreeMinutes(30);
        $product->setMinAmount(20.0);
        $product->setMaxAmount(100.0);

        // Case 1: Within free minutes
        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->never())
            ->method('calculatePrice')
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 20);
        $this->assertSame(0.0, $result->finalPrice);
        $this->assertSame('免费时长', $result->breakdown['adjustment_reason']);

        // Case 2: Below minimum after free minutes
        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->once())
            ->method('calculatePrice')
            ->with(30) // 60 - 30 free minutes
            ->willReturn(15.0)
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 60);
        $this->assertSame(20.0, $result->finalPrice);
        $this->assertSame('最低消费', $result->breakdown['adjustment_reason']);

        // Case 3: Above maximum
        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->once())
            ->method('calculatePrice')
            ->with(270) // 300 - 30 free minutes
            ->willReturn(135.0)
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 300);
        $this->assertSame(100.0, $result->finalPrice);
        $this->assertSame('最高限价', $result->breakdown['adjustment_reason']);
    }

    public function testCalculateWithNoConstraints(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        // No free minutes, min or max amount

        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->once())
            ->method('calculatePrice')
            ->with(60)
            ->willReturn(30.0)
        ;

        $result = $this->calculator->calculate($product, $pricingRule, 60);

        $this->assertSame(30.0, $result->basePrice);
        $this->assertSame(30.0, $result->finalPrice);
        $this->assertSame(0.0, $result->getDiscount());
        $this->assertSame(60, $result->billableMinutes);
        $this->assertEmpty($result->breakdown);
    }

    public function testGetPriceDetails(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        $product->setFreeMinutes(30);
        $product->setMinAmount(20.0);
        $product->setMaxAmount(100.0);

        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->once())
            ->method('calculatePrice')
            ->with(30)
            ->willReturn(15.0)
        ;
        $pricingRule->expects($this->once())
            ->method('getDescription')
            ->willReturn('按小时计费')
        ;

        $details = $this->calculator->getPriceDetails($product, $pricingRule, 60);

        $this->assertIsArray($details);
        /**
         * @var array{
         *     total_minutes: int,
         *     free_minutes: int,
         *     billed_minutes: int,
         *     base_price: float,
         *     final_price: float,
         *     adjustment_reason: string|null,
         *     pricing_rule_description: string,
         *     product_constraints: array{free_minutes: int, min_amount: float|null, max_amount: float|null}
         * } $details
         */
        $this->assertSame(60, $details['total_minutes']);
        $this->assertSame(30, $details['free_minutes']);
        $this->assertSame(30, $details['billed_minutes']);
        $this->assertSame(15.0, $details['base_price']);
        $this->assertSame(20.0, $details['final_price']);
        $this->assertSame('最低消费', $details['adjustment_reason']);
        $this->assertSame('按小时计费', $details['pricing_rule_description']);
        $this->assertArrayHasKey('product_constraints', $details);

        $constraints = $details['product_constraints'];
        $this->assertIsArray($constraints);
        $this->assertSame(30, $constraints['free_minutes']);
        $this->assertSame(20.0, $constraints['min_amount']);
        $this->assertSame(100.0, $constraints['max_amount']);
    }

    public function testGetPriceDetailsWithNullConstraints(): void
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product');
        $product->setDescription('Test');
        // All constraints are null

        $pricingRule = $this->createMock(PricingRuleInterface::class);
        $pricingRule->expects($this->once())
            ->method('calculatePrice')
            ->with(60)
            ->willReturn(30.0)
        ;
        $pricingRule->expects($this->once())
            ->method('getDescription')
            ->willReturn('标准计费')
        ;

        $details = $this->calculator->getPriceDetails($product, $pricingRule, 60);

        $this->assertIsArray($details);
        /**
         * @var array{
         *     adjustment_reason: string|null,
         *     product_constraints: array{free_minutes: int, min_amount: float|null, max_amount: float|null}
         * } $details
         */
        $constraints = $details['product_constraints'];
        $this->assertIsArray($constraints);
        $this->assertSame(0, $constraints['free_minutes']);
        $this->assertNull($constraints['min_amount']);
        $this->assertNull($constraints['max_amount']);
        $this->assertNull($details['adjustment_reason']);
    }

    protected function onSetUp(): void
    {
        // 使用服务容器获取服务实例，避免直接实例化
        $this->calculator = self::getService(PriceCalculator::class);
    }
}
