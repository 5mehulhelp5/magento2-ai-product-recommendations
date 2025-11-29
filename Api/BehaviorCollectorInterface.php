<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Api;

/**
 * Interface for collecting customer behavior data
 */
interface BehaviorCollectorInterface
{
    /**
     * Get product IDs from customer behavior
     *
     * @param int|null $customerId Customer ID (null for session-based)
     * @param int $limit Maximum products to return
     * @param int|null $storeId Store ID
     * @return array Array of product IDs
     */
    public function getProductIds(?int $customerId, int $limit = 20, ?int $storeId = null): array;

    /**
     * Get behavior type identifier
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Check if collector has data for customer
     *
     * @param int|null $customerId
     * @param int|null $storeId
     * @return bool
     */
    public function hasData(?int $customerId, ?int $storeId = null): bool;
}
