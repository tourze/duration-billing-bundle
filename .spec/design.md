# Duration Billing Bundle - 技术设计文档

## 技术概览

### 架构模式
- **领域驱动设计 (DDD)** - 将复杂的计费业务逻辑映射为清晰的领域模型
- **策略模式** - 可插拔的计费规则引擎设计
- **状态机模式** - 严格控制订单状态转换的业务规则
- **事件驱动架构** - 通过事件解耦核心业务与外部系统集成

### 核心设计原则
- **接口隔离** - 清晰定义公共API，隐藏内部实现细节
- **单一职责** - 每个组件专注于特定的业务职责
- **开闭原则** - 支持扩展新的计费规则，不修改现有代码
- **依赖倒置** - 依赖抽象接口，不依赖具体实现

### 技术栈决策
- **PHP 8.2+** - 利用现代PHP特性（枚举、联合类型、readonly属性）
- **Symfony 6.4+/7.0+** - Bundle集成、依赖注入、事件系统
- **Doctrine ORM** - 实体映射、数据持久化、查询优化
- **Carbon** - 时间处理和计算
- **SnowflakeBundle** - 分布式唯一ID生成
- **TimestampBundle** - 自动管理创建和更新时间

---

## 公共API设计

### 核心服务接口

```php
<?php

namespace Tourze\DurationBillingBundle\Service;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

interface DurationBillingServiceInterface
{
    // ========== 创建方法 - 返回实体 ==========
    
    /**
     * 开始标准计费
     * 
     * @throws ProductNotFoundException 商品不存在
     * @throws InvalidArgumentException 参数无效
     */
    public function startBilling(string $userId, string $productId, array $metadata = []): DurationBillingOrder;
    
    /**
     * 开始预付费计费
     * 
     * @throws ProductNotFoundException 商品不存在
     * @throws InvalidPrepaidAmountException 预付金额无效
     */
    public function startPrepaidBilling(
        string $userId, 
        string $productId, 
        float $prepaidAmount,
        string $transactionId,
        array $metadata = []
    ): DurationBillingOrder;
    
    // ========== 操作方法 - 接受实体对象 ==========
    
    /**
     * 冻结计费 - 停止计时并锁定金额
     * 
     * @throws OrderAlreadyEndedException 订单已结束
     * @throws InvalidOrderStateException 订单状态不允许冻结
     */
    public function freezeBilling(DurationBillingOrder $order, ?\DateTimeImmutable $freezeTime = null): DurationBillingOrder;
    
    /**
     * 结束计费 - 计算最终费用
     * 
     * @throws OrderAlreadyEndedException 订单已结束
     */
    public function endBilling(DurationBillingOrder $order, ?\DateTimeImmutable $endTime = null): DurationBillingOrder;
    
    /**
     * 恢复计费 - 从冻结状态恢复计时
     * 
     * @throws InvalidOrderStateException 订单状态不允许恢复
     */
    public function resumeBilling(DurationBillingOrder $order, ?\DateTimeImmutable $resumeTime = null): DurationBillingOrder;
    
    /**
     * 确认补款 - 预付费不足时的补款确认
     * 
     * @throws InvalidOrderStateException 订单状态不需要补款
     */
    public function confirmAdditionalPayment(DurationBillingOrder $order, float $additionalAmount, string $transactionId): DurationBillingOrder;
    
    /**
     * 取消订单
     * 
     * @throws OrderAlreadyEndedException 订单已结束
     * @throws InvalidOrderStateException 订单状态不允许取消
     */
    public function cancelBilling(DurationBillingOrder $order): DurationBillingOrder;
    
    // ========== 查询方法 ==========
    
    /**
     * 根据ID查找订单
     */
    public function findOrderById(string $orderId): ?DurationBillingOrder;
    
    /**
     * 查找用户的活跃订单
     */
    public function findActiveOrdersByUser(string $userId): array;
    
    /**
     * 根据状态查询订单
     */
    public function findOrdersByStatus(OrderStatus $status, int $limit = 50): array;
    
    // ========== 工具方法 ==========
    
    /**
     * 价格计算 - 不创建订单的纯计算
     */
    public function calculatePrice(string $productId, int $minutes): PriceResult;
}
```

### 计费规则接口

```php
<?php

namespace Tourze\DurationBillingBundle\Contract;

interface PricingRuleInterface
{
    /**
     * 根据时长计算价格（分钟为单位）
     */
    public function calculatePrice(int $minutes): float;
    
    /**
     * 获取规则描述（用于显示和调试）
     */
    public function getDescription(): string;
    
    /**
     * 序列化为数组（用于数据库存储）
     */
    public function serialize(): array;
    
    /**
     * 从数组反序列化（从数据库加载）
     */
    public static function deserialize(array $data): self;
    
    /**
     * 验证规则配置是否有效
     */
    public function validate(): bool;
}
```

---

## 内部架构

### 核心组件划分

```
DurationBillingBundle/
├── Entity/                    # 实体层
│   ├── DurationBillingProduct.php
│   └── DurationBillingOrder.php
├── Enum/                      # 枚举定义
│   ├── OrderStatus.php
│   └── RoundingMode.php
├── ValueObject/               # 值对象
│   ├── PriceResult.php
│   ├── PriceTier.php
│   └── BillingTimeRange.php
├── Service/                   # 服务层
│   ├── DurationBillingService.php
│   ├── PriceCalculator.php
│   └── OrderStateMachine.php
├── Repository/                # 仓储层
│   ├── DurationBillingProductRepository.php
│   └── DurationBillingOrderRepository.php
├── PricingRule/              # 计费规则实现
│   ├── HourlyPricingRule.php
│   ├── TieredPricingRule.php
│   └── TimePeriodPricingRule.php
├── Event/                    # 事件定义
│   ├── BillingStartedEvent.php
│   ├── BillingEndedEvent.php
│   └── OrderFrozenEvent.php
├── Exception/                # 异常定义
│   ├── ProductNotFoundException.php
│   ├── OrderNotFoundException.php
│   └── InvalidOrderStateException.php
└── DependencyInjection/      # Bundle集成
    └── DurationBillingExtension.php
```

### 领域模型设计

#### 实体设计

```php
<?php

namespace Tourze\DurationBillingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;
use Tourze\DurationBillingBundle\Repository\DurationBillingProductRepository;

#[ORM\Entity(repositoryClass: DurationBillingProductRepository::class)]
#[ORM\Table(name: 'duration_billing_products')]
class DurationBillingProduct
{
    use SnowflakeKeyAware;
    use TimestampableAware;
    
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;
    
    #[ORM\Column(type: 'json')]
    private array $pricingRuleData;
    
    #[ORM\Column(type: 'integer')]
    private int $freeMinutes = 0;
    
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $freezeMinutes = null;
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $minAmount = null;
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $maxAmount = null;
    
    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;
    
    #[ORM\Column(type: 'json')]
    private array $metadata = [];
    
    private ?PricingRuleInterface $pricingRule = null;
    
    // Getters and Setters...
    
    public function getPricingRule(): PricingRuleInterface
    {
        if ($this->pricingRule === null) {
            $ruleClass = $this->pricingRuleData['class'];
            $this->pricingRule = $ruleClass::deserialize($this->pricingRuleData);
        }
        return $this->pricingRule;
    }
    
    public function setPricingRule(PricingRuleInterface $rule): void
    {
        $this->pricingRule = $rule;
        $this->pricingRuleData = array_merge(
            $rule->serialize(),
            ['class' => get_class($rule)]
        );
    }
}
```

```php
<?php

namespace Tourze\DurationBillingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\Repository\DurationBillingOrderRepository;

#[ORM\Entity(repositoryClass: DurationBillingOrderRepository::class)]
#[ORM\Table(name: 'duration_billing_orders')]
#[ORM\Index(columns: ['user_id', 'status'])]
#[ORM\Index(columns: ['status', 'started_at'])]
class DurationBillingOrder
{
    use SnowflakeKeyAware;
    use TimestampableAware;
    
    #[ORM\Column(type: 'string', length: 36)]
    private string $userId;
    
    #[ORM\ManyToOne(targetEntity: DurationBillingProduct::class)]
    #[ORM\JoinColumn(nullable: false)]
    private DurationBillingProduct $product;
    
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;
    
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;
    
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $frozenAt = null;
    
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resumedAt = null;
    
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $billingMinutes = null;
    
    #[ORM\Column(type: 'integer')]
    private int $frozenMinutes = 0;
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $totalAmount = null;
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $prepaidAmount = null;
    
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $prepaidTransactionId = null;
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $additionalAmount = null;
    
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $additionalTransactionId = null;
    
    #[ORM\Column(type: 'string', enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::ACTIVE;
    
    #[ORM\Column(type: 'json')]
    private array $metadata = [];
    
    // Getters and Setters...
    
    public function getActualBillingMinutes(): int
    {
        if ($this->endedAt === null) {
            $endTime = new \DateTimeImmutable();
        } else {
            $endTime = $this->endedAt;
        }
        
        $totalMinutes = $this->startedAt->diff($endTime)->i + 
                       $this->startedAt->diff($endTime)->h * 60 + 
                       $this->startedAt->diff($endTime)->days * 24 * 60;
        
        // 减去冻结时间
        return max(0, $totalMinutes - $this->frozenMinutes);
    }
    
    public function getRefundAmount(): float
    {
        if ($this->prepaidAmount === null || $this->totalAmount === null) {
            return 0.0;
        }
        
        $totalPaid = $this->prepaidAmount + ($this->additionalAmount ?? 0.0);
        return max(0.0, $totalPaid - $this->totalAmount);
    }
    
    public function requiresAdditionalPayment(): bool
    {
        if ($this->prepaidAmount === null || $this->totalAmount === null) {
            return false;
        }
        
        return $this->totalAmount > $this->prepaidAmount;
    }
}
```

#### 枚举设计

```php
<?php

namespace Tourze\DurationBillingBundle\Enum;

enum OrderStatus: string
{
    case ACTIVE = 'active';
    case FROZEN = 'frozen';
    case PREPAID = 'prepaid';
    case PENDING_PAYMENT = 'pending_payment';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return match ($this) {
            self::ACTIVE => in_array($newStatus, [self::FROZEN, self::COMPLETED, self::CANCELLED]),
            self::FROZEN => in_array($newStatus, [self::COMPLETED, self::ACTIVE]),
            self::PREPAID => in_array($newStatus, [self::COMPLETED, self::PENDING_PAYMENT]),
            self::PENDING_PAYMENT => $newStatus === self::COMPLETED,
            self::COMPLETED, self::CANCELLED => false,
        };
    }
    
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED]);
    }
    
    public function isActive(): bool
    {
        return !$this->isTerminal();
    }
}

enum RoundingMode: string
{
    case UP = 'up';
    case DOWN = 'down';
    case NEAREST = 'nearest';
}
```

#### 值对象设计

```php
<?php

namespace Tourze\DurationBillingBundle\ValueObject;

final readonly class PriceResult
{
    public function __construct(
        public float $basePrice,
        public float $finalPrice,
        public int $billableMinutes,
        public int $freeMinutes,
        public array $breakdown = []
    ) {}
    
    public function getDiscount(): float
    {
        return $this->basePrice - $this->finalPrice;
    }
    
    public function hasDiscount(): bool
    {
        return $this->getDiscount() > 0;
    }
}

final readonly class PriceTier
{
    public function __construct(
        public int $fromMinutes,
        public ?int $toMinutes,
        public float $pricePerHour
    ) {}
    
    public function contains(int $minutes): bool
    {
        return $minutes >= $this->fromMinutes && 
               ($this->toMinutes === null || $minutes < $this->toMinutes);
    }
    
    public function getApplicableMinutes(int $totalMinutes): int
    {
        if (!$this->contains($totalMinutes)) {
            return 0;
        }
        
        $start = max($this->fromMinutes, 0);
        $end = $this->toMinutes ?? $totalMinutes;
        
        return min($totalMinutes, $end) - $start;
    }
}
```

---

## 计费规则引擎设计

### 策略模式实现

```php
<?php

namespace Tourze\DurationBillingBundle\PricingRule;

use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;
use Tourze\DurationBillingBundle\Enum\RoundingMode;

/**
 * 按小时计费规则
 */
class HourlyPricingRule implements PricingRuleInterface
{
    public function __construct(
        private float $pricePerHour,
        private RoundingMode $roundingMode = RoundingMode::UP
    ) {}
    
    public function calculatePrice(int $minutes): float
    {
        $hours = $minutes / 60;
        
        $roundedHours = match ($this->roundingMode) {
            RoundingMode::UP => ceil($hours),
            RoundingMode::DOWN => floor($hours),
            RoundingMode::NEAREST => round($hours),
        };
        
        return $roundedHours * $this->pricePerHour;
    }
    
    public function getDescription(): string
    {
        return sprintf('%.2f元/小时，%s取整', $this->pricePerHour, $this->roundingMode->value);
    }
    
    public function serialize(): array
    {
        return [
            'price_per_hour' => $this->pricePerHour,
            'rounding_mode' => $this->roundingMode->value,
        ];
    }
    
    public static function deserialize(array $data): self
    {
        return new self(
            $data['price_per_hour'],
            RoundingMode::from($data['rounding_mode'] ?? 'up')
        );
    }
    
    public function validate(): bool
    {
        return $this->pricePerHour >= 0;
    }
}

/**
 * 阶梯计费规则
 */
class TieredPricingRule implements PricingRuleInterface
{
    /**
     * @param PriceTier[] $tiers
     */
    public function __construct(
        private array $tiers
    ) {}
    
    public function calculatePrice(int $minutes): float
    {
        $totalPrice = 0.0;
        
        foreach ($this->tiers as $tier) {
            $applicableMinutes = $tier->getApplicableMinutes($minutes);
            if ($applicableMinutes > 0) {
                $hours = $applicableMinutes / 60;
                $totalPrice += $hours * $tier->pricePerHour;
            }
        }
        
        return $totalPrice;
    }
    
    public function getDescription(): string
    {
        $descriptions = [];
        foreach ($this->tiers as $tier) {
            $from = $tier->fromMinutes;
            $to = $tier->toMinutes === null ? '∞' : $tier->toMinutes . '分钟';
            $descriptions[] = sprintf(
                '%d-%s: %.2f元/小时',
                $from,
                $to,
                $tier->pricePerHour
            );
        }
        
        return '阶梯计费: ' . implode(', ', $descriptions);
    }
    
    public function serialize(): array
    {
        return [
            'tiers' => array_map(fn($tier) => [
                'from_minutes' => $tier->fromMinutes,
                'to_minutes' => $tier->toMinutes,
                'price_per_hour' => $tier->pricePerHour,
            ], $this->tiers),
        ];
    }
    
    public static function deserialize(array $data): self
    {
        $tiers = array_map(
            fn($tierData) => new PriceTier(
                $tierData['from_minutes'],
                $tierData['to_minutes'],
                $tierData['price_per_hour']
            ),
            $data['tiers']
        );
        
        return new self($tiers);
    }
    
    public function validate(): bool
    {
        if (empty($this->tiers)) {
            return false;
        }
        
        foreach ($this->tiers as $tier) {
            if ($tier->pricePerHour < 0) {
                return false;
            }
            if ($tier->fromMinutes < 0) {
                return false;
            }
            if ($tier->toMinutes !== null && $tier->toMinutes <= $tier->fromMinutes) {
                return false;
            }
        }
        
        return true;
    }
}
```

### 价格计算器

```php
<?php

namespace Tourze\DurationBillingBundle\Service;

use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

class PriceCalculator
{
    public function calculate(DurationBillingProduct $product, int $minutes): PriceResult
    {
        // 计算免费时长
        $freeMinutes = min($minutes, $product->getFreeMinutes());
        $billableMinutes = max(0, $minutes - $freeMinutes);
        
        // 使用计费规则计算基础价格
        $basePrice = $billableMinutes > 0 
            ? $product->getPricingRule()->calculatePrice($billableMinutes)
            : 0.0;
        
        // 应用最低/最高金额限制
        $finalPrice = $this->applyLimits($basePrice, $product);
        
        return new PriceResult(
            basePrice: $basePrice,
            finalPrice: $finalPrice,
            billableMinutes: $billableMinutes,
            freeMinutes: $freeMinutes,
            breakdown: [
                'total_minutes' => $minutes,
                'free_minutes' => $freeMinutes,
                'billable_minutes' => $billableMinutes,
                'base_price' => $basePrice,
                'min_amount' => $product->getMinAmount(),
                'max_amount' => $product->getMaxAmount(),
                'final_price' => $finalPrice,
            ]
        );
    }
    
    private function applyLimits(float $price, DurationBillingProduct $product): float
    {
        if ($product->getMinAmount() !== null) {
            $price = max($price, $product->getMinAmount());
        }
        
        if ($product->getMaxAmount() !== null) {
            $price = min($price, $product->getMaxAmount());
        }
        
        return $price;
    }
}
```

---

## 订单状态机设计

```php
<?php

namespace Tourze\DurationBillingBundle\Service;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\Exception\InvalidOrderStateException;

class OrderStateMachine
{
    public function transitionTo(DurationBillingOrder $order, OrderStatus $newStatus): void
    {
        $currentStatus = $order->getStatus();
        
        if (!$currentStatus->canTransitionTo($newStatus)) {
            throw new InvalidOrderStateException(
                sprintf(
                    'Cannot transition from %s to %s',
                    $currentStatus->value,
                    $newStatus->value
                )
            );
        }
        
        $order->setStatus($newStatus);
    }
    
    public function canFreeze(DurationBillingOrder $order): bool
    {
        return $order->getStatus() === OrderStatus::ACTIVE;
    }
    
    public function canResume(DurationBillingOrder $order): bool
    {
        return $order->getStatus() === OrderStatus::FROZEN;
    }
    
    public function canCancel(DurationBillingOrder $order): bool
    {
        return in_array($order->getStatus(), [OrderStatus::ACTIVE, OrderStatus::PREPAID]);
    }
    
    public function canEnd(DurationBillingOrder $order): bool
    {
        return $order->getStatus()->isActive();
    }
}
```

---

## 事件系统设计

### 事件定义

```php
<?php

namespace Tourze\DurationBillingBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;

abstract class DurationBillingEvent extends Event
{
    public function __construct(
        private DurationBillingOrder $order,
        private DurationBillingProduct $product,
        private \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {}
    
    public function getOrder(): DurationBillingOrder
    {
        return $this->order;
    }
    
    public function getProduct(): DurationBillingProduct
    {
        return $this->product;
    }
    
    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

class BillingStartedEvent extends DurationBillingEvent {}

class BillingEndedEvent extends DurationBillingEvent
{
    public function __construct(
        DurationBillingOrder $order,
        DurationBillingProduct $product,
        private PriceResult $priceResult,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {
        parent::__construct($order, $product, $occurredAt);
    }
    
    public function getPriceResult(): PriceResult
    {
        return $this->priceResult;
    }
}

class OrderFrozenEvent extends DurationBillingEvent {}

class FreezeExpiredEvent extends DurationBillingEvent {}

class RefundRequiredEvent extends DurationBillingEvent
{
    public function __construct(
        DurationBillingOrder $order,
        DurationBillingProduct $product,
        private float $refundAmount,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable()
    ) {
        parent::__construct($order, $product, $occurredAt);
    }
    
    public function getRefundAmount(): float
    {
        return $this->refundAmount;
    }
}
```

---

## 扩展机制设计

### 自定义计费规则

```php
<?php

// 用户可以实现自定义计费规则
class CustomDiscountPricingRule implements PricingRuleInterface
{
    public function __construct(
        private PricingRuleInterface $baseRule,
        private float $discountPercent
    ) {}
    
    public function calculatePrice(int $minutes): float
    {
        $basePrice = $this->baseRule->calculatePrice($minutes);
        return $basePrice * (1 - $this->discountPercent / 100);
    }
    
    // 实现其他接口方法...
}
```

### 事件监听器扩展

```php
<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\DurationBillingBundle\Event\BillingEndedEvent;
use Tourze\DurationBillingBundle\Event\RefundRequiredEvent;

class CustomBillingEventSubscriber implements EventSubscriberInterface
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
        // 自定义业务逻辑：发送账单、记录日志等
        $order = $event->getOrder();
        $this->invoiceService->generateInvoice($order);
    }
    
    public function onRefundRequired(RefundRequiredEvent $event): void
    {
        // 自定义退费处理
        $order = $event->getOrder();
        $this->paymentService->processRefund($order, $event->getRefundAmount());
    }
}
```

---

## 测试策略

### 单元测试示例

```php
<?php

namespace Tourze\DurationBillingBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Tourze\DurationBillingBundle\Service\PriceCalculator;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;

class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;
    
    protected function setUp(): void
    {
        $this->calculator = new PriceCalculator();
    }
    
    public function testCalculateWithFreeMinutes(): void
    {
        $product = new DurationBillingProduct();
        $product->setFreeMinutes(30);
        $product->setPricingRule(new HourlyPricingRule(10.0));
        
        $result = $this->calculator->calculate($product, 90);
        
        $this->assertEquals(30, $result->freeMinutes);
        $this->assertEquals(60, $result->billableMinutes);
        $this->assertEquals(10.0, $result->finalPrice);
    }
    
    public function testCalculateWithMinAmount(): void
    {
        $product = new DurationBillingProduct();
        $product->setMinAmount(5.0);
        $product->setPricingRule(new HourlyPricingRule(10.0));
        
        $result = $this->calculator->calculate($product, 15); // 0.25小时 = 2.5元
        
        $this->assertEquals(2.5, $result->basePrice);
        $this->assertEquals(5.0, $result->finalPrice); // 应用最低金额
    }
}
```

### 集成测试示例

```php
<?php

namespace Tourze\DurationBillingBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\DurationBillingBundle\Service\DurationBillingServiceInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\Enum\OrderStatus;

class DurationBillingServiceIntegrationTest extends KernelTestCase
{
    private DurationBillingServiceInterface $billingService;
    
    protected function setUp(): void
    {
        self::bootKernel();
        $this->billingService = static::getContainer()->get(DurationBillingServiceInterface::class);
    }
    
    public function testCompleteOrderLifecycle(): void
    {
        // 创建商品
        $product = $this->createTestProduct();
        
        // 开始计费
        $order = $this->billingService->startBilling('user_123', $product->getId());
        $this->assertEquals(OrderStatus::ACTIVE, $order->getStatus());
        
        // 冻结订单
        $frozenOrder = $this->billingService->freezeBilling($order);
        $this->assertEquals(OrderStatus::FROZEN, $frozenOrder->getStatus());
        
        // 完成订单
        $completedOrder = $this->billingService->endBilling($frozenOrder);
        $this->assertEquals(OrderStatus::COMPLETED, $completedOrder->getStatus());
        $this->assertNotNull($completedOrder->getTotalAmount());
    }
}
```

---

## 性能优化设计

### 缓存策略

```php
<?php

namespace Tourze\DurationBillingBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;

class CachedPriceCalculator
{
    public function __construct(
        private PriceCalculator $calculator,
        private CacheItemPoolInterface $cache,
        private int $cacheTtl = 3600
    ) {}
    
    public function calculate(DurationBillingProduct $product, int $minutes): PriceResult
    {
        $cacheKey = sprintf('price_%s_%d', $product->getId(), $minutes);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }
        
        $result = $this->calculator->calculate($product, $minutes);
        
        $cacheItem->set($result);
        $cacheItem->expiresAfter($this->cacheTtl);
        $this->cache->save($cacheItem);
        
        return $result;
    }
}
```

---

## 错误处理策略

### 异常层次设计

```php
<?php

namespace Tourze\DurationBillingBundle\Exception;

abstract class DurationBillingException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class ProductNotFoundException extends DurationBillingException {}
class OrderNotFoundException extends DurationBillingException {}
class OrderAlreadyEndedException extends DurationBillingException {}
class InvalidOrderStateException extends DurationBillingException {}
class InvalidPricingRuleException extends DurationBillingException {}
class NegativeBillingTimeException extends DurationBillingException {}
class InvalidPrepaidAmountException extends DurationBillingException {}
```

---

## 总结

### 设计决策总结

1. **架构模式**: 领域驱动设计 + 策略模式，确保业务逻辑清晰且可扩展
2. **API设计**: 统一使用实体对象参数，保证类型安全和一致性
3. **状态管理**: 严格的状态机控制，防止非法状态转换
4. **事件驱动**: 松耦合的业务集成，支持扩展和监控
5. **扩展机制**: 接口驱动的扩展点，支持自定义计费规则和业务逻辑

### 关键实体设计要点

1. **使用TimestampableAware**: 自动管理创建和更新时间
2. **使用SnowflakeKeyAware**: 分布式唯一ID生成
3. **ORM关联关系**: DurationBillingOrder通过ManyToOne关联DurationBillingProduct
4. **环境变量配置**: 所有配置通过$_ENV读取，不使用Configuration类

### 质量保证

- **测试覆盖**: 单元测试≥90%，集成测试≥80%
- **性能基准**: 价格计算<100ms，支持1000+QPS
- **代码质量**: PHPStan Level 8，PSR-12代码规范
- **类型安全**: 充分利用PHP 8.2+的类型系统