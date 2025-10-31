<?php

namespace Tourze\DurationBillingBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DurationBillingBundle\Contract\PricingRuleInterface;
use Tourze\DurationBillingBundle\Repository\DurationBillingProductRepository;

#[ORM\Entity(repositoryClass: DurationBillingProductRepository::class)]
#[ORM\Table(name: 'duration_billing_products', options: ['comment' => '时长计费产品表'])]
class DurationBillingProduct implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '产品名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '产品描述'])]
    #[Assert\Length(max: 65535)]
    #[Assert\Type(type: 'string')]
    private ?string $description = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '定价规则数据'])]
    #[Assert\NotNull]
    #[Assert\Type(type: 'array')]
    #[Assert\NotBlank]
    private array $pricingRuleData;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '免费时长（分钟）'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $freeMinutes = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '冻结时长（分钟）'])]
    #[Assert\PositiveOrZero]
    private ?int $freezeMinutes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '最低消费金额'])]
    #[Assert\PositiveOrZero]
    private ?float $minAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '最高消费金额'])]
    #[Assert\PositiveOrZero]
    private ?float $maxAmount = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\NotNull]
    #[Assert\Type(type: 'bool')]
    private bool $enabled = true;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array')]
    private array $metadata = [];

    #[Assert\Valid]
    private ?PricingRuleInterface $pricingRule = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPricingRuleData(): array
    {
        return $this->pricingRuleData;
    }

    /**
     * @param array<string, mixed> $pricingRuleData
     */
    public function setPricingRuleData(array $pricingRuleData): void
    {
        $this->pricingRuleData = $pricingRuleData;
        $this->pricingRule = null; // Reset cached instance
    }

    public function getFreeMinutes(): int
    {
        return $this->freeMinutes;
    }

    public function setFreeMinutes(int $freeMinutes): void
    {
        $this->freeMinutes = $freeMinutes;
    }

    public function getFreezeMinutes(): ?int
    {
        return $this->freezeMinutes;
    }

    public function setFreezeMinutes(?int $freezeMinutes): void
    {
        $this->freezeMinutes = $freezeMinutes;
    }

    public function getMinAmount(): ?float
    {
        return $this->minAmount;
    }

    public function setMinAmount(?float $minAmount): void
    {
        $this->minAmount = $minAmount;
    }

    public function getMaxAmount(): ?float
    {
        return $this->maxAmount;
    }

    public function setMaxAmount(?float $maxAmount): void
    {
        $this->maxAmount = $maxAmount;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }

    public function setActive(bool $active): void
    {
        $this->enabled = $active;
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

    public function getPricingRule(): PricingRuleInterface
    {
        if (null === $this->pricingRule) {
            $ruleClass = $this->pricingRuleData['class'] ?? null;
            if (!is_string($ruleClass) || !class_exists($ruleClass) || !is_subclass_of($ruleClass, PricingRuleInterface::class)) {
                throw new \InvalidArgumentException('Invalid pricing rule class');
            }
            $this->pricingRule = $ruleClass::deserialize($this->pricingRuleData);
        }

        assert($this->pricingRule instanceof PricingRuleInterface);

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

    public function __toString(): string
    {
        return $this->name;
    }
}
