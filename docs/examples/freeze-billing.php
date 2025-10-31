<?php

/**
 * 冻结计费示例
 *
 * 这个示例展示了如何使用冻结和恢复功能来暂停和继续计费
 * 适用场景：用户临时离开、设备维护、服务暂停等
 */

use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\RoundingMode;
use Tourze\DurationBillingBundle\Exception\InvalidOrderStateException;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Service\DurationBillingServiceInterface;

// 假设这些服务已经通过依赖注入获得
/** @var EntityManagerInterface $entityManager */
/** @var DurationBillingServiceInterface $billingService */

// ========================================
// 1. 准备测试数据
// ========================================

echo "=== 准备测试数据 ===\n";

// 创建一个健身房计费产品
$product = new DurationBillingProduct();
$product->setName('健身房单次使用');
$product->setDescription('健身房单次使用，包含所有器械和淋浴');
$product->setActive(true);

// 每小时20元，按实际时间计费（向下取整）
$pricingRule = new HourlyPricingRule(
    hourlyPrice: 20.0,
    roundingMode: RoundingMode::ROUND_DOWN
);
$product->setPricingRule($pricingRule);

// 无免费时长，最低10元，最高100元
$product->setFreeMinutes(0);
$product->setMinAmount(10.0);
$product->setMaxAmount(100.0);

$entityManager->persist($product);
$entityManager->flush();

echo "产品创建成功：{$product->getName()}\n\n";

// ========================================
// 2. 开始计费
// ========================================

echo "=== 用户进入健身房，开始计费 ===\n";

$order = $billingService->startBilling(
    productId: $product->getId(),
    userId: 'MEMBER_456',
    externalOrderCode: 'GYM_SESSION_001'
);

echo "计费开始！\n";
echo "订单编号: {$order->getOrderCode()}\n";
echo "开始时间: {$order->getStartedAt()->format('H:i:s')}\n";
echo "当前状态: {$order->getStatus()->value}\n\n";

// ========================================
// 3. 模拟使用30分钟后暂停
// ========================================

echo "=== 使用30分钟后，用户需要接电话，暂停计费 ===\n";

// 模拟时间流逝（实际应用中是真实时间）
sleep(1); // 确保时间有变化

try {
    $billingService->freezeBilling($order->getOrderCode());

    // 重新获取订单查看状态
    $order = $billingService->getOrder($order->getOrderCode());

    echo "计费已冻结！\n";
    echo '冻结时间: ' . (new DateTime())->format('H:i:s') . "\n";
    echo "当前状态: {$order->getStatus()->value}\n";
    echo '冻结时的费用: ￥' . $billingService->calculateCurrentPrice($order->getOrderCode()) . "\n\n";
} catch (InvalidOrderStateException $e) {
    echo "错误：无法冻结订单 - {$e->getMessage()}\n";
}

// ========================================
// 4. 冻结期间的操作
// ========================================

echo "=== 冻结期间尝试各种操作 ===\n";

// 尝试再次冻结（应该失败）
try {
    $billingService->freezeBilling($order->getOrderCode());
    echo "错误：不应该能够再次冻结\n";
} catch (InvalidOrderStateException $e) {
    echo "✓ 正确：无法对已冻结的订单再次冻结\n";
}

// 尝试结束计费（应该失败）
try {
    $billingService->endBilling($order->getOrderCode());
    echo "错误：不应该能够直接结束冻结的订单\n";
} catch (InvalidOrderStateException $e) {
    echo "✓ 正确：无法直接结束冻结的订单\n";
}

// 查询当前价格（冻结期间价格不变）
$frozenPrice = $billingService->calculateCurrentPrice($order->getOrderCode());
echo "✓ 冻结期间的价格保持不变: ￥{$frozenPrice}\n\n";

// ========================================
// 5. 恢复计费
// ========================================

echo "=== 15分钟后，用户回来继续健身，恢复计费 ===\n";

sleep(1); // 模拟时间流逝

try {
    $billingService->resumeBilling($order->getOrderCode());

    // 重新获取订单
    $order = $billingService->getOrder($order->getOrderCode());

    echo "计费已恢复！\n";
    echo '恢复时间: ' . (new DateTime())->format('H:i:s') . "\n";
    echo "当前状态: {$order->getStatus()->value}\n";
    echo "总冻结时长: {$order->getTotalFrozenMinutes()}分钟\n\n";
} catch (InvalidOrderStateException $e) {
    echo "错误：无法恢复订单 - {$e->getMessage()}\n";
}

// ========================================
// 6. 继续使用并结束
// ========================================

echo "=== 再使用45分钟后，用户离开，结束计费 ===\n";

// 模拟更多时间流逝
sleep(1);

$result = $billingService->endBilling($order->getOrderCode());

echo "计费结束！\n";
echo "总使用时长: {$order->getActualBillingMinutes()}分钟（不含冻结时间）\n";
echo "总冻结时长: {$order->getTotalFrozenMinutes()}分钟\n";
echo "实际计费时长: {$result->getBillableMinutes()}分钟\n";
echo "最终费用: ￥{$result->getFinalPrice()}\n\n";

// ========================================
// 7. 处理超时冻结订单
// ========================================

echo "=== 演示超时冻结订单的处理 ===\n";

// 创建一个新订单并冻结
$order2 = $billingService->startBilling(
    productId: $product->getId(),
    userId: 'MEMBER_789',
    externalOrderCode: 'GYM_SESSION_002'
);

$billingService->freezeBilling($order2->getOrderCode());
echo "创建了一个冻结的订单: {$order2->getOrderCode()}\n";

// 查找冻结超时的订单
$expiredOrders = $billingService->findExpiredFrozenOrders(
    freezeMinutes: 1,  // 为了演示，设置为1分钟
    limit: 10
);

echo '找到 ' . count($expiredOrders) . " 个冻结超时的订单\n";

foreach ($expiredOrders as $expiredOrder) {
    echo "- 订单 {$expiredOrder->getOrderCode()} 已冻结 {$expiredOrder->getTotalFrozenMinutes()} 分钟\n";

    // 自动结束超时的订单
    try {
        $billingService->endBilling($expiredOrder->getOrderCode());
        echo "  ✓ 已自动结束\n";
    } catch (Exception $e) {
        echo "  ✗ 结束失败: {$e->getMessage()}\n";
    }
}

// ========================================
// 8. 清理测试数据
// ========================================

echo "\n=== 清理测试数据 ===\n";

// 清理订单
$orders = $entityManager->getRepository(DurationBillingOrder::class)
    ->findBy(['product' => $product])
;

foreach ($orders as $order) {
    $entityManager->remove($order);
}

// 清理产品
$entityManager->remove($product);
$entityManager->flush();

echo "测试数据已清理\n";
echo "\n示例运行完成！\n";

// ========================================
// 使用建议
// ========================================

echo "\n=== 使用建议 ===\n";
echo "1. 冻结功能适用于临时暂停服务的场景\n";
echo "2. 建议设置冻结超时时间，避免订单长期占用\n";
echo "3. 可以通过定时任务自动处理超时的冻结订单\n";
echo "4. 冻结期间的时间不计入实际使用时长\n";
echo "5. 一个订单可以多次冻结和恢复\n";
