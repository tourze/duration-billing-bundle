<?php

/**
 * 预付费计费示例
 *
 * 这个示例展示了如何处理预付费场景，包括退款和补款的计算
 * 适用场景：共享汽车、共享单车、KTV包厢等需要预付费的服务
 */

use Symfony\Component\EventDispatcher\EventDispatcher;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Event\RefundRequiredEvent;
use Tourze\DurationBillingBundle\PricingRule\TieredPricingRule;
use Tourze\DurationBillingBundle\Service\DurationBillingServiceInterface;
use Tourze\DurationBillingBundle\ValueObject\PriceTier;

// 假设这些服务已经通过依赖注入获得
/** @var EntityManagerInterface $entityManager */
/** @var DurationBillingServiceInterface $billingService */
/** @var EventDispatcher $eventDispatcher */

// ========================================
// 1. 创建共享汽车产品（阶梯计费）
// ========================================

echo "=== 创建共享汽车产品 ===\n";

$product = new DurationBillingProduct();
$product->setName('共享汽车-经济型');
$product->setDescription('5座经济型轿车，适合市内出行');
$product->setActive(true);

// 创建阶梯计费规则
$tiers = [
    new PriceTier(0, 30, 30.0),        // 前30分钟：30元/小时（起步价）
    new PriceTier(30, 120, 25.0),      // 30-120分钟：25元/小时
    new PriceTier(120, 360, 20.0),     // 2-6小时：20元/小时
    new PriceTier(360, null, 15.0),    // 6小时以上：15元/小时（长租优惠）
];

$pricingRule = new TieredPricingRule($tiers);
$product->setPricingRule($pricingRule);

// 设置免费时长和价格限制
$product->setFreeMinutes(5);        // 前5分钟免费（开车前准备时间）
$product->setMinAmount(15.0);       // 最低收费15元
$product->setMaxAmount(300.0);      // 单次最高收费300元（日封顶）

$entityManager->persist($product);
$entityManager->flush();

echo "产品创建成功！\n";
echo "计费规则：\n";
echo "- 0-30分钟: 30元/小时\n";
echo "- 30-120分钟: 25元/小时\n";
echo "- 2-6小时: 20元/小时\n";
echo "- 6小时以上: 15元/小时\n";
echo "- 前5分钟免费，最低15元，最高300元\n\n";

// ========================================
// 2. 监听退款事件
// ========================================

echo "=== 设置退款事件监听器 ===\n";

$refundHandler = function (RefundRequiredEvent $event): void {
    $order = $event->getOrder();
    $refundAmount = $event->getRefundAmount();

    echo "\n【退款通知】\n";
    echo "订单号: {$order->getOrderCode()}\n";
    echo "用户ID: {$order->getUserId()}\n";
    echo "退款金额: ￥{$refundAmount}\n";
    echo "原因: 预付金额大于实际消费\n";
    echo "处理: 已提交到退款队列\n\n";
};

$eventDispatcher->addListener(RefundRequiredEvent::class, $refundHandler);

// ========================================
// 3. 场景一：短途出行（需要退款）
// ========================================

echo "=== 场景一：短途出行（15分钟） ===\n";

// 用户预付100元
$order1 = $billingService->startBilling(
    productId: $product->getId(),
    userId: 'USER_CAR_001',
    externalOrderCode: 'CAR_RENTAL_001',
    prepaidAmount: 100.0
);

echo "用户开始用车\n";
echo "订单号: {$order1->getOrderCode()}\n";
echo "预付金额: ￥100.00\n";

// 模拟使用15分钟
echo "\n使用15分钟后还车...\n";

$result1 = $billingService->endBilling($order1->getOrderCode());

echo "\n计费详情:\n";
echo "总使用时长: 15分钟\n";
echo "免费时长: 5分钟\n";
echo "计费时长: 10分钟\n";
echo '基础价格: ￥' . $result1->getBasePrice() . "\n";
echo '最终价格: ￥' . $result1->getFinalPrice() . "（受最低价格限制）\n";
echo "预付金额: ￥100.00\n";
echo '退款金额: ￥' . $result1->getOrder()->getRefundAmount() . "\n";

// ========================================
// 4. 场景二：中途出行（费用适中）
// ========================================

echo "\n=== 场景二：中途出行（2.5小时） ===\n";

// 用户预付100元
$order2 = $billingService->startBilling(
    productId: $product->getId(),
    userId: 'USER_CAR_002',
    externalOrderCode: 'CAR_RENTAL_002',
    prepaidAmount: 100.0
);

echo "用户开始用车\n";
echo "订单号: {$order2->getOrderCode()}\n";
echo "预付金额: ￥100.00\n";

// 模拟使用2.5小时（150分钟）
// 实际应用中这里会是真实的时间流逝
$startedAt = clone $order2->getStartedAt();
$startedAt->modify('-150 minutes');

$updateSql = 'UPDATE duration_billing_order SET started_at = :started_at WHERE order_code = :order_code';
$stmt = $entityManager->getConnection()->prepare($updateSql);
$stmt->executeStatement([
    'started_at' => $startedAt->format('Y-m-d H:i:s'),
    'order_code' => $order2->getOrderCode(),
]);

echo "\n使用2.5小时后还车...\n";

$result2 = $billingService->endBilling($order2->getOrderCode());

echo "\n计费详情:\n";
echo "总使用时长: 150分钟（2.5小时）\n";
echo "免费时长: 5分钟\n";
echo "计费时长: 145分钟\n";

// 计算分段价格
echo "\n价格计算明细:\n";
echo "- 前30分钟: 30分钟 × 30元/小时 = ￥15.00\n";
echo "- 30-120分钟: 90分钟 × 25元/小时 = ￥37.50\n";
echo "- 120-145分钟: 25分钟 × 20元/小时 = ￥8.33\n";
echo "- 基础价格合计: ￥60.83\n";

echo "\n最终价格: ￥" . $result2->getFinalPrice() . "\n";
echo "预付金额: ￥100.00\n";
echo '退款金额: ￥' . $result2->getOrder()->getRefundAmount() . "\n";

// ========================================
// 5. 场景三：长途出行（需要补款）
// ========================================

echo "\n=== 场景三：长途出行（8小时） ===\n";

// 用户预付100元
$order3 = $billingService->startBilling(
    productId: $product->getId(),
    userId: 'USER_CAR_003',
    externalOrderCode: 'CAR_RENTAL_003',
    prepaidAmount: 100.0
);

echo "用户开始用车\n";
echo "订单号: {$order3->getOrderCode()}\n";
echo "预付金额: ￥100.00\n";

// 模拟使用8小时（480分钟）
$startedAt = clone $order3->getStartedAt();
$startedAt->modify('-480 minutes');

$stmt->executeStatement([
    'started_at' => $startedAt->format('Y-m-d H:i:s'),
    'order_code' => $order3->getOrderCode(),
]);

echo "\n使用8小时后还车...\n";

$result3 = $billingService->endBilling($order3->getOrderCode());

echo "\n计费详情:\n";
echo "总使用时长: 480分钟（8小时）\n";
echo "免费时长: 5分钟\n";
echo "计费时长: 475分钟\n";

echo "\n价格计算明细:\n";
echo "- 前30分钟: 30分钟 × 30元/小时 = ￥15.00\n";
echo "- 30-120分钟: 90分钟 × 25元/小时 = ￥37.50\n";
echo "- 120-360分钟: 240分钟 × 20元/小时 = ￥80.00\n";
echo "- 360-475分钟: 115分钟 × 15元/小时 = ￥28.75\n";
echo "- 基础价格合计: ￥161.25\n";

echo "\n最终价格: ￥" . $result3->getFinalPrice() . "\n";
echo "预付金额: ￥100.00\n";

if ($result3->getOrder()->requiresAdditionalPayment()) {
    $additionalPayment = $result3->getOrder()->getFinalAmount() - $result3->getOrder()->getPrepaidAmount();
    echo '需要补款: ￥' . number_format($additionalPayment, 2) . "\n";
}

// ========================================
// 6. 场景四：超长使用（价格封顶）
// ========================================

echo "\n=== 场景四：超长使用（24小时，触发价格上限） ===\n";

// 用户预付300元
$order4 = $billingService->startBilling(
    productId: $product->getId(),
    userId: 'USER_CAR_004',
    externalOrderCode: 'CAR_RENTAL_004',
    prepaidAmount: 300.0
);

echo "用户开始用车\n";
echo "订单号: {$order4->getOrderCode()}\n";
echo "预付金额: ￥300.00\n";

// 模拟使用24小时（1440分钟）
$startedAt = clone $order4->getStartedAt();
$startedAt->modify('-1440 minutes');

$stmt->executeStatement([
    'started_at' => $startedAt->format('Y-m-d H:i:s'),
    'order_code' => $order4->getOrderCode(),
]);

echo "\n使用24小时后还车...\n";

$result4 = $billingService->endBilling($order4->getOrderCode());

echo "\n计费详情:\n";
echo "总使用时长: 1440分钟（24小时）\n";
echo "计费时长: 1435分钟\n";
echo '基础价格: ￥' . $result4->getBasePrice() . "（超过上限）\n";
echo '最终价格: ￥' . $result4->getFinalPrice() . "（价格上限）\n";
echo "预付金额: ￥300.00\n";
echo "退款金额: ￥0.00（预付金额等于最高限价）\n";

// ========================================
// 7. 清理测试数据
// ========================================

echo "\n=== 清理测试数据 ===\n";

// 移除事件监听器
$eventDispatcher->removeListener(RefundRequiredEvent::class, $refundHandler);

// 清理订单
$orders = [$order1, $order2, $order3, $order4];
foreach ($orders as $order) {
    $entityManager->remove($order);
}

// 清理产品
$entityManager->remove($product);
$entityManager->flush();

echo "测试数据已清理\n";

// ========================================
// 总结
// ========================================

echo "\n=== 预付费场景总结 ===\n";
echo "1. 短途出行：实际费用 < 预付金额 → 需要退款\n";
echo "2. 中途出行：实际费用 < 预付金额 → 需要退款\n";
echo "3. 长途出行：实际费用 > 预付金额 → 需要补款\n";
echo "4. 超长使用：触发价格上限，预付金额正好等于上限\n";
echo "\n重要提示:\n";
echo "- RefundRequiredEvent 事件会在需要退款时自动触发\n";
echo "- 可以监听此事件来处理实际的退款业务\n";
echo "- 补款需要在订单结束后主动检查并处理\n";
echo "- 建议设置合理的预付金额，减少退款和补款的频率\n";

echo "\n示例运行完成！\n";
