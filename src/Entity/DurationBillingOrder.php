<?php

namespace Tourze\DurationBillingBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DurationBillingBundle\Enum\OrderStatus;
use Tourze\DurationBillingBundle\Repository\DurationBillingOrderRepository;

#[ORM\Entity(repositoryClass: DurationBillingOrderRepository::class)]
#[ORM\Table(name: 'duration_billing_orders', options: ['comment' => '时长计费订单表'])]
class DurationBillingOrder implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    #[ORM\ManyToOne(targetEntity: DurationBillingProduct::class)]
    #[ORM\JoinColumn(nullable: false)]
    private DurationBillingProduct $product;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '用户ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[IndexColumn]
    private string $userId;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true, options: ['comment' => '订单编号'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[IndexColumn]
    private string $orderCode;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: OrderStatus::class, options: ['comment' => '订单状态'])]
    #[Assert\NotNull]
    #[Assert\Choice(callback: [OrderStatus::class, 'cases'])]
    #[IndexColumn]
    private OrderStatus $status = OrderStatus::ACTIVE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '开始时间'])]
    #[Assert\NotNull]
    #[IndexColumn]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '结束时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '支付时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $paymentTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '冻结时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $frozenAt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '预付金额'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private float $prepaidAmount = 0.0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '实际金额'])]
    #[Assert\PositiveOrZero]
    private ?float $actualAmount = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '冻结时间（分钟）'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $frozenMinutes = 0;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array')]
    private array $metadata = [];

    public function getProduct(): DurationBillingProduct
    {
        return $this->product;
    }

    public function setProduct(DurationBillingProduct $product): void
    {
        $this->product = $product;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getOrderCode(): string
    {
        return $this->orderCode;
    }

    public function setOrderCode(string $orderCode): void
    {
        $this->orderCode = $orderCode;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function getPaymentTime(): ?\DateTimeImmutable
    {
        return $this->paymentTime;
    }

    public function setPaymentTime(?\DateTimeImmutable $paymentTime): void
    {
        $this->paymentTime = $paymentTime;
    }

    public function getFrozenAt(): ?\DateTimeImmutable
    {
        return $this->frozenAt;
    }

    public function setFrozenAt(?\DateTimeImmutable $frozenAt): void
    {
        $this->frozenAt = $frozenAt;
    }

    public function getPrepaidAmount(): float
    {
        return $this->prepaidAmount;
    }

    public function setPrepaidAmount(float $prepaidAmount): void
    {
        $this->prepaidAmount = $prepaidAmount;
    }

    public function getActualAmount(): ?float
    {
        return $this->actualAmount;
    }

    public function setActualAmount(?float $actualAmount): void
    {
        $this->actualAmount = $actualAmount;
    }

    public function getFrozenMinutes(): int
    {
        return $this->frozenMinutes;
    }

    public function setFrozenMinutes(int $frozenMinutes): void
    {
        $this->frozenMinutes = $frozenMinutes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getActualBillingMinutes(): int
    {
        if (null === $this->endTime) {
            return 0;
        }

        $totalMinutes = (int) (($this->endTime->getTimestamp() - $this->startTime->getTimestamp()) / 60);

        // 使用订单的冻结时间，如果没有设置则使用产品的默认冻结时间
        $freezeMinutes = $this->frozenMinutes > 0 ? $this->frozenMinutes : ($this->product->getFreezeMinutes() ?? 0);

        $billingMinutes = $totalMinutes - $freezeMinutes;

        return max(0, $billingMinutes);
    }

    public function getRefundAmount(): float
    {
        if (null === $this->actualAmount) {
            return 0.0;
        }

        if ($this->prepaidAmount > $this->actualAmount) {
            return $this->prepaidAmount - $this->actualAmount;
        }

        return 0.0;
    }

    public function requiresAdditionalPayment(): bool
    {
        if (null === $this->actualAmount) {
            return false;
        }

        return $this->actualAmount > $this->prepaidAmount;
    }

    public function getAdditionalPaymentAmount(): float
    {
        if (!$this->requiresAdditionalPayment()) {
            return 0.0;
        }

        return ($this->actualAmount ?? 0.0) - $this->prepaidAmount;
    }

    public function __toString(): string
    {
        return $this->orderCode;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getFinalAmount(): ?float
    {
        return $this->actualAmount;
    }

    public function getTotalFrozenMinutes(): int
    {
        return $this->frozenMinutes;
    }
}
