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

use Navindbhudiya\ProductRecommendation\Api\Data\ProductEmbeddingInterface;

/**
 * Interface for product embedding repository
 */
interface ProductEmbeddingRepositoryInterface
{
    /**
     * Save product embedding
     *
     * @param ProductEmbeddingInterface $embedding
     * @return ProductEmbeddingInterface
     */
    public function save(ProductEmbeddingInterface $embedding): ProductEmbeddingInterface;

    /**
     * Get by product ID and store ID
     *
     * @param int $productId
     * @param int $storeId
     * @return ProductEmbeddingInterface|null
     */
    public function getByProductId(int $productId, int $storeId): ?ProductEmbeddingInterface;

    /**
     * Delete by product ID
     *
     * @param int $productId
     * @param int|null $storeId
     * @return bool
     */
    public function deleteByProductId(int $productId, ?int $storeId = null): bool;

    /**
     * Get products pending sync
     *
     * @param int $limit
     * @return ProductEmbeddingInterface[]
     */
    public function getPendingSync(int $limit = 100): array;

    /**
     * Mark products as synced
     *
     * @param array $productIds
     * @param int $storeId
     * @return void
     */
    public function markAsSynced(array $productIds, int $storeId): void;

    /**
     * Check if product needs resync (content changed)
     *
     * @param int $productId
     * @param int $storeId
     * @param string $newHash
     * @return bool
     */
    public function needsResync(int $productId, int $storeId, string $newHash): bool;
}
