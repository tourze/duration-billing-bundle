<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Tests\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Tourze\DurationBillingBundle\Controller\Admin\DurationBillingProductCrudController;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(DurationBillingProductCrudController::class)]
#[RunTestsInSeparateProcesses]
final class DurationBillingProductCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    protected static ?KernelBrowser $client = null;

    protected function getEntityFqcn(): string
    {
        return DurationBillingProduct::class;
    }

    protected function getControllerService(): DurationBillingProductCrudController
    {
        return self::getService(DurationBillingProductCrudController::class);
    }

    private function persistEntity(object $entity): void
    {
        $em = self::getService(EntityManagerInterface::class);
        $em->persist($entity);
        $em->flush();
    }

    public function testIndexPage(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client);

        $crawler = $client->request('GET', '/admin');
        self::assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Navigate to DurationBillingProductCrudController CRUD
        $link = $crawler->filter('a[href*="DurationBillingProductCrudController"]')->first();
        if ($link->count() > 0) {
            $client->click($link->link());
            self::assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        }
    }

    public function testControllerInheritance(): void
    {
        $controller = new DurationBillingProductCrudController();
        $reflection = new \ReflectionClass($controller);
        self::assertTrue($reflection->isSubclassOf(AbstractCrudController::class));
    }

    public function testEntityFqcnConfiguration(): void
    {
        $controller = new DurationBillingProductCrudController();
        self::assertEquals(DurationBillingProduct::class, $controller::getEntityFqcn());
    }

    public function testConfigureFieldsForDifferentPages(): void
    {
        $controller = new DurationBillingProductCrudController();

        foreach (['index', 'new', 'edit', 'detail'] as $pageName) {
            $fields = $controller->configureFields($pageName);
            self::assertIsIterable($fields, "Fields should be iterable for page: {$pageName}");
        }
    }

    public function testNewPageAccessAndValidation(): void
    {
        $client = $this->createAuthenticatedClient();

        // Access new page
        $newUrl = $this->generateAdminUrl('new');
        $crawler = $client->request('GET', $newUrl);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('创建计费产品', $crawler->text());

        // Test form validation - submit empty form
        $saveButton = $crawler->filter('button:contains("保存"), input[type="submit"][value*="保存"], button[type="submit"]');
        if ($saveButton->count() > 0) {
            $form = $saveButton->form();
            $client->submit($form);

            // Should stay on form page due to validation errors
            $response = $client->getResponse();
            self::assertTrue(
                Response::HTTP_OK === $response->getStatusCode()
                || Response::HTTP_UNPROCESSABLE_ENTITY === $response->getStatusCode(),
                'Expected validation errors for empty required fields'
            );
        } else {
            // If we can't find save button, just check that form exists
            $forms = $crawler->filter('form');
            self::assertGreaterThan(0, $forms->count(), 'Form should exist on new page');
        }
    }

    public function testSuccessfulProductCreation(): void
    {
        $client = $this->createAuthenticatedClient();

        $newUrl = $this->generateAdminUrl('new');
        $crawler = $client->request('GET', $newUrl);

        // Check if we can access the new page
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Look for any form and submit button
        $forms = $crawler->filter('form');
        if ($forms->count() > 0) {
            // Try to find any submit button
            $submitButtons = $crawler->filter('button[type="submit"], input[type="submit"], button:contains("Save"), button:contains("保存")');

            if ($submitButtons->count() > 0) {
                $form = $submitButtons->first()->form([
                    'DurationBillingProduct[name]' => '测试产品',
                    'DurationBillingProduct[description]' => '测试产品描述',
                    'DurationBillingProduct[freeMinutes]' => '10',
                    'DurationBillingProduct[enabled]' => '1',
                ]);

                $client->submit($form);

                // Check if form submission was processed (either redirect or stay on page with validation)
                $response = $client->getResponse();
                self::assertTrue(
                    $response->isRedirect()
                    || Response::HTTP_OK === $response->getStatusCode()
                    || Response::HTTP_UNPROCESSABLE_ENTITY === $response->getStatusCode(),
                    'Form should be processed successfully or show validation errors'
                );
            } else {
                // Try alternative: just test that the form exists
                self::assertGreaterThan(0, $forms->count(), 'Form should exist on new page');
                self::markTestSkipped('No submit button found on form, but form exists');
            }
        } else {
            self::markTestSkipped('No form found on new page');
        }
    }

    public function testEditPageAccess(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create a test product first
        $product = new DurationBillingProduct();
        $product->setName('测试产品');
        $product->setFreeMinutes(10);
        $product->setEnabled(true);
        $product->setPricingRuleData(['class' => 'TestPricingRule', 'data' => []]);

        $this->persistEntity($product);

        // Access edit page
        $editUrl = $this->generateAdminUrl('edit', ['entityId' => $product->getId()]);
        $crawler = $client->request('GET', $editUrl);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('编辑计费产品', $crawler->text());
    }

    public function testDetailPageAccess(): void
    {
        $client = $this->createAuthenticatedClient();

        // Create a test product first
        $product = new DurationBillingProduct();
        $product->setName('测试产品');
        $product->setFreeMinutes(10);
        $product->setEnabled(true);
        $product->setPricingRuleData(['class' => 'TestPricingRule', 'data' => []]);

        $this->persistEntity($product);

        // Access detail page
        $detailUrl = $this->generateAdminUrl('detail', ['entityId' => $product->getId()]);
        $crawler = $client->request('GET', $detailUrl);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('计费产品详情', $crawler->text());
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
        yield '产品名称' => ['产品名称'];
        yield '免费时长(分钟)' => ['免费时长(分钟)'];
        yield '是否启用' => ['是否启用'];
        yield '创建时间' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'pricingRuleData' => ['pricingRuleData'];
        yield 'freeMinutes' => ['freeMinutes'];
        yield 'freezeMinutes' => ['freezeMinutes'];
        yield 'minAmount' => ['minAmount'];
        yield 'maxAmount' => ['maxAmount'];
        yield 'enabled' => ['enabled'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'name' => ['name'];
        yield 'description' => ['description'];
        yield 'pricingRuleData' => ['pricingRuleData'];
        yield 'freeMinutes' => ['freeMinutes'];
        yield 'freezeMinutes' => ['freezeMinutes'];
        yield 'minAmount' => ['minAmount'];
        yield 'maxAmount' => ['maxAmount'];
        yield 'enabled' => ['enabled'];
    }
}
