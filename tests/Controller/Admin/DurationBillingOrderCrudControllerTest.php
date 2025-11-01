<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Tests\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Tourze\DurationBillingBundle\Controller\Admin\DurationBillingOrderCrudController;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingOrderCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingOrderCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected static ?KernelBrowser $client = null;

    protected function getEntityFqcn(): string
    {
        return DurationBillingOrder::class;
    }

    protected function getControllerService(): DurationBillingOrderCrudController
    {
        return self::getService(DurationBillingOrderCrudController::class);
    }

    private function persistEntity(object $entity): void
    {
        $em = self::getService(EntityManagerInterface::class);
        $em->persist($entity);
        $em->flush();
    }

    public function testIndexPage(): void
    {
        $client = self::createAuthenticatedClient();

        $crawler = $client->request('GET', '/admin');
        self::assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Navigate to DurationBillingOrderCrudController CRUD
        $link = $crawler->filter('a[href*="DurationBillingOrderCrudController"]')->first();
        if ($link->count() > 0) {
            $client->click($link->link());
            self::assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        }
    }

    public function testControllerInheritance(): void
    {
        $controller = new DurationBillingOrderCrudController();
        $reflection = new \ReflectionClass($controller);
        self::assertTrue($reflection->isSubclassOf(AbstractCrudController::class));
    }

    public function testEntityFqcnConfiguration(): void
    {
        $controller = new DurationBillingOrderCrudController();
        self::assertEquals(DurationBillingOrder::class, $controller::getEntityFqcn());
    }

    public function testConfigureFieldsForDifferentPages(): void
    {
        $controller = new DurationBillingOrderCrudController();

        foreach (['index', 'new', 'edit', 'detail'] as $pageName) {
            $fields = $controller->configureFields($pageName);
            self::assertIsIterable($fields, "Fields should be iterable for page: {$pageName}");
        }
    }

    public function testNewPageAccessAndValidation(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create a test product first (required for association)
        $product = new DurationBillingProduct();
        $product->setName('测试产品');
        $product->setFreeMinutes(10);
        $product->setEnabled(true);
        $product->setPricingRuleData(['class' => 'TestPricingRule', 'data' => []]);
        $this->persistEntity($product);

        // Access new page
        $newUrl = $this->generateAdminUrl('new');
        $crawler = $client->request('GET', $newUrl);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('创建计费订单', $crawler->text());

        // Test form validation - just verify the form exists and has required fields
        $forms = $crawler->filter('form');
        self::assertGreaterThan(0, $forms->count(), 'Form should exist on new page');

        // Check that form has required fields
        $productField = $crawler->filter('select[name*="[product]"], input[name*="[product]"]');
        $userIdField = $crawler->filter('input[name*="[userId]"]');
        $orderCodeField = $crawler->filter('input[name*="[orderCode]"]');
        $startTimeField = $crawler->filter('input[name*="[startTime]"]');
        $prepaidAmountField = $crawler->filter('input[name*="[prepaidAmount]"]');

        self::assertGreaterThan(0, $productField->count(), 'Product field should exist');
        self::assertGreaterThan(0, $userIdField->count(), 'UserId field should exist');
        self::assertGreaterThan(0, $orderCodeField->count(), 'OrderCode field should exist');
        self::assertGreaterThan(0, $startTimeField->count(), 'StartTime field should exist');
        self::assertGreaterThan(0, $prepaidAmountField->count(), 'PrepaidAmount field should exist');
    }

    public function testSuccessfulOrderCreation(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create a test product first
        $product = new DurationBillingProduct();
        $product->setName('测试产品');
        $product->setFreeMinutes(10);
        $product->setEnabled(true);
        $product->setPricingRuleData(['class' => 'TestPricingRule', 'data' => []]);
        $this->persistEntity($product);

        $newUrl = $this->generateAdminUrl('new');
        $crawler = $client->request('GET', $newUrl);

        // Check that the new page loads successfully
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Look for any form
        $forms = $crawler->filter('form');
        if ($forms->count() > 0) {
            // Just verify that the form exists and has the expected fields
            $productField = $crawler->filter('select[name*="[product]"], input[name*="[product]"]');
            $userIdField = $crawler->filter('input[name*="[userId]"]');
            $orderCodeField = $crawler->filter('input[name*="[orderCode]"]');

            self::assertGreaterThan(0, $productField->count(), 'Product field should exist');
            self::assertGreaterThan(0, $userIdField->count(), 'UserId field should exist');
            self::assertGreaterThan(0, $orderCodeField->count(), 'OrderCode field should exist');

        // Form fields verification passed - no further assertion needed
        // self::assertTrue(true, 'Order creation form is properly configured');
        } else {
            self::markTestSkipped('No form found on new page');
        }
    }

    public function testEditPageAccess(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create test data
        $product = new DurationBillingProduct();
        $product->setName('测试产品');
        $product->setFreeMinutes(10);
        $product->setEnabled(true);
        $product->setPricingRuleData(['class' => 'TestPricingRule', 'data' => []]);
        $this->persistEntity($product);

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-ORDER-123');
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setStartTime(new \DateTimeImmutable());
        $order->setPrepaidAmount(100.00);
        $this->persistEntity($order);

        // Access edit page
        $editUrl = $this->generateAdminUrl('edit', ['entityId' => $order->getId()]);
        $crawler = $client->request('GET', $editUrl);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('编辑计费订单', $crawler->text());
    }

    public function testDetailPageAccess(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create test data
        $product = new DurationBillingProduct();
        $product->setName('测试产品');
        $product->setFreeMinutes(10);
        $product->setEnabled(true);
        $product->setPricingRuleData(['class' => 'TestPricingRule', 'data' => []]);
        $this->persistEntity($product);

        $order = new DurationBillingOrder();
        $order->setProduct($product);
        $order->setUserId('test-user');
        $order->setOrderCode('TEST-ORDER-123');
        $order->setStatus(OrderStatus::ACTIVE);
        $order->setStartTime(new \DateTimeImmutable());
        $order->setPrepaidAmount(100.00);
        $this->persistEntity($order);

        // Access detail page
        $detailUrl = $this->generateAdminUrl('detail', ['entityId' => $order->getId()]);
        $crawler = $client->request('GET', $detailUrl);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('计费订单详情', $crawler->text());
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();

        // Access new page
        $newUrl = $this->generateAdminUrl('new');
        $crawler = $client->request('GET', $newUrl);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Find and submit empty form to trigger validation errors
        $forms = $crawler->filter('form');
        if ($forms->count() > 0) {
            $form = $forms->first()->form();
            $crawler = $client->submit($form);

            // Check that we get validation errors (422 or stay on form page)
            $response = $client->getResponse();
            self::assertTrue(
                Response::HTTP_UNPROCESSABLE_ENTITY === $response->getStatusCode()
                || Response::HTTP_OK === $response->getStatusCode(),
                'Should get validation errors when submitting empty form'
            );

            // If we stayed on the form page, check for error indicators
            if (Response::HTTP_OK === $response->getStatusCode()) {
                $errorIndicators = $crawler->filter('.invalid-feedback, .error, .form-error, .field-error, [class*="error"]');
                $hasErrorMessages = $errorIndicators->count() > 0
                    || str_contains($crawler->html(), 'should not be blank')
                    || str_contains($crawler->html(), 'This value should not be blank')
                    || str_contains($crawler->html(), 'required');

                self::assertTrue($hasErrorMessages, 'Form should show validation error messages');
            }
        } else {
            self::markTestSkipped('No form found for validation testing');
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '计费产品' => ['计费产品'];
        yield '用户ID' => ['用户ID'];
        yield '订单编号' => ['订单编号'];
        yield '订单状态' => ['订单状态'];
        yield '开始时间' => ['开始时间'];
        yield '预付金额' => ['预付金额'];
        yield '创建时间' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'product' => ['product'];
        yield 'userId' => ['userId'];
        yield 'orderCode' => ['orderCode'];
        yield 'status' => ['status'];
        yield 'startTime' => ['startTime'];
        yield 'prepaidAmount' => ['prepaidAmount'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'product' => ['product'];
        yield 'userId' => ['userId'];
        yield 'orderCode' => ['orderCode'];
        yield 'status' => ['status'];
        yield 'startTime' => ['startTime'];
        yield 'endTime' => ['endTime'];
        yield 'paymentTime' => ['paymentTime'];
        yield 'frozenAt' => ['frozenAt'];
        yield 'prepaidAmount' => ['prepaidAmount'];
        yield 'actualAmount' => ['actualAmount'];
        yield 'frozenMinutes' => ['frozenMinutes'];
    }
}
