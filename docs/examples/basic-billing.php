<?php

/**
 * 基础计费示例
 *
 * 这个示例展示了如何使用 Duration Billing Bundle 进行基本的时长计费操作
 */

use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\RoundingMode;
use Tourze\DurationBillingBundle\Exception\OrderAlreadyEndedException;
use Tourze\DurationBillingBundle\Exception\ProductNotFoundException;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Service\DurationBillingServiceInterface;

// 假设这些服务已经通过依赖注入获得
/** @var EntityManagerInterface $entityManager */
/** @var DurationBillingServiceInterface $billingService */

// ========================================
// 1. 创建计费产品
// ========================================

echo "=== 创建计费产品 ===\n";

$product = new DurationBillingProduct();
$product->setName('标准会议室');
$product->setDescription('可容纳10人的标准会议室，配备投影仪和白板');
$product->setActive(true);

// 设置按小时计费规则：每小时100元，向上取整
$pricingRule = new HourlyPricingRule(
    hourlyPrice: 100.0,
    roundingMode: RoundingMode::ROUND_UP
);
$product->setPricingRule($pricingRule);

// 设置免费时长和价格限制
$product->setFreeMinutes(15);      // 前15分钟免费
$product->setMinAmount(50.0);      // 最低收费50元
$product->setMaxAmount(800.0);     // 最高收费800元

// 保存产品
$entityManager->persist($product);
$entityManager->flush();

echo "产品创建成功！产品ID: {$product->getId()}\n\n";

// ========================================
// 2. 开始计费
// ========================================

echo "=== 开始计费 ===\n";

try {
    $order = $billingService->startBilling(
        productId: $product->getId(),
        userId: 'USER_123',
        externalOrderCode: 'MEETING_ROOM_001',
        prepaidAmount: 200.0  // 预付200元
    );

    echo "计费开始成功！\n";
    echo "订单编号: {$order->getOrderCode()}\n";
    echo "开始时间: {$order->getStartedAt()->format('Y-m-d H:i:s')}\n";
    echo "预付金额: ￥{$order->getPrepaidAmount()}\n\n";
} catch (ProductNotFoundException $e) {
    echo "错误：产品不存在\n";
    exit(1);
}

// ========================================
// 3. 模拟使用一段时间
// ========================================

echo "=== 模拟使用2小时45分钟 ===\n";
// 在实际应用中，这里会是真实的时间流逝
// 这里我们通过直接修改数据库来模拟时间流逝
$startedAt = clone $order->getStartedAt();
$startedAt->modify('-2 hours 45 minutes');

$updateSql = 'UPDATE duration_billing_order SET started_at = :started_at WHERE order_code = :order_code';
$stmt = $entityManager->getConnection()->prepare($updateSql);
$stmt->executeStatement([
    'started_at' => $startedAt->format('Y-m-d H:i:s'),
    'order_code' => $order->getOrderCode(),
]);

echo "时间已调整，模拟使用了2小时45分钟\n\n";

// ========================================
// 4. 查询当前费用
// ========================================

echo "=== 查询当前费用 ===\n";

$currentPrice = $billingService->calculateCurrentPrice($order->getOrderCode());
echo "当前费用: ￥{$currentPrice}\n\n";

// ========================================
// 5. 结束计费
// ========================================

echo "=== 结束计费 ===\n";

try {
    $result = $billingService->endBilling($order->getOrderCode());

    echo "计费结束成功！\n";
    echo "使用时长: {$result->getBillableMinutes()}分钟\n";
    echo "免费时长: {$result->getFreeMinutes()}分钟\n";
    echo "基础价格: ￥{$result->getBasePrice()}\n";
    echo "最终价格: ￥{$result->getFinalPrice()}\n";
    echo "折扣金额: ￥{$result->getDiscount()}\n";

    $order = $result->getOrder();
    echo "\n支付信息:\n";
    echo "预付金额: ￥{$order->getPrepaidAmount()}\n";
    echo "最终金额: ￥{$order->getFinalAmount()}\n";

    if ($order->getRefundAmount() > 0) {
        echo "需要退款: ￥{$order->getRefundAmount()}\n";
    } elseif ($order->requiresAdditionalPayment()) {
        $additionalPayment = $order->getFinalAmount() - $order->getPrepaidAmount();
        echo "需要补款: ￥{$additionalPayment}\n";
    } else {
        echo "无需退款或补款\n";
    }
} catch (OrderAlreadyEndedException $e) {
    echo "错误：订单已经结束\n";
}

// ========================================
// 6. 价格计算说明
// ========================================

echo "\n=== 价格计算说明 ===\n";
echo "使用时长: 2小时45分钟 (165分钟)\n";
echo "免费时长: 15分钟\n";
echo "计费时长: 150分钟 = 2.5小时\n";
echo "计费规则: 每小时100元，向上取整\n";
echo "基础价格: 3小时 × 100元 = 300元\n";
echo "价格限制: 最低50元，最高800元\n";
echo "最终价格: 300元（在限制范围内）\n";
echo "预付200元，需要补款100元\n";

// ========================================
// 7. 清理示例数据
// ========================================

echo "\n=== 清理示例数据 ===\n";

// 删除创建的订单和产品
$entityManager->remove($order);
$entityManager->remove($product);
$entityManager->flush();

echo "示例数据已清理\n";
echo "\n示例运行完成！\n";
