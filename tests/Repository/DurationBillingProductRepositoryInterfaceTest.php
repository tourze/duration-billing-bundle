<?php

namespace Tourze\DurationBillingBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Contract\DurationBillingProductRepositoryInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Repository\DurationBillingProductRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingProductRepository::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingProductRepositoryInterfaceTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
    }

    protected function getRepository(): DurationBillingProductRepository
    {
        return self::getService(DurationBillingProductRepository::class);
    }

    protected function createNewEntity(): object
    {
        $product = new DurationBillingProduct();
        $product->setName('Test Product ' . uniqid());
        $product->setPricingRuleData(['class' => 'TestRule', 'data' => []]);
        $product->setEnabled(true);

        return $product;
    }

    public function testProductRepositoryInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(DurationBillingProductRepositoryInterface::class));
    }

    public function testRepositoryImplementsInterface(): void
    {
        // 测试Repository实现了正确的接口
        $repository = $this->getRepository();
        $this->assertInstanceOf(DurationBillingProductRepositoryInterface::class, $repository);
    }

    public function testFindById(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $product = $repository->findById(99999);
        $this->assertNull($product); // 不存在的ID返回null
    }

    public function testFindEnabledProducts(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $products = $repository->findEnabledProducts();
        $this->assertIsArray($products);
    }

    public function testFindByName(): void
    {
        $repository = $this->getRepository();

        // 测试基本功能性
        $product = $repository->findByName('nonexistent-product');
        $this->assertNull($product); // 不存在的产品名返回null
    }
}
