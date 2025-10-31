# Duration Billing Bundle - TDD任务分解

## 概述
本文档基于技术设计，使用测试驱动开发(TDD)方法分解实施任务。每个任务遵循红-绿-重构循环。

## 开发里程碑
- **里程碑 1**: 基础架构和异常体系 (第1天)
- **里程碑 2**: 枚举和值对象 (第2天)
- **里程碑 3**: 核心实体和仓储 (第3-4天)
- **里程碑 4**: 计费规则引擎 (第5-6天)
- **里程碑 5**: 订单状态机和服务层 (第7-8天)
- **里程碑 6**: 事件系统 (第9天)
- **里程碑 7**: Bundle集成和文档 (第10天)

---

## 基础架构任务

### 任务 1: 创建包结构和异常体系

#### 描述
建立包的基础结构，包括目录结构、命名空间、composer配置和基础异常类。

#### 验收标准（基于 EARS）
- 当运行 `composer install` 时，包必须正确加载
- 系统必须定义所有必需的异常类继承层次
- 如果抛出异常，那么必须包含有意义的错误消息

#### TDD 实施步骤
1. **红色阶段**：编写测试验证异常类的存在和继承关系
2. **绿色阶段**：创建目录结构和异常类
3. **重构阶段**：确保异常类遵循统一的构造函数模式

#### 测试场景
- 单元测试：验证每个异常类可以被实例化
- 单元测试：验证异常继承自 DurationBillingException
- 单元测试：验证异常消息和代码设置正确

#### 依赖关系
- 需要：无
- 阻塞：所有其他任务

#### 文件清单
```
src/
├── Exception/
│   ├── DurationBillingException.php
│   ├── ProductNotFoundException.php
│   ├── OrderNotFoundException.php
│   ├── OrderAlreadyEndedException.php
│   ├── InvalidOrderStateException.php
│   ├── InvalidPricingRuleException.php
│   ├── NegativeBillingTimeException.php
│   └── InvalidPrepaidAmountException.php
tests/
└── Unit/
    └── Exception/
        └── ExceptionHierarchyTest.php
```

---

## 枚举和值对象任务

### 任务 2: 实现订单状态和舍入模式枚举

#### 描述
创建 OrderStatus 和 RoundingMode 枚举，包含状态转换逻辑。

#### 验收标准（基于 EARS）
- 当订单状态为 ACTIVE 时，必须允许转换到 FROZEN、COMPLETED 或 CANCELLED
- 当订单状态为 COMPLETED 或 CANCELLED 时，必须拒绝任何状态转换
- 系统必须提供 isTerminal() 和 isActive() 辅助方法

#### TDD 实施步骤
1. **红色阶段**：编写测试验证所有状态转换规则
2. **绿色阶段**：实现枚举和 canTransitionTo() 方法
3. **重构阶段**：优化 match 表达式的可读性

#### 测试场景
- 单元测试：测试每个状态的合法转换
- 单元测试：测试非法状态转换返回 false
- 单元测试：测试 isTerminal() 和 isActive() 方法
- 边缘案例：测试无效的枚举值处理

#### 依赖关系
- 需要：任务 1
- 阻塞：任务 5, 任务 8

#### 文件清单
```
src/
├── Enum/
│   ├── OrderStatus.php
│   └── RoundingMode.php
tests/
└── Unit/
    └── Enum/
        ├── OrderStatusTest.php
        └── RoundingModeTest.php
```

---

### 任务 3: 实现价格结果和价格层级值对象

#### 描述
创建不可变的值对象 PriceResult 和 PriceTier，用于表示计算结果和价格层级。

#### 验收标准（基于 EARS）
- 值对象必须是不可变的（使用 readonly）
- 当创建 PriceResult 时，必须正确计算折扣
- 当检查价格层级包含性时，必须正确处理边界情况

#### TDD 实施步骤
1. **红色阶段**：编写测试验证值对象的不可变性和计算逻辑
2. **绿色阶段**：实现 readonly 类和必需的方法
3. **重构阶段**：优化计算逻辑的清晰度

#### 测试场景
- 单元测试：验证值对象创建后不能修改
- 单元测试：测试 PriceResult 折扣计算
- 单元测试：测试 PriceTier 包含性判断
- 单元测试：测试 getApplicableMinutes 计算
- 边缘案例：测试负数和零值处理

#### 依赖关系
- 需要：任务 1
- 阻塞：任务 6, 任务 7

#### 文件清单
```
src/
└── ValueObject/
    ├── PriceResult.php
    └── PriceTier.php
tests/
└── Unit/
    └── ValueObject/
        ├── PriceResultTest.php
        └── PriceTierTest.php
```

---

## 核心实体任务

### 任务 4: 实现 DurationBillingProduct 实体

#### 描述
创建商品实体，包含 Snowflake ID、时间戳管理和计费规则序列化。

#### 验收标准（基于 EARS）
- 实体必须使用 SnowflakeKeyAware 和 TimestampableAware traits
- 当设置计费规则时，必须正确序列化到 JSON
- 当获取计费规则时，必须正确反序列化

#### TDD 实施步骤
1. **红色阶段**：编写测试验证实体属性和计费规则管理
2. **绿色阶段**：实现实体类和 getter/setter
3. **重构阶段**：优化计费规则的延迟加载逻辑

#### 测试场景
- 单元测试：验证所有属性的 getter/setter
- 单元测试：测试计费规则序列化/反序列化
- 单元测试：验证 traits 正确使用
- 集成测试：测试 Doctrine 映射配置

#### 依赖关系
- 需要：任务 1
- 阻塞：任务 5, 任务 6, 任务 7

#### 文件清单
```
src/
└── Entity/
    └── DurationBillingProduct.php
tests/
└── Unit/
    └── Entity/
        └── DurationBillingProductTest.php
```

---

### 任务 5: 实现 DurationBillingOrder 实体

#### 描述
创建订单实体，包含状态管理、时间计算和关联关系。

#### 验收标准（基于 EARS）
- 实体必须通过 ManyToOne 关联 DurationBillingProduct
- 当计算实际计费分钟数时，必须减去冻结时间
- 当有预付费时，必须正确计算退费金额或补款需求

#### TDD 实施步骤
1. **红色阶段**：编写测试验证时间计算和金额计算逻辑
2. **绿色阶段**：实现实体和计算方法
3. **重构阶段**：优化时间差计算的准确性

#### 测试场景
- 单元测试：测试 getActualBillingMinutes() 各种场景
- 单元测试：测试 getRefundAmount() 计算
- 单元测试：测试 requiresAdditionalPayment() 判断
- 单元测试：验证实体关联关系
- 边缘案例：测试跨天、跨月的时间计算

#### 依赖关系
- 需要：任务 1, 任务 2, 任务 4
- 阻塞：任务 8, 任务 9, 任务 10

#### 文件清单
```
src/
└── Entity/
    └── DurationBillingOrder.php
tests/
└── Unit/
    └── Entity/
        └── DurationBillingOrderTest.php
```

---

## 计费规则引擎任务

### 任务 6: 实现计费规则接口和基础规则

#### 描述
创建 PricingRuleInterface 接口和 HourlyPricingRule 实现。

#### 验收标准（基于 EARS）
- 当使用向上取整模式时，0.1小时必须计费为1小时
- 系统必须支持序列化和反序列化规则配置
- 如果价格为负数，validate() 必须返回 false

#### TDD 实施步骤
1. **红色阶段**：编写测试验证不同舍入模式的计算结果
2. **绿色阶段**：实现接口和 HourlyPricingRule 类
3. **重构阶段**：使用 match 表达式优化舍入逻辑

#### 测试场景
- 单元测试：测试三种舍入模式的计算结果
- 单元测试：测试序列化/反序列化往返
- 单元测试：测试 validate() 方法
- 边缘案例：测试0分钟、负数分钟的处理

#### 依赖关系
- 需要：任务 1, 任务 2
- 阻塞：任务 7, 任务 11

#### 文件清单
```
src/
├── Contract/
│   └── PricingRuleInterface.php
└── PricingRule/
    └── HourlyPricingRule.php
tests/
└── Unit/
    └── PricingRule/
        └── HourlyPricingRuleTest.php
```

---

### 任务 7: 实现阶梯计费规则

#### 描述
创建 TieredPricingRule 类，支持分段计费。

#### 验收标准（基于 EARS）
- 当使用时长跨越多个层级时，必须正确计算每个层级的费用
- 如果层级配置无效（如负数价格），validate() 必须返回 false
- 系统必须支持无上限的最后一个层级

#### TDD 实施步骤
1. **红色阶段**：编写测试验证多层级计算逻辑
2. **绿色阶段**：实现阶梯计费算法
3. **重构阶段**：优化层级遍历和计算效率

#### 测试场景
- 单元测试：测试单层级计算
- 单元测试：测试多层级跨越计算
- 单元测试：测试无上限层级
- 单元测试：测试配置验证
- 边缘案例：测试层级边界值计算

#### 依赖关系
- 需要：任务 1, 任务 3, 任务 6
- 阻塞：任务 11

#### 文件清单
```
src/
└── PricingRule/
    └── TieredPricingRule.php
tests/
└── Unit/
    └── PricingRule/
        └── TieredPricingRuleTest.php
```

---

## 服务层任务

### 任务 8: 实现订单状态机

#### 描述
创建 OrderStateMachine 服务，管理订单状态转换。

#### 验收标准（基于 EARS）
- 当尝试非法状态转换时，必须抛出 InvalidOrderStateException
- 系统必须提供 canFreeze()、canResume() 等辅助方法
- 如果订单已完成，所有操作检查必须返回 false

#### TDD 实施步骤
1. **红色阶段**：编写测试验证状态转换规则和异常
2. **绿色阶段**：实现状态机逻辑
3. **重构阶段**：提取状态检查的通用逻辑

#### 测试场景
- 单元测试：测试合法状态转换
- 单元测试：测试非法状态转换抛出异常
- 单元测试：测试各种 can* 方法
- 边缘案例：测试终态订单的操作

#### 依赖关系
- 需要：任务 1, 任务 2, 任务 5
- 阻塞：任务 10

#### 文件清单
```
src/
└── Service/
    └── OrderStateMachine.php
tests/
└── Unit/
    └── Service/
        └── OrderStateMachineTest.php
```

---

### 任务 9: 实现价格计算器

#### 描述
创建 PriceCalculator 服务，处理免费时长和金额限制。

#### 验收标准（基于 EARS）
- 当计费时长小于免费时长时，最终价格必须为0
- 当基础价格低于最低金额时，必须应用最低金额
- 当基础价格高于最高金额时，必须应用最高金额

#### TDD 实施步骤
1. **红色阶段**：编写测试验证各种价格计算场景
2. **绿色阶段**：实现计算逻辑和限制应用
3. **重构阶段**：优化计算流程的清晰度

#### 测试场景
- 单元测试：测试免费时长计算
- 单元测试：测试最低金额限制
- 单元测试：测试最高金额限制
- 单元测试：测试价格详情分解
- 边缘案例：测试0分钟计费

#### 依赖关系
- 需要：任务 1, 任务 3, 任务 4
- 阻塞：任务 10

#### 文件清单
```
src/
└── Service/
    └── PriceCalculator.php
tests/
└── Unit/
    └── Service/
        └── PriceCalculatorTest.php
```

---

### 任务 10: 实现仓储接口和基础仓储

#### 描述
创建仓储接口和 Doctrine 实现，处理数据持久化。

#### 验收标准（基于 EARS）
- 仓储必须提供标准的 CRUD 操作
- 当查询活跃订单时，必须包含所有非终态状态
- 系统必须支持分页和排序

#### TDD 实施步骤
1. **红色阶段**：编写测试定义仓储接口行为
2. **绿色阶段**：实现 Doctrine 仓储类
3. **重构阶段**：优化查询构建器的使用

#### 测试场景
- 单元测试：测试仓储接口定义
- 集成测试：测试数据库查询
- 集成测试：测试复杂查询条件
- 边缘案例：测试空结果处理

#### 依赖关系
- 需要：任务 1, 任务 4, 任务 5
- 阻塞：任务 11

#### 文件清单
```
src/
└── Repository/
    ├── DurationBillingProductRepositoryInterface.php
    ├── DurationBillingOrderRepositoryInterface.php
    ├── DurationBillingProductRepository.php
    └── DurationBillingOrderRepository.php
tests/
├── Unit/
│   └── Repository/
│       └── RepositoryInterfaceTest.php
└── Integration/
    └── Repository/
        ├── DurationBillingProductRepositoryTest.php
        └── DurationBillingOrderRepositoryTest.php
```

---

### 任务 11: 实现核心计费服务

#### 描述
创建 DurationBillingService，实现所有业务操作。

#### 验收标准（基于 EARS）
- 当开始计费时，必须创建状态为 ACTIVE 的订单
- 当冻结订单时，必须记录冻结时间并计算当前金额
- 当结束计费时，必须派发相应事件
- 如果商品不存在，必须抛出 ProductNotFoundException

#### TDD 实施步骤
1. **红色阶段**：编写测试覆盖所有业务方法
2. **绿色阶段**：逐个实现业务方法
3. **重构阶段**：提取通用逻辑，优化代码结构

#### 测试场景
- 单元测试：测试 startBilling 创建订单
- 单元测试：测试 freezeBilling 状态转换
- 单元测试：测试 endBilling 价格计算
- 单元测试：测试查询方法
- 集成测试：测试完整的订单生命周期
- 边缘案例：测试各种异常情况

#### 依赖关系
- 需要：任务 1-10
- 阻塞：任务 12, 任务 13

#### 文件清单
```
src/
└── Service/
    ├── DurationBillingServiceInterface.php
    └── DurationBillingService.php
tests/
├── Unit/
│   └── Service/
│       └── DurationBillingServiceTest.php
└── Integration/
    └── Service/
        └── DurationBillingServiceIntegrationTest.php
```

---

## 事件系统任务

### 任务 12: 实现事件类

#### 描述
创建所有业务事件类，支持事件驱动架构。

#### 验收标准（基于 EARS）
- 所有事件必须包含订单、商品和发生时间
- BillingEndedEvent 必须包含价格计算结果
- RefundRequiredEvent 必须包含退费金额

#### TDD 实施步骤
1. **红色阶段**：编写测试验证事件属性和构造
2. **绿色阶段**：实现事件类继承体系
3. **重构阶段**：优化基类的共享逻辑

#### 测试场景
- 单元测试：验证事件构造和属性访问
- 单元测试：测试事件继承关系
- 单元测试：验证特殊事件的额外属性

#### 依赖关系
- 需要：任务 1, 任务 3, 任务 4, 任务 5
- 阻塞：任务 13

#### 文件清单
```
src/
└── Event/
    ├── DurationBillingEvent.php
    ├── BillingStartedEvent.php
    ├── BillingEndedEvent.php
    ├── OrderFrozenEvent.php
    ├── FreezeExpiredEvent.php
    └── RefundRequiredEvent.php
tests/
└── Unit/
    └── Event/
        └── EventHierarchyTest.php
```

---

### 任务 13: 集成事件派发

#### 描述
在核心服务中集成事件派发机制。

#### 验收标准（基于 EARS）
- 当订单创建时，必须派发 BillingStartedEvent
- 当订单结束且需要退费时，必须派发 RefundRequiredEvent
- 系统必须在事务提交后派发事件

#### TDD 实施步骤
1. **红色阶段**：编写测试验证事件派发
2. **绿色阶段**：在服务方法中添加事件派发
3. **重构阶段**：确保事件派发的时机正确

#### 测试场景
- 单元测试：验证各操作派发正确的事件
- 单元测试：验证事件包含正确的数据
- 集成测试：测试事件监听器接收事件

#### 依赖关系
- 需要：任务 11, 任务 12
- 阻塞：任务 14

#### 文件清单
```
更新文件：
- src/Service/DurationBillingService.php
- tests/Unit/Service/DurationBillingServiceTest.php
```

---

## Bundle集成任务

### 任务 14: 实现 Bundle 扩展类

#### 描述
创建 DurationBillingExtension，注册服务和配置。

#### 验收标准（基于 EARS）
- Bundle 必须自动注册所有服务
- 系统必须支持环境变量配置
- 服务必须正确注入依赖

#### TDD 实施步骤
1. **红色阶段**：编写测试验证服务注册
2. **绿色阶段**：实现扩展类和服务配置
3. **重构阶段**：优化服务定义

#### 测试场景
- 集成测试：验证服务容器包含所有服务
- 集成测试：测试服务依赖注入
- 集成测试：测试环境变量读取

#### 依赖关系
- 需要：任务 1-13
- 阻塞：任务 15

#### 文件清单
```
src/
├── DependencyInjection/
│   └── DurationBillingExtension.php
├── Resources/
│   └── config/
│       └── services.php
└── DurationBillingBundle.php
tests/
└── Integration/
    └── BundleIntegrationTest.php
```

---

### 任务 15: 创建文档和示例

#### 描述
编写使用文档、API 参考和集成示例。

#### 验收标准（基于 EARS）
- 文档必须包含安装和配置说明
- 系统必须提供完整的使用示例
- 如果用户遇到问题，必须有故障排除指南

#### TDD 实施步骤
1. **红色阶段**：创建示例代码并验证其工作
2. **绿色阶段**：编写完整的文档
3. **重构阶段**：改进文档的组织和清晰度

#### 测试场景
- 手动测试：验证示例代码可运行
- 代码审查：确保文档准确完整

#### 依赖关系
- 需要：任务 1-14
- 阻塞：无

#### 文件清单
```
README.md
docs/
├── installation.md
├── configuration.md
├── usage.md
└── examples/
    ├── basic-billing.php
    ├── freeze-billing.php
    └── prepaid-billing.php
```

---

## 任务统计

- **基础架构**：1个任务
- **枚举和值对象**：2个任务
- **核心实体**：2个任务
- **计费规则引擎**：2个任务
- **服务层**：4个任务
- **事件系统**：2个任务
- **Bundle集成**：2个任务

**总计**：15个任务，预计10个工作日完成

## 质量保证

每个任务完成后必须通过：
1. PHPUnit 测试全部通过
2. PHPStan Level 8 无错误
3. PHP-CS-Fixer 代码风格检查
4. 测试覆盖率 ≥ 90%

## 持续集成建议

建议设置 GitHub Actions 自动运行：
- 单元测试和集成测试
- 静态分析 (PHPStan)
- 代码风格检查
- 测试覆盖率报告