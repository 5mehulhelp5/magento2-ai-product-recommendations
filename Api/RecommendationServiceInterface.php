<?php
/**
 * Navindbhudiya ProductRecommendation
 *
 * @category  Navindbhudiya
 * @package   Navindbhudiya_ProductRecommendation
 * @author    Navin Bhudiya
 * @license   MIT License
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Api;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Interface for recommendation service
 */
interface RecommendationServiceInterface
{
    public const TYPE_RELATED = 'related';
    public const TYPE_CROSSSELL = 'crosssell';
    public const TYPE_UPSELL = 'upsell';

    /**
     * Get related products for a given product
     *
     * @param ProductInterface|int $product Product or product ID
     * @param int|null $limit
     * @param int|null $storeId
     * @return ProductInterface[]
     */
    public function getRelatedProducts($product, ?int $limit = null, ?int $storeId = null): array;

    /**
     * Get cross-sell products for a given product
     *
     * @param ProductInterface|int $product Product or product ID
     * @param int|null $limit
     * @param int|null $storeId
     * @return ProductInterface[]
     */
    public function getCrossSellProducts($product, ?int $limit = null, ?int $storeId = null): array;

    /**
     * Get up-sell products for a given product
     *
     * @param ProductInterface|int $product Product or product ID
     * @param int|null $limit
     * @param int|null $storeId
     * @return ProductInterface[]
     */
    public function getUpSellProducts($product, ?int $limit = null, ?int $storeId = null): array;

    /**
     * Get similar products based on text query
     *
     * @param string $query
     * @param int $limit
     * @param int|null $storeId
     * @return ProductInterface[]
     */
    public function getSimilarProductsByQuery(string $query, int $limit = 10, ?int $storeId = null): array;

    /**
     * Get recommendations with scores
     *
     * @param ProductInterface|int $product
     * @param string $type
     * @param int|null $limit
     * @param int|null $storeId
     * @return \Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface[]
     */
    public function getRecommendationsWithScores(
        $product,
        string $type = self::TYPE_RELATED,
        ?int $limit = null,
        ?int $storeId = null
    ): array;

    /**
     * Check if AI recommendations are available for a product
     *
     * @param int $productId
     * @return bool
     */
    public function hasAiRecommendations(int $productId): bool;

    /**
     * Clear recommendation cache for a product
     *
     * @param int $productId
     * @return void
     */
    public function clearCache(int $productId): void;

    /**
     * Clear all recommendation cache
     *
     * @return void
     */
    public function clearAllCache(): void;
}
