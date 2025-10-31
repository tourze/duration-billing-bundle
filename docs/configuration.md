# 配置指南

## 概述

Duration Billing Bundle 采用约定优于配置的原则，提供合理的默认配置。大多数情况下，您无需任何额外配置即可使用。

## 服务配置

### 主要服务

Bundle 自动注册以下服务：

#### 1. 计费服务 (DurationBillingService)

```php
use Tourze\DurationBillingBundle\Service\DurationBillingServiceInterface;

class MyService
{
    public function __construct(
        private DurationBillingServiceInterface $billingService
    ) {
    }
}
```

**别名**: `duration_billing.service`

#### 2. 订单状态机 (OrderStateMachine)

```php
use Tourze\DurationBillingBundle\Service\OrderStateMachine;

// 通过依赖注入
public function __construct(
    private OrderStateMachine $stateMachine
) {
}
```

**别名**: `duration_billing.state_machine`

#### 3. 价格计算器 (PriceCalculator)

```php
use Tourze\DurationBillingBundle\Service\PriceCalculator;

// 计算订单价格
$priceResult = $priceCalculator->calculate($order);
```

**别名**: `duration_billing.price_calculator`

### 仓储服务

#### 订单仓储

```php
use Tourze\DurationBillingBundle\Repository\DurationBillingOrderRepositoryInterface;

public function __construct(
    private DurationBillingOrderRepositoryInterface $orderRepository
) {
}
```

**别名**: `duration_billing.order_repository`

#### 产品仓储

```php
use Tourze\DurationBillingBundle\Repository\DurationBillingProductRepositoryInterface;

public function __construct(
    private DurationBillingProductRepositoryInterface $productRepository
) {
}
```

**别名**: `duration_billing.product_repository`

## 实体配置

### Doctrine 映射

Bundle 的实体已配置好 Doctrine 映射。确保您的 Doctrine 配置包含 Bundle 的实体路径：

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        mappings:
            DurationBillingBundle:
                is_bundle: true
                type: attribute
                dir: 'Entity'
                prefix: 'Tourze\DurationBillingBundle\Entity'
                alias: DurationBilling
```

### 数据库表

Bundle 会创建以下数据库表：

- `duration_billing_product` - 计费产品表
- `duration_billing_order` - 计费订单表

## 事件配置

### 可监听的事件

Bundle 派发以下事件，您可以创建监听器来扩展功能：

```php
namespace App\EventListener;

use Tourze\DurationBillingBundle\Event\BillingStartedEvent;
use Tourze\DurationBillingBundle\Event\BillingEndedEvent;
use Tourze\DurationBillingBundle\Event\OrderFrozenEvent;
use Tourze\DurationBillingBundle\Event\FreezeExpiredEvent;
use Tourze\DurationBillingBundle\Event\RefundRequiredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BillingEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BillingStartedEvent::class => 'onBillingStarted',
            BillingEndedEvent::class => 'onBillingEnded',
            OrderFrozenEvent::class => 'onOrderFrozen',
            FreezeExpiredEvent::class => 'onFreezeExpired',
            RefundRequiredEvent::class => 'onRefundRequired',
        ];
    }
    
    public function onBillingStarted(BillingStartedEvent $event): void
    {
        // 处理计费开始事件
        $order = $event->getOrder();
        // ... 您的业务逻辑
    }
    
    // ... 其他事件处理方法
}
```

## 高级配置

### 自定义计费规则

创建自定义计费规则：

```php
namespace App\PricingRule;

use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;

class CustomPricingRule implements PricingRuleInterface
{
    public function calculate(int $minutes): float
    {
        // 实现您的计费逻辑
        return $minutes * 1.5;
    }
    
    public function validate(): bool
    {
        // 验证规则配置
        return true;
    }
    
    public function serialize(): array
    {
        // 序列化规则配置
        return ['type' => 'custom', 'rate' => 1.5];
    }
    
    public static function deserialize(array $data): self
    {
        // 反序列化规则配置
        return new self();
    }
}
```

### 扩展实体

如需扩展 Bundle 提供的实体，可以使用 Doctrine 的继承映射：

```php
namespace App\Entity;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'app_billing_order')]
class CustomBillingOrder extends DurationBillingOrder
{
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $customField = null;
    
    // getter 和 setter
}
```

## 性能优化

### 1. 查询优化

使用仓储提供的优化方法：

```php
// 批量查询活跃订单
$activeOrders = $orderRepository->findActiveOrders(limit: 100);

// 使用分页
$orders = $orderRepository->findByUser(
    userId: $userId,
    offset: 0,
    limit: 20
);
```

### 2. 缓存策略

建议为产品信息添加缓存：

```php
use Symfony\Contracts\Cache\CacheInterface;

class CachedProductRepository
{
    public function __construct(
        private DurationBillingProductRepositoryInterface $repository,
        private CacheInterface $cache
    ) {
    }
    
    public function find(int $id): ?DurationBillingProduct
    {
        return $this->cache->get(
            'product_' . $id,
            fn() => $this->repository->find($id)
        );
    }
}
```

### 3. 事件异步处理

对于耗时的事件处理，建议使用 Symfony Messenger：

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            'Tourze\DurationBillingBundle\Event\RefundRequiredEvent': async
```

## 环境特定配置

### 开发环境

```yaml
# config/packages/dev/duration_billing.yaml
services:
    # 开发环境特定服务配置
    Tourze\DurationBillingBundle\Service\DurationBillingService:
        arguments:
            $debug: true
```

### 测试环境

```yaml
# config/packages/test/duration_billing.yaml
services:
    # 测试环境使用内存数据库
    test.duration_billing.order_repository:
        class: App\Test\Repository\InMemoryOrderRepository
        decorates: Tourze\DurationBillingBundle\Repository\DurationBillingOrderRepository
```

## 安全配置

### 访问控制

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/billing/start, roles: ROLE_USER }
        - { path: ^/billing/end, roles: ROLE_USER }
        - { path: ^/billing/admin, roles: ROLE_ADMIN }
```

### 数据验证

Bundle 内置了数据验证，但您可以添加额外的验证规则：

```php
use Symfony\Component\Validator\Constraints as Assert;

class BillingRequest
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $productId;
    
    #[Assert\NotBlank]
    public string $userId;
    
    #[Assert\PositiveOrZero]
    public float $prepaidAmount = 0.0;
}
```

## 监控和日志

### 配置日志通道

```yaml
# config/packages/monolog.yaml
monolog:
    channels: ['billing']
    handlers:
        billing:
            type: stream
            path: '%kernel.logs_dir%/billing.log'
            level: info
            channels: ['billing']
```

### 使用日志

```php
use Psr\Log\LoggerInterface;

class CustomBillingService
{
    public function __construct(
        private LoggerInterface $billingLogger
    ) {
    }
    
    public function processBilling(): void
    {
        $this->billingLogger->info('Processing billing', [
            'order_id' => $orderId,
            'amount' => $amount,
        ]);
    }
}
```

## 故障排除配置

### 调试模式

启用详细的调试信息：

```php
# .env.local
DURATION_BILLING_DEBUG=true
```

### 性能分析

```yaml
# config/packages/dev/duration_billing.yaml
services:
    Tourze\DurationBillingBundle\Service\DurationBillingService:
        decorates: duration_billing.service
        decoration_inner_name: duration_billing.service.inner
        arguments:
            - '@duration_billing.service.inner'
            - '@profiler'
```