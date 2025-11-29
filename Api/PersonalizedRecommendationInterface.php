<?php
/**
 * Navindbhudiya ProductRecommendation
 *
 * @category  Navindbhudiya
 * @package   Navindbhudiya_ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Api;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Interface for personalized product recommendations
 */
interface PersonalizedRecommendationInterface
{
    /**
     * Recommendation types
     */
    public const TYPE_BROWSING = 'browsing';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_WISHLIST = 'wishlist';
    public const TYPE_JUST_FOR_YOU = 'just_for_you';

    /**
     * Get recommendations inspired by browsing history
     *
     * @param int|null $customerId Customer ID (null for guest with session)
     * @param int $limit Maximum number of products
     * @param int|null $storeId Store ID
     * @return ProductInterface[]
     */
    public function getBrowsingInspired(?int $customerId = null, int $limit = 8, ?int $storeId = null): array;

    /**
     * Get recommendations inspired by past purchases
     *
     * @param int $customerId Customer ID
     * @param int $limit Maximum number of products
     * @param int|null $storeId Store ID
     * @return ProductInterface[]
     */
    public function getPurchaseInspired(int $customerId, int $limit = 8, ?int $storeId = null): array;

    /**
     * Get recommendations inspired by wishlist
     *
     * @param int $customerId Customer ID
     * @param int $limit Maximum number of products
     * @param int|null $storeId Store ID
     * @return ProductInterface[]
     */
    public function getWishlistInspired(int $customerId, int $limit = 8, ?int $storeId = null): array;

    /**
     * Get combined "Just For You" recommendations
     * Combines browsing, purchase, and wishlist data with weighted scoring
     *
     * @param int $customerId Customer ID
     * @param int $limit Maximum number of products
     * @param int|null $storeId Store ID
     * @return ProductInterface[]
     */
    public function getJustForYou(int $customerId, int $limit = 12, ?int $storeId = null): array;

    /**
     * Check if customer has enough data for personalized recommendations
     *
     * @param int|null $customerId Customer ID
     * @param string $type Recommendation type
     * @return bool
     */
    public function hasEnoughData(?int $customerId, string $type): bool;

    /**
     * Refresh customer profile for a specific type
     *
     * @param int $customerId Customer ID
     * @param string $type Profile type
     * @return void
     */
    public function refreshProfile(int $customerId, string $type): void;

    /**
     * Clear cached recommendations for a customer
     *
     * @param int $customerId Customer ID
     * @param string|null $type Specific type or null for all
     * @return void
     */
    public function clearCache(int $customerId, ?string $type = null): void;
}
