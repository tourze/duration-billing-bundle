<?php

namespace Tourze\DurationBillingBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\DurationBillingBundle\Contract\DurationBillingProductRepositoryInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<DurationBillingProduct>
 */
#[AsRepository(entityClass: DurationBillingProduct::class)]
class DurationBillingProductRepository extends ServiceEntityRepository implements DurationBillingProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DurationBillingProduct::class);
    }

    public function findById($id): ?DurationBillingProduct
    {
        return $this->find($id);
    }

    /**
     * @return DurationBillingProduct[]
     */
    public function findEnabledProducts(): array
    {
        $result = $this->createQueryBuilder('p')
            ->where('p.enabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        /** @var mixed[] $result */
        return array_filter($result, fn ($item): bool => $item instanceof DurationBillingProduct);
    }

    public function findByName(string $name): ?DurationBillingProduct
    {
        $result = $this->createQueryBuilder('p')
            ->where('p.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof DurationBillingProduct ? $result : null;
    }

    public function save(DurationBillingProduct $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DurationBillingProduct $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
