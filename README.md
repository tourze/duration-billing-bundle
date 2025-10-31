# Duration Billing Bundle

[English](README.md) | [中文](README.zh-CN.md)

一个 Symfony Bundle，用于按时长计费的商品和订单管理。

## 特性

- 支持 **按时长计费**，适用于停车场收费、共享充电宝等场景
- 支持 **预付费模式**，可处理预付金额不足的情况
- 支持 **计费暂停**，用于处理异常情况或特殊需求
- 支持 **多种定价规则**，包括阶梯定价和按小时定价
- 支持 **状态机管理**，订单状态转换规范化
- 提供 **完整的测试覆盖**，90%+ 的测试覆盖率保证

## 安装

通过 Composer 安装：

```bash
composer require tourze/duration-billing-bundle
```

在 `config/bundles.php` 中注册 Bundle：

```php
return [
    // ...
    Tourze\DurationBillingBundle\DurationBillingBundle::class => ['all' => true],
];
```

## 使用

### 1. 创建计费商品

```php
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Enum\RoundingMode;

// 创建计费商品
$product = new DurationBillingProduct();
$product->setName('充电宝A');
$product->setDescription('共享充电宝商品A');
$product->setActive(true);

// 设置定价规则：每小时 100 元
$rule = new HourlyPricingRule(100.0, RoundingMode::ROUND_UP);
$product->setPricingRule($rule);

// 设置限制条件
$product->setFreeMinutes(30);        // 免费 30 分钟
$product->setMinAmount(50.0);        // 最低收费 50 元
$product->setMaxAmount(1000.0);      // 最高收费 1000 元

$entityManager->persist($product);
$entityManager->flush();
```

### 2. 开始计费

```php
use Tourze\DurationBillingBundle\Service\DurationBillingServiceInterface;

class BillingController extends AbstractController
{
    public function start(
        DurationBillingServiceInterface $billingService,
        int $productId
    ): Response {
        try {
            $order = $billingService->startBilling(
                $productId,
                $this->getUser()->getId(),
                'USER_ORDER_001',  // 外部订单号
                200.0              // 预付金额
            );
            
            return $this->json([
                'success' => true,
                'order_code' => $order->getOrderCode(),
                'started_at' => $order->getStartedAt()->format('Y-m-d H:i:s'),
            ]);
        } catch (ProductNotFoundException $e) {
            return $this->json(['error' => '商品不存在'], 404);
        }
    }
}
```

### 3. 结束计费

```php
public function end(
    DurationBillingServiceInterface $billingService,
    string $orderCode
): Response {
    try {
        $result = $billingService->endBilling($orderCode);
        
        return $this->json([
            'success' => true,
            'billing_minutes' => $result->getBillableMinutes(),
            'free_minutes' => $result->getFreeMinutes(),
            'base_price' => $result->getBasePrice(),
            'final_price' => $result->getFinalPrice(),
            'discount' => $result->getDiscount(),
            'refund_amount' => $result->getOrder()->getRefundAmount(),
        ]);
    } catch (OrderNotFoundException $e) {
        return $this->json(['error' => '订单不存在'], 404);
    } catch (OrderAlreadyEndedException $e) {
        return $this->json(['error' => '订单已结束'], 400);
    }
}
```

## 高级特性

### 阶梯定价

```php
use Tourze\DurationBillingBundle\PricingRule\TieredPricingRule;
use Tourze\DurationBillingBundle\ValueObject\PriceTier;

// 阶梯定价规则
$tiers = [
    new PriceTier(0, 60, 100.0),      // 0-60分钟：100元/小时
    new PriceTier(60, 180, 80.0),     // 60-180分钟：80元/小时
    new PriceTier(180, null, 60.0),   // 180分钟后：60元/小时
];

$rule = new TieredPricingRule($tiers);
$product->setPricingRule($rule);
```

### 订单暂停

```php
// 暂停订单计费
$billingService->freezeBilling($orderCode);

// 恢复订单计费
$billingService->resumeBilling($orderCode);

// 查找超时的暂停订单
$expiredOrders = $billingService->findExpiredFrozenOrders(
    freezeMinutes: 30,  // 暂停超过30分钟的订单
    limit: 100
);
```

### 事件监听

```php
use Tourze\DurationBillingBundle\Event\BillingEndedEvent;
use Tourze\DurationBillingBundle\Event\RefundRequiredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BillingEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BillingEndedEvent::class => 'onBillingEnded',
            RefundRequiredEvent::class => 'onRefundRequired',
        ];
    }
    
    public function onBillingEnded(BillingEndedEvent $event): void
    {
        $order = $event->getOrder();
        $priceResult = $event->getPriceResult();
        
        // 发送通知
        $this->notificationService->sendBillingNotification(
            $order->getUserId(),
            $priceResult->getFinalPrice()
        );
    }
    
    public function onRefundRequired(RefundRequiredEvent $event): void
    {
        $order = $event->getOrder();
        $refundAmount = $event->getRefundAmount();
        
        // 处理退款
        $this->refundService->processRefund(
            $order->getExternalOrderCode(),
            $refundAmount
        );
    }
}
```

## 服务

Bundle 提供以下核心服务：

- `duration_billing.service` - 计费服务
- `duration_billing.state_machine` - 订单状态机
- `duration_billing.price_calculator` - 价格计算器
- `duration_billing.order_repository` - 订单仓储
- `duration_billing.product_repository` - 商品仓储

## 定价规则

支持以下定价规则类型：

- 按小时计费规则
- 阶梯定价规则
- 自定义定价规则
- 价格限制设置

## 异常

Bundle 定义了以下异常类：

- `DurationBillingException` - 基础异常类
- `ProductNotFoundException` - 商品不存在
- `OrderNotFoundException` - 订单不存在
- `OrderAlreadyEndedException` - 订单已结束
- `InvalidOrderStateException` - 无效的订单状态
- `InvalidPricingRuleException` - 无效的定价规则
- `NegativeBillingTimeException` - 负计费时长
- `InvalidPrepaidAmountException` - 无效的预付金额

## 测试

运行测试：

```bash
# 单元测试
vendor/bin/phpunit --testsuite unit

# 集成测试
vendor/bin/phpunit --testsuite integration

# 全部测试
vendor/bin/phpunit
```

## 许可证

MIT License

## 贡献

欢迎提交 Issue 和 Pull Request！