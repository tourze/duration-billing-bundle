<?php

namespace Tourze\DurationBillingBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\RoundingMode;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\PricingRule\TieredPricingRule;
use Tourze\DurationBillingBundle\ValueObject\PriceTier;

/**
 * @codeCoverageIgnore
 */
class DurationBillingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 创建按小时计费的产品
        $hourlyProduct = new DurationBillingProduct();
        $hourlyProduct->setName('标准会议室');
        $hourlyProduct->setDescription('可容纳10人的标准会议室');
        $hourlyProduct->setEnabled(true);
        $hourlyProduct->setFreeMinutes(15);
        $hourlyProduct->setMinAmount(50.0);
        $hourlyProduct->setMaxAmount(800.0);

        $hourlyRule = new HourlyPricingRule(100.0, RoundingMode::UP);
        $hourlyProduct->setPricingRule($hourlyRule);

        $manager->persist($hourlyProduct);

        // 创建阶梯计费的产品
        $tieredProduct = new DurationBillingProduct();
        $tieredProduct->setName('共享汽车');
        $tieredProduct->setDescription('经济型共享汽车');
        $tieredProduct->setEnabled(true);
        $tieredProduct->setFreeMinutes(5);
        $tieredProduct->setMinAmount(15.0);
        $tieredProduct->setMaxAmount(300.0);

        $tiers = [
            new PriceTier(0, 30, 30.0),
            new PriceTier(30, 120, 25.0),
            new PriceTier(120, 360, 20.0),
            new PriceTier(360, null, 15.0),
        ];

        $tieredRule = new TieredPricingRule($tiers);
        $tieredProduct->setPricingRule($tieredRule);

        $manager->persist($tieredProduct);

        $manager->flush();
    }
}
