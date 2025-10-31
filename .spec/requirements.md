# Duration Billing Bundle - EARS 需求规范

## 包概述
**包名称**: duration-billing-bundle  
**版本**: 0.1.0  
**类型**: Symfony Bundle  
**目标**: 为停车场收费、共享充电宝、共享单车等基于时长计费的业务场景提供通用解决方案

## 核心价值主张
- **统一抽象**: 标准化时长计费业务逻辑
- **规则引擎**: 支持复杂、可配置的计费规则  
- **事件驱动**: 完整的业务事件系统集成
- **高性能**: 优化的价格计算引擎

## 核心业务场景

### 场景一：停车场收费（含冻结期）
- 用户入场时开始计时
- 离场扫码时进入冻结期，停止计时并锁定金额
- 冻结期内用户完成支付，订单结束
- 冻结期超时则恢复计时，继续计费
- 支持分时段收费（如工作日/周末不同价格）
- 支持最低收费和最高收费限制

### 场景二：共享充电宝（预付费模式）
- 用户租借时预付押金和预估费用
- 开始计时，正常计费逻辑
- 归还时结束计时，计算实际费用
- 预付费充足时直接完成，不足时需要补款
- 预付费有剩余时标记需要退费
- 支持阶梯计费（如前1小时2元，后续每小时1元）

### 场景三：共享单车（标准计费）
- 用户开锁时开始计时
- 锁车时结束计时，计算骑行费用
- 支持起步价+时长费用模式
- 支持会员价格优惠

### 场景四：混合模式
- 某些商品支持预付费模式
- 某些商品支持冻结期机制
- 灵活的商品配置支持不同业务需求

## EARS 功能需求

### 普遍性需求 (Ubiquitous Requirements)

**FR-1 商品管理**
- 包必须提供 `DurationBillingProduct` 实体，用于定义时长计费商品
- 包必须支持商品的基本信息管理（名称、描述、状态）
- 包必须支持商品计费规则的数据库存储和检索
- 包必须使用 `SnowflakeKeyAware` Trait 生成唯一商品ID

**FR-2 订单管理**
- 包必须提供 `DurationBillingOrder` 实体，用于管理计费订单
- 包必须支持订单的完整生命周期管理
- 包必须使用 `SnowflakeKeyAware` Trait 生成唯一订单ID
- 包必须将所有计费结果存储到订单实体中

**FR-3 价格计算**
- 包必须提供 `PriceCalculator` 服务，用于同步价格计算
- 包必须支持固定单价、阶梯计费、分时段计费模式
- 包必须支持免费时长、最低收费、封顶价格限制
- 包必须支持分钟级的时间计算精度

**FR-4 计费规则引擎**
- 包必须支持计费规则的数据库存储和动态加载
- 包必须提供 `PricingRuleInterface` 接口，用于自定义计费规则
- 包必须支持时间舍入模式配置（向上/向下取整）

### 事件驱动需求 (Event-Driven Requirements)

**FR-5 订单创建**
- 当调用 `DurationBillingService::startBilling()` 时，包必须创建新订单并记录开始时间
- 当订单创建成功时，包必须派发 `BillingStartedEvent` 事件
- 当订单创建失败时，包必须抛出相应的业务异常

**FR-6 订单结束**
- 当调用 `DurationBillingService::endBilling()` 时，包必须停止计时并计算最终费用
- 当订单结束时，包必须派发 `BillingEndedEvent` 事件
- 当订单已结束时，包必须抛出 `OrderAlreadyEndedException` 异常

**FR-7 订单取消**
- 当调用 `DurationBillingService::cancelBilling()` 时，包必须将订单状态设为已取消
- 当订单取消时，包必须派发 `BillingCancelledEvent` 事件

### 状态驱动需求 (State-Driven Requirements)

**FR-8 订单状态管理**
- 当订单处于 `ACTIVE` 状态时，包必须允许结束、取消或冻结操作
- 当订单处于 `FROZEN` 状态时，包必须拒绝取消操作，但允许完成或恢复计费
- 当订单处于 `PREPAID` 状态时，包必须允许正常的结束操作
- 当订单处于 `PENDING_PAYMENT` 状态时，包必须等待补款确认
- 当订单处于 `COMPLETED` 状态时，包必须拒绝任何状态变更操作
- 当订单处于 `CANCELLED` 状态时，包必须拒绝任何状态变更操作

### 条件性需求 (Conditional Requirements)

**FR-9 计费规则验证**
- 如果商品未配置计费规则，那么包必须抛出 `InvalidPricingRuleException`
- 如果计费时长为负数，那么包必须抛出 `NegativeBillingTimeException`
- 如果商品不存在，那么包必须抛出 `ProductNotFoundException`
- 如果订单不存在，那么包必须抛出 `OrderNotFoundException`

**FR-10 时间处理**
- 如果未指定结束时间，那么包必须使用当前时间作为结束时间
- 如果计费时长不足一个计费单位，那么包必须根据舍入模式处理
- 如果涉及跨时区计费，那么包必须使用配置的默认时区

**FR-11 查询参数验证**
- 如果查询参数无效，那么包必须抛出 `InvalidArgumentException`
- 如果查询结果数量可能很大，那么包必须支持限制返回数量
- 如果用户ID为空或无效，那么包必须抛出相应的参数异常

**FR-12 冻结期功能**
- 当调用 `freezeBilling()` 时，包必须停止计时并锁定当前应付金额
- 当冻结期超时时，包必须自动恢复计时并派发 `FreezeExpiredEvent` 事件
- 当冻结期内完成支付时，包必须派发 `FreezeCompletedEvent` 事件
- 冻结期时长必须根据商品配置的 `freeze_minutes` 参数确定

**FR-13 预付费功能**
- 当创建预付费订单时，包必须记录预付金额和第三方交易ID
- 当预付费订单结束时，包必须计算实际费用与预付费的差额
- 如果实际费用超过预付费，包必须将订单状态设为 `PENDING_PAYMENT`
- 如果预付费有剩余，包必须计算退费金额并派发 `RefundRequiredEvent` 事件

### 可选性需求 (Optional Requirements)

**FR-15 订单查询功能**
- 包必须提供 `findOrderById()` 方法，根据订单ID查找特定订单
- 包必须提供 `findActiveOrdersByUser()` 方法，查找用户的活跃订单
- 包必须提供 `findOrdersByStatus()` 方法，根据状态查询订单列表
- 当查询不存在的订单时，包必须返回 `null` 而不是抛出异常
- 当查询结果为空时，包必须返回空数组而不是 `null`

### 可选性需求 (Optional Requirements)

**FR-16 扩展支持**  
- 在需要自定义计费规则的情况下，包必须提供 `PricingRuleInterface` 扩展点
- 在需要自定义事件处理的情况下，包必须支持 Symfony 事件监听器注册
- 在需要元数据存储的情况下，包必须支持订单和商品的 `metadata` 字段
- 在需要冻结期功能的情况下，包必须支持商品级的冻结期配置
- 在需要预付费模式的情况下，包必须支持预付费订单创建和管理
- 在需要分页查询的情况下，包必须提供相应的分页参数支持

## EARS 技术需求

### 普遍性技术需求

**TR-1 环境兼容性**
- 包必须支持 PHP 8.2 及以上版本
- 包必须支持 Symfony 6.4+ 和 7.0+ 框架
- 包必须支持 MySQL 8.0+ 和 PostgreSQL 13+ 数据库

**TR-2 依赖管理**
- 包必须依赖 `tourze/doctrine-snowflake-bundle` 用于ID生成
- 包必须使用 Doctrine ORM 进行数据持久化
- 包必须集成 Symfony 依赖注入容器

**TR-3 Bundle结构**
- 包必须遵循 Symfony Bundle 标准结构
- 包必须提供标准的 `DurationBillingExtension` 配置扩展
- 包必须提供数据库迁移脚本支持

### 事件驱动技术需求

**TR-4 事件系统**
- 当业务操作完成时，包必须通过 Symfony EventDispatcher 派发相应事件
- 当派发事件时，包必须包含完整的上下文数据
- 当事件处理失败时，包必须记录错误但不中断主流程

### 条件性技术需求

**TR-5 性能要求**
- 如果单次价格计算请求，那么响应时间必须 < 100ms
- 如果数据库查询涉及订单表，那么必须使用适当的索引优化
- 如果计费规则复杂，那么必须在内存中缓存解析结果

**TR-6 并发处理**
- 如果应用需要处理并发订单创建，那么并发控制由接入方负责实现
- 如果需要分布式ID生成，那么必须使用 `SnowflakeKeyAware` Trait
- 如果出现数据竞争，那么包必须使用数据库事务保证一致性

### 可选性技术需求

**TR-7 扩展机制**
- 在需要自定义计费算法的情况下，包必须支持 `PricingRuleInterface` 实现
- 在需要自定义存储的情况下，包必须支持Repository接口替换
- 在需要性能监控的情况下，包必须支持事件监听器集成

## EARS 质量需求

### 普遍性质量需求

**QR-1 代码质量**
- 包必须通过 PHPStan Level 8 静态分析检查
- 包必须遵循 PSR-12 代码规范
- 包必须达到 90% 以上的单元测试覆盖率
- 包必须达到 80% 以上的集成测试覆盖率

**QR-2 文档要求**
- 包必须提供完整的 API 文档
- 包必须提供使用示例和最佳实践指南
- 包必须提供数据库架构说明文档

### 条件性质量需求

**QR-3 性能基准**
- 如果进行性能测试，那么单次价格计算必须在 100ms 内完成
- 如果进行负载测试，那么必须支持每秒 1000+ 次计费操作
- 如果涉及数据库操作，那么查询优化必须通过性能分析验证

## API 接口规范

### 核心服务接口
```php
interface DurationBillingServiceInterface
{
    // ========== 创建方法 - 返回实体 ==========
    
    /**
     * 开始计费 - 创建标准订单并开始计时
     */
    public function startBilling(string $userId, string $productId, array $metadata = []): DurationBillingOrder;
    
    /**
     * 开始预付费计费 - 创建预付费订单
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
     * 冻结计费 - 进入冻结期，停止计时
     */
    public function freezeBilling(DurationBillingOrder $order, ?\DateTimeImmutable $freezeTime = null): DurationBillingOrder;
    
    /**
     * 结束计费 - 停止计时并计算费用 
     */
    public function endBilling(DurationBillingOrder $order, ?\DateTimeImmutable $endTime = null): DurationBillingOrder;
    
    /**
     * 恢复计费 - 从冻结状态恢复计时
     */
    public function resumeBilling(DurationBillingOrder $order, ?\DateTimeImmutable $resumeTime = null): DurationBillingOrder;
    
    /**
     * 补款确认 - 确认预付费不足订单的补款
     */
    public function confirmAdditionalPayment(DurationBillingOrder $order, float $additionalAmount, string $transactionId): DurationBillingOrder;
    
    /**
     * 取消订单 - 取消进行中的计费
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
     * 根据状态查询订单列表
     */
    public function findOrdersByStatus(OrderStatus $status, int $limit = 50): array;
    
    // ========== 工具方法 ==========
    
    /**
     * 价格计算 - 根据商品和时长计算价格
     */
    public function calculatePrice(string $productId, int $minutes): PriceResult;
}
```

### 订单状态枚举
```php
enum OrderStatus: string
{
    case ACTIVE = 'active';                 // 计费中
    case FROZEN = 'frozen';                 // 冻结期
    case PREPAID = 'prepaid';               // 预付费模式  
    case PENDING_PAYMENT = 'pending_payment'; // 等待补款
    case COMPLETED = 'completed';           // 已完成
    case CANCELLED = 'cancelled';           // 已取消
}
```

### 计费规则接口
```php
interface PricingRuleInterface
{
    public function calculatePrice(int $minutes): float;
    public function getDescription(): string;
    public function serialize(): array;
    public static function deserialize(array $data): self;
}
```

## 使用示例

### 标准计费流程
```php
// 注入服务
/** @var DurationBillingServiceInterface $billingService */

// 开始计费
$order = $billingService->startBilling(
    userId: 'user_12345',
    productId: 'parking_zone_a',
    metadata: ['vehicle_plate' => '京A12345']
);

// 结束计费 - 传入实体对象  
$completedOrder = $billingService->endBilling($order);
echo "计费金额: " . $completedOrder->getTotalAmount();
```

### 冻结期计费流程
```php
// 停车场扫码出场场景
$order = $billingService->startBilling('user_12345', 'parking_zone_a');

// 扫码时冻结订单 - 传入实体对象
$frozenOrder = $billingService->freezeBilling($order);
echo "应付金额: " . $frozenOrder->getTotalAmount();

// 用户支付完成
$completedOrder = $billingService->endBilling($frozenOrder);

// 或者冻结超时，需要先查找订单再恢复计费
// $order = $billingService->findOrderById($orderId);
// $resumedOrder = $billingService->resumeBilling($order);
```

### 预付费计费流程
```php
// 共享充电宝预付费场景
$prepaidOrder = $billingService->startPrepaidBilling(
    userId: 'user_12345',
    productId: 'powerbank_type_a', 
    prepaidAmount: 20.00,
    transactionId: 'txn_abc123',
    metadata: ['device_id' => 'pb_001']
);

// 归还时结束计费 - 传入实体对象
$completedOrder = $billingService->endBilling($prepaidOrder);

// 检查是否需要补款
if ($completedOrder->getStatus() === OrderStatus::PENDING_PAYMENT) {
    // 需要补款
    $additionalAmount = $completedOrder->getTotalAmount() - $completedOrder->getPrepaidAmount();
    // 用户完成补款后确认 - 传入实体对象
    $finalOrder = $billingService->confirmAdditionalPayment(
        $completedOrder, 
        $additionalAmount, 
        'txn_def456'
    );
} elseif ($completedOrder->getRefundAmount() > 0) {
    // 需要退费（由外部系统处理）
    echo "需要退费: " . $completedOrder->getRefundAmount();
}
```

### 订单查询示例
```php
// 根据ID查找订单
$order = $billingService->findOrderById('order_12345');
if ($order === null) {
    throw new \Exception('订单不存在');
}

// 查找用户的活跃订单
$activeOrders = $billingService->findActiveOrdersByUser('user_12345');
foreach ($activeOrders as $order) {
    echo "活跃订单: {$order->getId()} - 状态: {$order->getStatus()->value}";
}

// 查找指定状态的订单
$frozenOrders = $billingService->findOrdersByStatus(OrderStatus::FROZEN, 20);
foreach ($frozenOrders as $order) {
    // 检查冻结是否超时，需要恢复计费
    if ($this->isFreezeExpired($order)) {
        $billingService->resumeBilling($order);
    }
}
```

### 事件监听示例
```php
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
    
    public function onBillingEnded(BillingEndedEvent $event): void
    {
        // 处理计费完成后的业务逻辑
        $order = $event->getOrder();
        if ($order->getStatus() === OrderStatus::PENDING_PAYMENT) {
            $this->notificationService->sendPaymentRequest($order);
        } else {
            $this->paymentService->processBilling($order);
        }
    }
    
    public function onOrderFrozen(OrderFrozenEvent $event): void
    {
        // 发送支付提醒
        $order = $event->getOrder();
        $this->notificationService->sendPaymentReminder($order);
    }
    
    public function onFreezeExpired(FreezeExpiredEvent $event): void
    {
        // 冻结期超时，恢复计费
        $order = $event->getOrder();
        $this->logger->info("Order {$order->getId()} freeze expired, billing resumed");
    }
    
    public function onRefundRequired(RefundRequiredEvent $event): void
    {
        // 需要退费，通知外部支付系统
        $order = $event->getOrder();
        $this->paymentService->processRefund($order);
    }
}
```

## EARS 验收标准

### 功能验收
- **FR-1 到 FR-16**: 所有功能需求必须通过对应的单元测试验证
- **API一致性**: 所有操作方法必须统一使用实体对象参数，保证类型安全
- **查询功能**: 所有查询方法必须正确处理空结果和异常情况
- **状态转换**: 所有订单状态转换必须符合业务规则，无非法状态转换
- **冻结期机制**: 冻结期超时和恢复机制必须准确工作
- **预付费处理**: 预付费、补款、退费逻辑必须正确计算
- **事件系统**: 所有业务事件必须在正确时机派发，包含完整上下文数据
- **异常处理**: 所有业务异常必须有对应的测试用例验证

### 技术验收  
- **TR-1 到 TR-7**: 所有技术需求必须通过集成测试验证
- **数据库**: 数据迁移脚本必须在目标数据库上测试通过
- **Symfony集成**: Bundle必须在标准Symfony应用中正确加载和工作

### 质量验收
- **QR-1**: 代码质量检查必须全部通过，无警告无错误
- **QR-2**: 文档必须完整准确，示例代码可执行
- **QR-3**: 性能基准必须在指定环境下达标

### 最终验收门禁
1. ✅ 所有EARS需求（FR-1 到 FR-16, TR-1 到 TR-7, QR-1 到 QR-3）对应的测试用例100%通过
2. ✅ API设计一致性验证：所有操作方法统一使用实体对象参数，无字符串ID混用
3. ✅ 查询功能完整性测试：所有查询方法正确处理空结果、异常参数等边界情况
4. ✅ 订单状态机转换逻辑完全正确，包含所有6种状态的合法转换
5. ✅ 冻结期和预付费两种特殊模式的端到端测试全部通过
6. ✅ PHPStan Level 8 分析零错误零警告，特别关注类型安全检查
7. ✅ 测试覆盖率达到质量标准要求（单元测试≥90%，集成测试≥80%）
8. ✅ 在真实业务场景下的端到端测试通过（停车场、充电宝、单车场景）
9. ✅ 性能测试在生产类似环境下达标（价格计算<100ms，1000+QPS支持）
10. ✅ 事件系统完整性验证，所有新增事件正确派发和处理