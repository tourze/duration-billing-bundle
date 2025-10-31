<?php

namespace Tourze\DurationBillingBundle\Service;

use Tourze\DurationBillingBundle\Entity\DurationBillingOrder;
use Tourze\DurationBillingBundle\Exception\InvalidOrderStateException;
use Tourze\DurationBillingBundle\Exception\OrderNotFoundException;
use Tourze\DurationBillingBundle\Exception\ProductNotFoundException;
use Tourze\DurationBillingBundle\ValueObject\PriceResult;

interface DurationBillingServiceInterface
{
    /**
     * 开始新的计费会话
     *
     * @param int|string $productId
     * @param string $userId
     * @param array<string, mixed> $options
     * @return DurationBillingOrder
     * @throws ProductNotFoundException
     */
    public function startBilling(int|string $productId, string $userId, array $options = []): DurationBillingOrder;

    /**
     * 冻结计费（暂停收费）
     *
     * @param int|string $orderId
     * @return DurationBillingOrder
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function freezeBilling(int|string $orderId): DurationBillingOrder;

    /**
     * 从冻结状态恢复计费
     *
     * @param int|string $orderId
     * @return DurationBillingOrder
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function resumeBilling(int|string $orderId): DurationBillingOrder;

    /**
     * 结束计费并计算最终价格
     *
     * @param int|string $orderId
     * @return array{order: DurationBillingOrder, price: PriceResult}
     * @throws OrderNotFoundException
     * @throws InvalidOrderStateException
     */
    public function endBilling(int|string $orderId): array;

    /**
     * 获取活跃订单的当前价格
     *
     * @param int|string $orderId
     * @return PriceResult
     * @throws OrderNotFoundException
     */
    public function getCurrentPrice(int|string $orderId): PriceResult;

    /**
     * 查找用户的所有活跃订单
     *
     * @param string $userId
     * @return DurationBillingOrder[]
     */
    public function findActiveOrders(string $userId): array;

    /**
     * 根据订单编号查找订单
     *
     * @param string $orderCode
     * @return DurationBillingOrder|null
     */
    public function findOrderByCode(string $orderCode): ?DurationBillingOrder;
}
