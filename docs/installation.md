# 安装指南

## 系统要求

- PHP 8.2 或更高版本
- Symfony 6.4 或更高版本
- Doctrine ORM 2.16 或更高版本
- Composer 2.0 或更高版本

## 安装步骤

### 1. 通过 Composer 安装

```bash
composer require tourze/duration-billing-bundle
```

### 2. 注册 Bundle

在 `config/bundles.php` 文件中添加：

```php
<?php

return [
    // ... 其他 bundles
    Tourze\DurationBillingBundle\DurationBillingBundle::class => ['all' => true],
];
```

### 3. 配置数据库实体

创建或更新数据库架构：

```bash
# 创建迁移文件
php bin/console make:migration

# 执行迁移
php bin/console doctrine:migrations:migrate
```

### 4. 验证安装

运行以下命令确认 Bundle 已正确安装：

```bash
# 查看已注册的服务
php bin/console debug:container duration_billing

# 查看路由（如果有）
php bin/console debug:router | grep duration_billing
```

## 依赖说明

### 必需依赖

- **doctrine/orm**: 用于数据持久化
- **symfony/event-dispatcher**: 用于事件驱动架构
- **tourze/doctrine-snowflake-bundle**: 用于生成分布式 ID
- **tourze/doctrine-timestamp-bundle**: 用于自动管理时间戳
- **tourze/bundle-dependency**: 用于管理 Bundle 依赖

### 开发依赖

- **phpunit/phpunit**: 用于单元测试和集成测试
- **phpstan/phpstan**: 用于静态代码分析
- **friendsofphp/php-cs-fixer**: 用于代码风格检查

## 配置文件

Bundle 使用默认配置，无需额外配置即可使用。如需自定义配置，可创建：

```yaml
# config/packages/duration_billing.yaml
duration_billing:
    # 未来版本将支持的配置选项
    # default_free_minutes: 30
    # default_rounding_mode: round_up
    # price_precision: 2
```

## 故障排除

### 问题：Class not found 错误

**解决方案**：
1. 确保运行了 `composer dump-autoload`
2. 清除缓存：`php bin/console cache:clear`

### 问题：数据库表未创建

**解决方案**：
1. 检查 Doctrine 配置是否正确
2. 确保实体映射路径包含 Bundle 的实体
3. 运行 `php bin/console doctrine:schema:validate`

### 问题：服务未找到

**解决方案**：
1. 确保 Bundle 已在 `bundles.php` 中注册
2. 清除缓存并预热：
   ```bash
   php bin/console cache:clear
   php bin/console cache:warmup
   ```

### 问题：依赖冲突

**解决方案**：
1. 检查 composer.json 中的版本约束
2. 运行 `composer why-not tourze/duration-billing-bundle`
3. 更新依赖：`composer update --with-dependencies`

## 生产环境注意事项

1. **性能优化**：
   - 启用 OPcache
   - 使用 APCu 缓存
   - 配置适当的数据库索引

2. **监控建议**：
   - 监控长时间运行的订单
   - 设置冻结订单的定期检查
   - 监控事件处理的性能

3. **数据备份**：
   - 定期备份订单数据
   - 实施数据归档策略
   - 考虑使用读写分离

## 下一步

安装完成后，请参考以下文档：
- [配置指南](configuration.md) - 了解可用的配置选项
- [使用指南](usage.md) - 学习如何使用 Bundle
- [示例代码](examples/) - 查看实际使用示例