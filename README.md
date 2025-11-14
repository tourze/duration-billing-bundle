# Duration Billing Bundle

[English](README.md) | [中文](README.zh-CN.md)

A Symfony bundle for time-based billing management, designed for parking lots, shared power banks, shared bikes, and other duration-based billing scenarios.

## Features

- ✅ **Time-based Billing**: Precise duration-based billing for all types of sharing economy scenarios
- 💳 **Prepaid Mode**: Support for prepaid amounts with automatic handling of insufficient funds
- ⏸️ **Billing Pause**: Support for pausing and resuming order billing
- 📊 **Flexible Pricing**: Support for tiered pricing, hourly pricing, and various pricing strategies
- 🔄 **State Machine Management**: Comprehensive order state transition mechanism for standardized business flows
- 🧪 **High Test Coverage**: 90%+ test coverage ensuring code quality and stability
- 📡 **Event-driven**: Rich event system for business extension and integration

## Installation

Install via Composer:

```bash
composer require tourze/duration-billing-bundle
```

Register the bundle in your Symfony application:

```php
// config/bundles.php
return [
    // ...
    Tourze\DurationBillingBundle\DurationBillingBundle::class => ['all' => true],
];
```

## Quick Start

### 1. Create Billing Product

```php
use Tourze\DurationBillingBundle\Entity\DurationBillingProduct;
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Enum\RoundingMode;

// Create billing product
$product = new DurationBillingProduct();
$product->setName('Power Bank A');
$product->setDescription('Shared power bank product A');
$product->setActive(true);

// Set pricing rule: 100 per hour, round up
$rule = new HourlyPricingRule(100.0, RoundingMode::ROUND_UP);
$product->setPricingRule($rule);

// Set billing limits
$product->setFreeMinutes(30);        // First 30 minutes free
$product->setMinAmount(50.0);        // Minimum charge 50
$product->setMaxAmount(1000.0);      // Maximum charge 1000

$entityManager->persist($product);
$entityManager->flush();
```

### 2. Start Billing

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
                productId: $productId,
                userId: $this->getUser()->getId(),
                externalOrderCode: 'USER_ORDER_001',  // External order code
                prepaidAmount: 200.0                   // Prepaid amount
            );

            return $this->json([
                'success' => true,
                'order_code' => $order->getOrderCode(),
                'started_at' => $order->getStartTime()->format('Y-m-d H:i:s'),
            ]);
        } catch (ProductNotFoundException $e) {
            return $this->json(['error' => 'Product not found'], 404);
        }
    }
}
```

### 3. End Billing

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
        return $this->json(['error' => 'Order not found'], 404);
    } catch (OrderAlreadyEndedException $e) {
        return $this->json(['error' => 'Order already ended'], 400);
    }
}
```

## Advanced Features

### Tiered Pricing

```php
use Tourze\DurationBillingBundle\PricingRule\TieredPricingRule;
use Tourze\DurationBillingBundle\ValueObject\PriceTier;

// Create tiered pricing rule
$tiers = [
    new PriceTier(0, 60, 100.0),      // 0-60 minutes: 100 per hour
    new PriceTier(60, 180, 80.0),     // 60-180 minutes: 80 per hour
    new PriceTier(180, null, 60.0),   // 180+ minutes: 60 per hour
];

$rule = new TieredPricingRule($tiers);
$product->setPricingRule($rule);
```

### Order Pause and Resume

```php
// Pause order billing
$billingService->freezeBilling($orderCode);

// Resume order billing
$billingService->resumeBilling($orderCode);

// Find expired frozen orders
$expiredOrders = $billingService->findExpiredFrozenOrders(
    freezeMinutes: 30,  // Orders frozen for more than 30 minutes
    limit: 100
);
```

### Event Listening

```php
use Tourze\DurationBillingBundle\Event\BillingEndedEvent;
use Tourze\DurationBillingBundle\Event\RefundRequiredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BillingEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BillingStartedEvent::class => 'onBillingStarted',
            BillingEndedEvent::class => 'onBillingEnded',
            RefundRequiredEvent::class => 'onRefundRequired',
            OrderFrozenEvent::class => 'onOrderFrozen',
        ];
    }

    public function onBillingEnded(BillingEndedEvent $event): void
    {
        $order = $event->getOrder();
        $priceResult = $event->getPriceResult();

        // Send billing notification
        $this->notificationService->sendBillingNotification(
            $order->getUserId(),
            $priceResult->getFinalPrice()
        );
    }

    public function onRefundRequired(RefundRequiredEvent $event): void
    {
        $order = $event->getOrder();
        $refundAmount = $event->getRefundAmount();

        // Process refund
        $this->refundService->processRefund(
            $order->getExternalOrderCode(),
            $refundAmount
        );
    }
}
```

## Core Services

The bundle provides the following core services:

| Service ID | Description |
|------------|-------------|
| `duration_billing.service` | Main billing service, handles order creation, updates, and settlement |
| `duration_billing.state_machine` | Order state machine, manages state transition logic |
| `duration_billing.price_calculator` | Price calculator, calculates fees based on pricing rules |
| `duration_billing.order_repository` | Order data repository |
| `duration_billing.product_repository` | Product data repository |

## Order States

```
PENDING → ACTIVE → ENDED
    ↓         ↑
  FROZEN ────┘
    ↓
  EXPIRED
```

State descriptions:
- `PENDING`: Pending activation (created but not yet started billing)
- `ACTIVE`: Active billing in progress
- `FROZEN`: Billing paused
- `ENDED`: Completed
- `EXPIRED`: Expired (abnormal state)

## Pricing Rules

Supports the following pricing strategies:

### Hourly Pricing Rule (HourlyPricingRule)
```php
use Tourze\DurationBillingBundle\PricingRule\HourlyPricingRule;
use Tourze\DurationBillingBundle\Enum\RoundingMode;

$rule = new HourlyPricingRule(
    pricePerHour: 100.0,
    roundingMode: RoundingMode::ROUND_UP
);
```

### Tiered Pricing Rule (TieredPricingRule)
```php
use Tourze\DurationBillingBundle\PricingRule\TieredPricingRule;
use Tourze\DurationBillingBundle\ValueObject\PriceTier;

$tiers = [
    new PriceTier(0, 60, 100.0),      // First hour
    new PriceTier(60, 180, 80.0),     // 2-3 hours
    new PriceTier(180, null, 60.0),   // After 3 hours
];

$rule = new TieredPricingRule($tiers);
```

### Custom Pricing Rules
Implement the `PricingRuleInterface` to create custom pricing logic:

```php
use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

class CustomPricingRule implements PricingRuleInterface
{
    public function calculatePrice(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        DurationBillingProduct $product
    ): PriceResult {
        // Implement custom billing logic
        return new PriceResult(/* ... */);
    }
}
```

## Exception Handling

The bundle defines a complete exception hierarchy:

- `DurationBillingException` - Base exception class
- `ProductNotFoundException` - Product not found
- `OrderNotFoundException` - Order not found
- `OrderAlreadyEndedException` - Order already ended
- `InvalidOrderStateException` - Invalid order state
- `InvalidPricingRuleException` - Invalid pricing rule
- `NegativeBillingTimeException` - Negative billing time
- `InvalidPrepaidAmountException` - Invalid prepaid amount

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run tests with coverage report
composer test-coverage

# Run code quality checks
composer quality
```

## Configuration

Default configuration works for most scenarios. For customization, create `config/packages/tourze_duration_billing.yaml`:

```yaml
tourze_duration_billing:
    # Default rounding mode
    default_rounding_mode: 'ROUND_UP'

    # Order timeout settings (minutes)
    order_timeout_minutes: 1440  # 24 hours

    # Frozen order timeout settings (minutes)
    frozen_order_timeout_minutes: 60
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please follow these guidelines:

- Follow PSR-12 coding standards
- Add tests for new features
- Ensure all tests pass before submitting PR
- Update relevant documentation

## Support

Need help or have suggestions? Please:

1. Check the [documentation](docs/)
2. Search [existing Issues](https://github.com/tourze/php-monorepo/issues)
3. Create a new Issue to describe your problem
4. Submit a Pull Request to contribute code