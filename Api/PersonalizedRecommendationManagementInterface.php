<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Api;

/**
 * Interface for REST API personalized recommendation management
 */
interface PersonalizedRecommendationManagementInterface
{
    /**
     * Get recommendations inspired by browsing history for logged-in customer
     *
     * @param int $customerId
     * @param int $limit
     * @return \Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface[]
     */
    public function getBrowsingInspired(int $customerId, int $limit = 8): array;

    /**
     * Get recommendations inspired by past purchases
     *
     * @param int $customerId
     * @param int $limit
     * @return \Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface[]
     */
    public function getPurchaseInspired(int $customerId, int $limit = 8): array;

    /**
     * Get recommendations inspired by wishlist
     *
     * @param int $customerId
     * @param int $limit
     * @return \Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface[]
     */
    public function getWishlistInspired(int $customerId, int $limit = 8): array;

    /**
     * Get combined "Just For You" recommendations
     *
     * @param int $customerId
     * @param int $limit
     * @return \Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface[]
     */
    public function getJustForYou(int $customerId, int $limit = 12): array;

    /**
     * Get browsing-inspired recommendations for guest (session-based)
     *
     * @param int $limit
     * @return \Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface[]
     */
    public function getGuestBrowsingInspired(int $limit = 8): array;

    /**
     * Admin: Get recommendations for any customer by type
     *
     * @param int $customerId
     * @param string $type
     * @param int $limit
     * @return \Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface[]
     */
    public function getCustomerRecommendations(int $customerId, string $type, int $limit = 8): array;

    /**
     * Admin: Refresh customer profile
     *
     * @param int $customerId
     * @param string|null $type
     * @return bool
     */
    public function refreshCustomerProfile(int $customerId, ?string $type = null): bool;
}
