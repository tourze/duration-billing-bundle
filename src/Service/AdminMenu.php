<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Service;

use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

#[Autoconfigure(public: true)]
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(private LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('计费管理')) {
            $item->addChild('计费管理');
        }

        $billingMenu = $item->getChild('计费管理');

        if (null !== $billingMenu) {
            $billingMenu
                ->addChild('计费产品')
                ->setUri($this->linkGenerator->getCurdListPage(DurationBillingProduct::class))
                ->setAttribute('icon', 'fas fa-cubes')
            ;

            $billingMenu
                ->addChild('计费订单')
                ->setUri($this->linkGenerator->getCurdListPage(DurationBillingOrder::class))
                ->setAttribute('icon', 'fas fa-receipt')
            ;
        }
    }
}
