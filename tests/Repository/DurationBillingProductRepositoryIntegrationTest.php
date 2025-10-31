<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Repository\DurationBillingProductRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * 时长计费产品仓库集成测试
 *
 * @internal
 */
#[CoversClass(DurationBillingProductRepository::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingProductRepositoryIntegrationTest extends AbstractRepositoryTestCase
{
    private DurationBillingProductRepository $repository;

    protected function onSetUp(): void
    {
        $repository = self::getEntityManager()->getRepository(DurationBillingProduct::class);
        self::assertInstanceOf(DurationBillingProductRepository::class, $repository);
        $this->repository = $repository;
    }

    protected function createNewEntity(): object
    {
        return $this->createTestProduct('测试产品', true);
    }

    protected function getRepository(): DurationBillingProductRepository
    {
        return $this->repository;
    }

    public function testFindById(): void
    {
        // 创建测试产品
        $product = $this->createTestProduct('测试产品', true);
        $this->persistAndFlush($product);

        // 测试查找
        $productId = $product->getId();
        $this->assertNotNull($productId, 'Product ID should not be null after persisting');
        $foundProduct = $this->repository->findById($productId);

        // 验证结果
        $this->assertNotNull($foundProduct);
        $this->assertSame($product->getId(), $foundProduct->getId());
        $this->assertSame($product->getName(), $foundProduct->getName());

        // 测试查找不存在的ID
        $notFound = $this->repository->findById(99999);
        $this->assertNull($notFound);
    }

    public function testFindByName(): void
    {
        $productName = '特定名称产品';

        // 创建测试产品
        $product = $this->createTestProduct($productName, true);
        $this->persistAndFlush($product);

        // 测试根据名称查找
        $foundProduct = $this->repository->findByName($productName);

        // 验证结果
        $this->assertNotNull($foundProduct);
        $this->assertSame($product->getId(), $foundProduct->getId());
        $this->assertSame($productName, $foundProduct->getName());

        // 测试查找不存在的名称
        $notFound = $this->repository->findByName('不存在的产品名称');
        $this->assertNull($notFound);
    }

    public function testFindEnabledProducts(): void
    {
        // 创建多个产品，有些启用有些禁用
        $enabledProduct1 = $this->createTestProduct('启用产品1', true);
        $enabledProduct2 = $this->createTestProduct('启用产品2', true);
        $disabledProduct1 = $this->createTestProduct('禁用产品1', false);
        $disabledProduct2 = $this->createTestProduct('禁用产品2', false);

        self::getEntityManager()->persist($enabledProduct1);
        self::getEntityManager()->persist($enabledProduct2);
        self::getEntityManager()->persist($disabledProduct1);
        self::getEntityManager()->persist($disabledProduct2);
        self::getEntityManager()->flush();

        // 查找启用的产品
        $enabledProducts = $this->repository->findEnabledProducts();

        // 验证结果 - 至少包含我们创建的2个启用产品
        $this->assertGreaterThanOrEqual(2, count($enabledProducts));
        $productIds = array_map(fn ($product) => $product->getId(), $enabledProducts);
        $this->assertContains($enabledProduct1->getId(), $productIds);
        $this->assertContains($enabledProduct2->getId(), $productIds);
        $this->assertNotContains($disabledProduct1->getId(), $productIds);
        $this->assertNotContains($disabledProduct2->getId(), $productIds);

        // 验证所有返回的产品都是启用的
        foreach ($enabledProducts as $product) {
            $this->assertTrue($product->isEnabled(), 'All returned products should be enabled');
        }
    }

    // testFindInactiveProducts removed - method does not exist in repository

    // testFindProductsByPricingRuleClass removed - method does not exist in repository

    // testSearchProductsByName removed - method does not exist in repository

    // testFindProductsInPriceRange removed - method does not exist in repository and entity has no basePrice property

    // testCountActiveProducts removed - method does not exist in repository

    // testCountInactiveProducts removed - method does not exist in repository

    // testFindRecentlyCreatedProducts removed - method does not exist in repository

    private function createTestProduct(string $name, bool $isActive): DurationBillingProduct
    {
        $product = new DurationBillingProduct();
        $product->setName($name);
        $product->setDescription($name . '的描述');
        $product->setPricingRuleData([
            'class' => HourlyPricingRule::class,
            'price_per_hour' => 10.0,
            'rounding_mode' => 'up',
        ]);
        $product->setActive($isActive);

        return $product;
    }

    // createHourlyProduct and createTieredProduct methods removed as they are unused
}
