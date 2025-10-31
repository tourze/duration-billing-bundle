<?php

namespace Tourze\DurationBillingBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\RoundingMode;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Repository\DurationBillingProductRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingProductRepository::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingProductRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Test setup is handled in individual test methods
    }

    public function testFindById(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $productId = $product->getId();
        $this->assertNotNull($productId);
        $found = $repository->findById($productId);

        $this->assertNotNull($found);
        $this->assertEquals($product->getId(), $found->getId());

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testFindByName(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct('Unique Product Name');
        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $found = $repository->findByName('Unique Product Name');

        $this->assertNotNull($found);
        $this->assertEquals('Unique Product Name', $found->getName());

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testFindEnabledProducts(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('Product 1', true);
        $product2 = $this->createProduct('Product 2', false);
        $product3 = $this->createProduct('Product 3', true);

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->persist($product3);
        self::getEntityManager()->flush();

        $enabledProducts = $repository->findEnabledProducts();

        // 应该只找到启用的产品
        $enabledIds = array_map(fn ($p) => $p->getId(), $enabledProducts);
        $this->assertContains($product1->getId(), $enabledIds);
        $this->assertContains($product3->getId(), $enabledIds);
        $this->assertNotContains($product2->getId(), $enabledIds);

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->remove($product3);
        self::getEntityManager()->flush();
    }

    public function testFind(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $productId = $product->getId();
        $found = $repository->find($productId);
        $this->assertNotNull($found);
        $this->assertEquals($productId, $found->getId());

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testFindNotFound(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $found = $repository->find(999999999);
        $this->assertNull($found);
    }

    public function testFindAll(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('Product 1');
        $product2 = $this->createProduct('Product 2');

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->flush();

        $all = $repository->findAll();
        $this->assertIsArray($all);
        $this->assertGreaterThanOrEqual(2, count($all));

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->flush();
    }

    public function testFindByEnabled(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct('Enabled Product', true);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $found = $repository->findBy(['enabled' => true]);
        $this->assertNotEmpty($found);
        $foundProduct = array_filter($found, fn ($p) => $p->getId() === $product->getId());
        $this->assertNotEmpty($foundProduct);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testFindByNullableFields(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        $product->setDescription(null);
        $product->setFreezeMinutes(null);
        $product->setMinAmount(null);
        $product->setMaxAmount(null);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $found = $repository->findBy(['description' => null]);
        $this->assertNotEmpty($found);
        $foundProduct = array_filter($found, fn ($p) => $p->getId() === $product->getId());
        $this->assertNotEmpty($foundProduct);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testFindByFreeMinutes(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        $product->setFreeMinutes(30);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $found = $repository->findBy(['freeMinutes' => 30]);
        $this->assertNotEmpty($found);
        $foundProduct = array_filter($found, fn ($p) => $p->getId() === $product->getId());
        $this->assertNotEmpty($foundProduct);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testFindBy(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('FindBy Product 1', true);
        $product2 = $this->createProduct('FindBy Product 2', false);

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->flush();

        $results = $repository->findBy(['enabled' => true]);
        $enabledIds = array_map(fn ($p) => $p->getId(), $results);
        $this->assertContains($product1->getId(), $enabledIds);
        $this->assertNotContains($product2->getId(), $enabledIds);

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->flush();
    }

    public function testFindByWithLimit(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('Limit Product 1');
        $product2 = $this->createProduct('Limit Product 2');
        $product3 = $this->createProduct('Limit Product 3');

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->persist($product3);
        self::getEntityManager()->flush();

        $results = $repository->findBy(['enabled' => true], null, 2);
        $this->assertLessThanOrEqual(2, count($results));

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->remove($product3);
        self::getEntityManager()->flush();
    }

    public function testFindByWithOffset(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('Offset Product 1');
        $product2 = $this->createProduct('Offset Product 2');
        $product3 = $this->createProduct('Offset Product 3');

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->persist($product3);
        self::getEntityManager()->flush();

        $results = $repository->findBy(['enabled' => true], ['id' => 'ASC'], 2, 1);
        $this->assertLessThanOrEqual(2, count($results));

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->remove($product3);
        self::getEntityManager()->flush();
    }

    public function testFindByEmpty(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $results = $repository->findBy(['name' => 'NONEXISTENT_PRODUCT_' . uniqid()]);
        $this->assertEmpty($results);
    }

    public function testFindOneBy(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $uniqueName = 'Unique FindOneBy Product ' . uniqid();
        $product = $this->createProduct($uniqueName);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['name' => $uniqueName]);
        $this->assertNotNull($found);
        $this->assertEquals($uniqueName, $found->getName());

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testFindOneByWithOrderBy(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('FindOneBy Product A');
        $product2 = $this->createProduct('FindOneBy Product Z');

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['enabled' => true], ['name' => 'ASC']);
        $this->assertNotNull($found);
        $this->assertTrue($found->isEnabled());

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->flush();
    }

    public function testFindOneByMultipleCriteria(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $uniqueName = 'Multi Criteria Product ' . uniqid();
        $product1 = $this->createProduct($uniqueName, true);
        $product2 = $this->createProduct($uniqueName . ' Disabled', false);

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['name' => $uniqueName, 'enabled' => true]);
        $this->assertNotNull($found);
        $this->assertEquals($uniqueName, $found->getName());
        $this->assertTrue($found->isEnabled());

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->flush();
    }

    public function testFindOneByNullableField(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        $product->setDescription(null);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $found = $repository->findOneBy(['description' => null]);
        $this->assertNotNull($found);
        $this->assertNull($found->getDescription());

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testFindOneByNotFound(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $found = $repository->findOneBy(['name' => 'NONEXISTENT_PRODUCT_' . uniqid()]);
        $this->assertNull($found);
    }

    public function testCount(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('Count Product 1');
        $product2 = $this->createProduct('Count Product 2');

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->flush();

        $count = $repository->count([]);
        $this->assertGreaterThanOrEqual(2, $count);

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->flush();
    }

    public function testCountByEnabled(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('Enabled Count Product 1', true);
        $product2 = $this->createProduct('Disabled Count Product 2', false);

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->flush();

        $enabledCount = $repository->count(['enabled' => true]);
        $this->assertGreaterThanOrEqual(1, $enabledCount);

        $disabledCount = $repository->count(['enabled' => false]);
        $this->assertGreaterThanOrEqual(1, $disabledCount);

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->flush();
    }

    public function testCountByName(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $uniqueName = 'Count By Name Product ' . uniqid();
        $product = $this->createProduct($uniqueName);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $count = $repository->count(['name' => $uniqueName]);
        $this->assertEquals(1, $count);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testCountByMultipleCriteria(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product1 = $this->createProduct('Multi Count Product', true);
        $product2 = $this->createProduct('Multi Count Product', false);

        self::getEntityManager()->persist($product1);
        self::getEntityManager()->persist($product2);
        self::getEntityManager()->flush();

        $count = $repository->count(['name' => 'Multi Count Product', 'enabled' => true]);
        $this->assertEquals(1, $count);

        self::getEntityManager()->remove($product1);
        self::getEntityManager()->remove($product2);
        self::getEntityManager()->flush();
    }

    public function testCountEmpty(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $count = $repository->count(['name' => 'NONEXISTENT_PRODUCT_' . uniqid()]);
        $this->assertEquals(0, $count);
    }

    public function testDatabaseConnectionFailure(): void
    {
        // This test is conceptual - in real scenarios, we would mock the EntityManager
        // to throw connection exceptions. For integration tests, we assume DB is available.
        $repository = self::getService(DurationBillingProductRepository::class);

        // Test that repository can handle basic operations
        $this->expectNotToPerformAssertions();
        try {
            $repository->findAll();
        } catch (\Exception $e) {
            self::fail('Unexpected database connection failure: ' . $e->getMessage());
        }
    }

    public function testNullFieldQueries(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        $product->setDescription(null);
        $product->setFreezeMinutes(null);
        $product->setMinAmount(null);
        $product->setMaxAmount(null);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        // Test querying for null description
        $nullDescriptionProducts = $repository->findBy(['description' => null]);
        $this->assertNotEmpty($nullDescriptionProducts);

        // Test querying for null freeze minutes
        $nullFreezeMinutesProducts = $repository->findBy(['freezeMinutes' => null]);
        $this->assertNotEmpty($nullFreezeMinutesProducts);

        // Test querying for null min amount
        $nullMinAmountProducts = $repository->findBy(['minAmount' => null]);
        $this->assertNotEmpty($nullMinAmountProducts);

        // Test querying for null max amount
        $nullMaxAmountProducts = $repository->findBy(['maxAmount' => null]);
        $this->assertNotEmpty($nullMaxAmountProducts);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testSave(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct('Save Test Product');

        $repository->save($product, true);
        $this->assertNotNull($product->getId());

        $repository->remove($product, true);
    }

    public function testRemove(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct('Remove Test Product');

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();
        $productId = $product->getId();

        $repository->remove($product, true);

        $found = $repository->find($productId);
        $this->assertNull($found);
    }

    public function testCountNullDescription(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        $product->setDescription(null);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $count = $repository->count(['description' => null]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testCountNullFreezeMinutes(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        $product->setFreezeMinutes(null);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $count = $repository->count(['freezeMinutes' => null]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testCountNullMinAmount(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        $product->setMinAmount(null);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $count = $repository->count(['minAmount' => null]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    public function testCountNullMaxAmount(): void
    {
        $repository = self::getService(DurationBillingProductRepository::class);
        $product = $this->createProduct();
        $product->setMaxAmount(null);

        self::getEntityManager()->persist($product);
        self::getEntityManager()->flush();

        $count = $repository->count(['maxAmount' => null]);
        $this->assertGreaterThanOrEqual(1, $count);

        self::getEntityManager()->remove($product);
        self::getEntityManager()->flush();
    }

    private function createProduct(?string $name = null, ?bool $enabled = null): DurationBillingProduct
    {
        $product = new DurationBillingProduct();
        $product->setName($name ?? 'Test Product ' . uniqid());
        $product->setEnabled($enabled ?? true);

        $pricingRule = new HourlyPricingRule(100.0, RoundingMode::UP);
        $product->setPricingRule($pricingRule);

        return $product;
    }

    /**
     * @return ServiceEntityRepository<DurationBillingProduct>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(DurationBillingProductRepository::class);
    }

    protected function createNewEntity(): object
    {
        return $this->createProduct();
    }
}
