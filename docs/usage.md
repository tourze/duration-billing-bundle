# 使用指南

## 基本概念

### 核心组件

1. **产品 (Product)**: 定义计费规则和限制的商品
2. **订单 (Order)**: 记录实际的计费过程
3. **计费规则 (Pricing Rule)**: 决定如何计算费用
4. **状态机 (State Machine)**: 管理订单的生命周期

### 订单生命周期

```
ACTIVE (活跃) ──┬──> FROZEN (冻结) ──> ACTIVE (恢复)
                │
                ├──> COMPLETED (完成)
                │
                └──> CANCELLED (取消)
```

## 基础操作

### 1. 产品管理

#### 创建产品

```php
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Enum\RoundingMode;
use Doctrine\ORM\EntityManagerInterface;

class ProductService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }
    
    public function createHourlyProduct(): DurationBillingProduct
    {
        $product = new DurationBillingProduct();
        $product->setName('标准会议室');
        $product->setDescription('可容纳10人的标准会议室');
        $product->setActive(true);
        
        // 设置按小时计费规则
        $rule = new HourlyPricingRule(
            hourlyPrice: 150.0,
            roundingMode: RoundingMode::ROUND_UP
        );
        $product->setPricingRule($rule);
        
        // 设置限制
        $product->setFreeMinutes(15);      // 前15分钟免费
        $product->setMinAmount(75.0);      // 最低收费75元
        $product->setMaxAmount(1500.0);    // 最高收费1500元
        
        $this->entityManager->persist($product);
        $this->entityManager->flush();
        
        return $product;
    }
}
```

#### 更新产品

```php
public function updateProduct(int $productId, array $data): void
{
    $product = $this->productRepository->find($productId);
    
    if (!$product) {
        throw new ProductNotFoundException($productId);
    }
    
    // 更新基本信息
    if (isset($data['name'])) {
        $product->setName($data['name']);
    }
    
    if (isset($data['active'])) {
        $product->setActive($data['active']);
    }
    
    // 更新计费规则
    if (isset($data['hourly_price'])) {
        $rule = new HourlyPricingRule(
            $data['hourly_price'],
            $product->getPricingRule()->getRoundingMode()
        );
        $product->setPricingRule($rule);
    }
    
    $this->entityManager->flush();
}
```

### 2. 订单操作

#### 开始计费

```php
use Tourze\DurationBillingBundle\Service\DurationBillingServiceInterface;

class BillingController extends AbstractController
{
    #[Route('/billing/start', methods: ['POST'])]
    public function startBilling(
        Request $request,
        DurationBillingServiceInterface $billingService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        try {
            $order = $billingService->startBilling(
                productId: $data['product_id'],
                userId: $this->getUser()->getId(),
                externalOrderCode: $data['external_order_code'] ?? null,
                prepaidAmount: $data['prepaid_amount'] ?? 0.0
            );
            
            return $this->json([
                'success' => true,
                'data' => [
                    'order_code' => $order->getOrderCode(),
                    'started_at' => $order->getStartedAt()->format('c'),
                    'product_name' => $order->getProduct()->getName(),
                    'prepaid_amount' => $order->getPrepaidAmount(),
                ]
            ]);
        } catch (ProductNotFoundException $e) {
            return $this->json(['error' => '产品不存在'], 404);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

#### 查询订单状态

```php
#[Route('/billing/status/{orderCode}', methods: ['GET'])]
public function getOrderStatus(
    string $orderCode,
    DurationBillingServiceInterface $billingService
): JsonResponse {
    try {
        $order = $billingService->getOrder($orderCode);
        
        // 计算当前费用（如果订单仍在进行中）
        $currentPrice = null;
        if ($order->getStatus()->isActive()) {
            $currentPrice = $billingService->calculateCurrentPrice($orderCode);
        }
        
        return $this->json([
            'success' => true,
            'data' => [
                'order_code' => $order->getOrderCode(),
                'status' => $order->getStatus()->value,
                'started_at' => $order->getStartedAt()->format('c'),
                'ended_at' => $order->getEndedAt()?->format('c'),
                'actual_minutes' => $order->getActualBillingMinutes(),
                'current_price' => $currentPrice,
                'prepaid_amount' => $order->getPrepaidAmount(),
                'final_amount' => $order->getFinalAmount(),
            ]
        ]);
    } catch (OrderNotFoundException $e) {
        return $this->json(['error' => '订单不存在'], 404);
    }
}
```

#### 结束计费

```php
#[Route('/billing/end/{orderCode}', methods: ['POST'])]
public function endBilling(
    string $orderCode,
    DurationBillingServiceInterface $billingService
): JsonResponse {
    try {
        $result = $billingService->endBilling($orderCode);
        $order = $result->getOrder();
        
        $response = [
            'success' => true,
            'data' => [
                'order_code' => $order->getOrderCode(),
                'status' => 'completed',
                'duration' => [
                    'total_minutes' => $order->getActualBillingMinutes(),
                    'billable_minutes' => $result->getBillableMinutes(),
                    'free_minutes' => $result->getFreeMinutes(),
                ],
                'pricing' => [
                    'base_price' => $result->getBasePrice(),
                    'final_price' => $result->getFinalPrice(),
                    'discount' => $result->getDiscount(),
                    'breakdown' => $result->getBreakdown(),
                ],
                'payment' => [
                    'prepaid_amount' => $order->getPrepaidAmount(),
                    'final_amount' => $order->getFinalAmount(),
                    'refund_amount' => $order->getRefundAmount(),
                    'additional_payment' => $order->requiresAdditionalPayment() 
                        ? $order->getFinalAmount() - $order->getPrepaidAmount()
                        : 0,
                ]
            ]
        ];
        
        return $this->json($response);
    } catch (OrderNotFoundException $e) {
        return $this->json(['error' => '订单不存在'], 404);
    } catch (OrderAlreadyEndedException $e) {
        return $this->json(['error' => '订单已结束'], 400);
    } catch (InvalidOrderStateException $e) {
        return $this->json(['error' => '订单状态无效'], 400);
    }
}
```

### 3. 冻结与恢复

#### 冻结订单

```php
#[Route('/billing/freeze/{orderCode}', methods: ['POST'])]
public function freezeOrder(
    string $orderCode,
    DurationBillingServiceInterface $billingService
): JsonResponse {
    try {
        $billingService->freezeBilling($orderCode);
        
        return $this->json([
            'success' => true,
            'message' => '订单已冻结',
            'data' => [
                'order_code' => $orderCode,
                'status' => 'frozen',
                'frozen_at' => (new \DateTime())->format('c'),
            ]
        ]);
    } catch (InvalidOrderStateException $e) {
        return $this->json(['error' => '无法冻结该订单'], 400);
    }
}
```

#### 恢复订单

```php
#[Route('/billing/resume/{orderCode}', methods: ['POST'])]
public function resumeOrder(
    string $orderCode,
    DurationBillingServiceInterface $billingService
): JsonResponse {
    try {
        $billingService->resumeBilling($orderCode);
        
        return $this->json([
            'success' => true,
            'message' => '订单已恢复',
            'data' => [
                'order_code' => $orderCode,
                'status' => 'active',
                'resumed_at' => (new \DateTime())->format('c'),
            ]
        ]);
    } catch (InvalidOrderStateException $e) {
        return $this->json(['error' => '无法恢复该订单'], 400);
    }
}
```

### 4. 批量操作

#### 查询用户的所有订单

```php
#[Route('/billing/orders', methods: ['GET'])]
public function getUserOrders(
    Request $request,
    DurationBillingServiceInterface $billingService
): JsonResponse {
    $userId = $this->getUser()->getId();
    $page = $request->query->getInt('page', 1);
    $limit = $request->query->getInt('limit', 20);
    $status = $request->query->get('status'); // active, completed, cancelled
    
    $offset = ($page - 1) * $limit;
    
    // 根据状态过滤
    if ($status === 'active') {
        $orders = $billingService->findActiveOrdersByUser($userId, $offset, $limit);
    } else {
        $orders = $billingService->findOrdersByUser($userId, $offset, $limit);
    }
    
    $data = array_map(function($order) {
        return [
            'order_code' => $order->getOrderCode(),
            'product_name' => $order->getProduct()->getName(),
            'status' => $order->getStatus()->value,
            'started_at' => $order->getStartedAt()->format('c'),
            'ended_at' => $order->getEndedAt()?->format('c'),
            'final_amount' => $order->getFinalAmount(),
        ];
    }, $orders);
    
    return $this->json([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => count($orders),
        ]
    ]);
}
```

#### 处理超时的冻结订单

```php
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessExpiredFrozenOrdersCommand extends Command
{
    protected static $defaultName = 'billing:process-expired';
    
    public function __construct(
        private DurationBillingServiceInterface $billingService
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expiredOrders = $this->billingService->findExpiredFrozenOrders(
            freezeMinutes: 30,  // 冻结超过30分钟
            limit: 100
        );
        
        $output->writeln(sprintf('找到 %d 个超时的冻结订单', count($expiredOrders)));
        
        foreach ($expiredOrders as $order) {
            try {
                // 自动结束超时的订单
                $this->billingService->endBilling($order->getOrderCode());
                $output->writeln(sprintf('订单 %s 已自动结束', $order->getOrderCode()));
            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    '<error>处理订单 %s 失败: %s</error>',
                    $order->getOrderCode(),
                    $e->getMessage()
                ));
            }
        }
        
        return Command::SUCCESS;
    }
}
```

## 高级用法

### 自定义计费规则

#### 实现复杂的计费逻辑

```php
use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;

class WeekendPricingRule implements PricingRuleInterface
{
    public function __construct(
        private float $weekdayPrice,
        private float $weekendPrice,
        private \DateTimeInterface $startTime
    ) {
    }
    
    public function calculate(int $minutes): float
    {
        $endTime = (clone $this->startTime)->modify("+{$minutes} minutes");
        
        // 计算工作日和周末的分钟数
        $weekdayMinutes = 0;
        $weekendMinutes = 0;
        
        $current = clone $this->startTime;
        while ($current < $endTime) {
            if (in_array($current->format('w'), [0, 6])) {
                $weekendMinutes++;
            } else {
                $weekdayMinutes++;
            }
            $current->modify('+1 minute');
        }
        
        // 计算总价
        $weekdayHours = $weekdayMinutes / 60;
        $weekendHours = $weekendMinutes / 60;
        
        return ($weekdayHours * $this->weekdayPrice) + 
               ($weekendHours * $this->weekendPrice);
    }
    
    public function validate(): bool
    {
        return $this->weekdayPrice > 0 && $this->weekendPrice > 0;
    }
    
    public function serialize(): array
    {
        return [
            'type' => 'weekend',
            'weekday_price' => $this->weekdayPrice,
            'weekend_price' => $this->weekendPrice,
            'start_time' => $this->startTime->format('c'),
        ];
    }
    
    public static function deserialize(array $data): self
    {
        return new self(
            $data['weekday_price'],
            $data['weekend_price'],
            new \DateTime($data['start_time'])
        );
    }
}
```

### 事件处理

#### 实现业务逻辑扩展

```php
use Tourze\DurationBillingBundle\Event\BillingEndedEvent;
use Tourze\DurationBillingBundle\Event\RefundRequiredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BillingIntegrationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PaymentService $paymentService,
        private NotificationService $notificationService,
        private PointsService $pointsService
    ) {
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            BillingEndedEvent::class => [
                ['processPayment', 10],
                ['sendNotification', 0],
                ['updatePoints', -10],
            ],
            RefundRequiredEvent::class => 'processRefund',
        ];
    }
    
    public function processPayment(BillingEndedEvent $event): void
    {
        $order = $event->getOrder();
        $priceResult = $event->getPriceResult();
        
        if ($order->requiresAdditionalPayment()) {
            $additionalAmount = $order->getFinalAmount() - $order->getPrepaidAmount();
            
            $this->paymentService->charge(
                userId: $order->getUserId(),
                amount: $additionalAmount,
                reference: $order->getOrderCode(),
                description: '时长计费补款'
            );
        }
    }
    
    public function sendNotification(BillingEndedEvent $event): void
    {
        $order = $event->getOrder();
        $priceResult = $event->getPriceResult();
        
        $this->notificationService->send(
            userId: $order->getUserId(),
            template: 'billing_completed',
            data: [
                'order_code' => $order->getOrderCode(),
                'product_name' => $order->getProduct()->getName(),
                'duration' => $order->getActualBillingMinutes(),
                'amount' => $priceResult->getFinalPrice(),
            ]
        );
    }
    
    public function updatePoints(BillingEndedEvent $event): void
    {
        $order = $event->getOrder();
        $points = (int) ($order->getFinalAmount() / 10); // 每10元1积分
        
        $this->pointsService->addPoints(
            userId: $order->getUserId(),
            points: $points,
            reason: '消费积分',
            reference: $order->getOrderCode()
        );
    }
    
    public function processRefund(RefundRequiredEvent $event): void
    {
        $order = $event->getOrder();
        $refundAmount = $event->getRefundAmount();
        
        $this->paymentService->refund(
            userId: $order->getUserId(),
            amount: $refundAmount,
            originalReference: $order->getExternalOrderCode(),
            reason: '时长计费退款'
        );
    }
}
```

### 报表和分析

#### 生成使用报表

```php
class BillingReportService
{
    public function __construct(
        private DurationBillingOrderRepositoryInterface $orderRepository,
        private EntityManagerInterface $entityManager
    ) {
    }
    
    public function generateDailyReport(\DateTimeInterface $date): array
    {
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select([
            'p.name as product_name',
            'COUNT(o.id) as order_count',
            'SUM(o.actualMinutes) as total_minutes',
            'SUM(o.finalAmount) as total_revenue',
            'AVG(o.finalAmount) as avg_order_value',
        ])
        ->from(DurationBillingOrder::class, 'o')
        ->join('o.product', 'p')
        ->where('o.startedAt BETWEEN :start AND :end')
        ->andWhere('o.status = :status')
        ->setParameter('start', $startOfDay)
        ->setParameter('end', $endOfDay)
        ->setParameter('status', OrderStatus::COMPLETED)
        ->groupBy('p.id');
        
        return $qb->getQuery()->getResult();
    }
}
```

## 最佳实践

### 1. 错误处理

始终捕获并适当处理异常：

```php
try {
    $order = $billingService->startBilling($productId, $userId);
} catch (ProductNotFoundException $e) {
    // 产品不存在
} catch (InvalidPrepaidAmountException $e) {
    // 预付金额无效
} catch (\Exception $e) {
    // 其他错误
    $this->logger->error('计费失败', [
        'exception' => $e,
        'product_id' => $productId,
        'user_id' => $userId,
    ]);
}
```

### 2. 事务管理

确保数据一致性：

```php
use Doctrine\DBAL\Exception\RetryableException;

public function processOrder(string $orderCode): void
{
    $retries = 3;
    
    while ($retries > 0) {
        try {
            $this->entityManager->beginTransaction();
            
            // 执行业务逻辑
            $result = $this->billingService->endBilling($orderCode);
            $this->paymentService->processPayment($result);
            
            $this->entityManager->commit();
            break;
        } catch (RetryableException $e) {
            $this->entityManager->rollback();
            $retries--;
            
            if ($retries === 0) {
                throw $e;
            }
            
            usleep(100000); // 100ms
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
```

### 3. 性能优化

使用批量操作和缓存：

```php
// 批量查询
$orders = $this->orderRepository->findActiveOrders(limit: 1000);

// 预加载关联数据
$qb = $this->entityManager->createQueryBuilder();
$qb->select('o', 'p')
   ->from(DurationBillingOrder::class, 'o')
   ->leftJoin('o.product', 'p')
   ->where('o.userId = :userId')
   ->setParameter('userId', $userId);
```

### 4. 监控和日志

添加适当的日志记录：

```php
$this->logger->info('开始计费', [
    'order_code' => $order->getOrderCode(),
    'product_id' => $productId,
    'user_id' => $userId,
    'prepaid_amount' => $prepaidAmount,
]);
```