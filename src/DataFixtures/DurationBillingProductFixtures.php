<?php

namespace Tourze\DurationBillingBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\RoundingMode;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;

class DurationBillingProductFixtures extends Fixture
{
    public const PRODUCT_1_REFERENCE = 'product-1';
    public const PRODUCT_2_REFERENCE = 'product-2';

    public function load(ObjectManager $manager): void
    {
        $product1 = new DurationBillingProduct();
        $product1->setName('小时计费产品');
        $product1->setDescription('按小时计费的标准产品');
        $product1->setFreeMinutes(10);
        $product1->setFreezeMinutes(5);
        $product1->setMinAmount(5.00);
        $product1->setMaxAmount(100.00);
        $product1->setEnabled(true);

        $hourlyRule = new HourlyPricingRule(15.00, RoundingMode::UP);
        $product1->setPricingRule($hourlyRule);

        $product2 = new DurationBillingProduct();
        $product2->setName('基础计费产品');
        $product2->setDescription('基础计费产品，无最低消费');
        $product2->setFreeMinutes(5);
        $product2->setEnabled(true);

        $basicRule = new HourlyPricingRule(10.00, RoundingMode::NEAREST);
        $product2->setPricingRule($basicRule);

        $manager->persist($product1);
        $manager->persist($product2);
        $manager->flush();

        $this->addReference(self::PRODUCT_1_REFERENCE, $product1);
        $this->addReference(self::PRODUCT_2_REFERENCE, $product2);
    }
}
