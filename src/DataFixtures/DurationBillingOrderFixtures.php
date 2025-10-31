<?php

namespace Tourze\DurationBillingBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;

class DurationBillingOrderFixtures extends Fixture implements DependentFixtureInterface
{
    public const ORDER_1_REFERENCE = 'order-1';
    public const ORDER_2_REFERENCE = 'order-2';

    public function load(ObjectManager $manager): void
    {
        $product = $this->getReference(DurationBillingProductFixtures::PRODUCT_1_REFERENCE, DurationBillingProduct::class);

        $order1 = new DurationBillingOrder();
        $order1->setProduct($product);
        $order1->setUserId('user-123');
        $order1->setOrderCode('ORDER-001');
        $order1->setStatus(OrderStatus::ACTIVE);
        $order1->setStartTime(new \DateTimeImmutable('-1 hour'));
        $order1->setPrepaidAmount(50.00);

        $order2 = new DurationBillingOrder();
        $order2->setProduct($product);
        $order2->setUserId('user-456');
        $order2->setOrderCode('ORDER-002');
        $order2->setStatus(OrderStatus::COMPLETED);
        $order2->setStartTime(new \DateTimeImmutable('-2 hours'));
        $order2->setEndTime(new \DateTimeImmutable('-1 hour'));
        $order2->setPrepaidAmount(30.00);
        $order2->setActualAmount(45.00);

        $manager->persist($order1);
        $manager->persist($order2);
        $manager->flush();

        $this->addReference(self::ORDER_1_REFERENCE, $order1);
        $this->addReference(self::ORDER_2_REFERENCE, $order2);
    }

    public function getDependencies(): array
    {
        return [
            DurationBillingProductFixtures::class,
        ];
    }
}
