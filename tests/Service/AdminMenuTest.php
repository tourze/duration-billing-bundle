<?php

declare(strict_types=1);

namespace Tourze\DurationBillingBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DurationBillingBundle\Service\AdminMenu;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * AdminMenu 单元测试
 *
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private ItemInterface $item;

    public function testInvokeMethod(): void
    {
        // 测试 AdminMenu 的 __invoke 方法正常工作
        $this->expectNotToPerformAssertions();

        try {
            $adminMenu = self::getService(AdminMenu::class);
            ($adminMenu)($this->item);
        } catch (\Throwable $e) {
            self::fail('AdminMenu __invoke method should not throw exception: ' . $e->getMessage());
        }
    }

    public function testMenuCreatesCorrectStructure(): void
    {
        $adminMenu = self::getService(AdminMenu::class);

        // 使用实际的菜单项进行测试
        $rootItem = $this->createMock(ItemInterface::class);
        $billingMenu = $this->createMock(ItemInterface::class);
        $productMenuItem = $this->createMock(ItemInterface::class);
        $orderMenuItem = $this->createMock(ItemInterface::class);

        // 使用 willReturnMap 来处理多次调用
        $rootItem->expects($this->exactly(2))
            ->method('getChild')
            ->with('计费管理')
            ->willReturnOnConsecutiveCalls(null, $billingMenu)
        ;

        $rootItem->expects($this->once())
            ->method('addChild')
            ->with('计费管理')
            ->willReturn($billingMenu)
        ;

        // 设置计费管理菜单的子菜单
        $billingMenu->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnOnConsecutiveCalls($productMenuItem, $orderMenuItem)
        ;

        $productMenuItem->expects($this->once())
            ->method('setUri')
            ->willReturn($productMenuItem)
        ;

        $productMenuItem->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fas fa-cubes')
            ->willReturn($productMenuItem)
        ;

        $orderMenuItem->expects($this->once())
            ->method('setUri')
            ->willReturn($orderMenuItem)
        ;

        $orderMenuItem->expects($this->once())
            ->method('setAttribute')
            ->with('icon', 'fas fa-receipt')
            ->willReturn($orderMenuItem)
        ;

        ($adminMenu)($rootItem);
    }

    protected function onSetUp(): void
    {
        $this->item = $this->createMock(ItemInterface::class);

        // 设置 mock 的返回值以避免 null 引用
        $childItem = $this->createMock(ItemInterface::class);
        $this->item->method('addChild')->willReturn($childItem);

        // 使用 willReturnCallback 来模拟 getChild 的行为
        $this->item->method('getChild')->willReturnCallback(function ($name) use ($childItem) {
            return '计费管理' === $name ? $childItem : null;
        });

        // 设置子菜单项的 mock 行为
        $childItem->method('addChild')->willReturn($childItem);
        $childItem->method('setUri')->willReturn($childItem);
        $childItem->method('setAttribute')->willReturn($childItem);
    }
}
