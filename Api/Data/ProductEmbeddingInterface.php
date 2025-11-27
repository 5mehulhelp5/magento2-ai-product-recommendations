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

namespace Navindbhudiya\ProductRecommendation\Api\Data;

/**
 * Interface for product embedding data
 */
interface ProductEmbeddingInterface
{
    public const PRODUCT_ID = 'product_id';
    public const SKU = 'sku';
    public const STORE_ID = 'store_id';
    public const EMBEDDING_TEXT = 'embedding_text';
    public const EMBEDDING_HASH = 'embedding_hash';
    public const SYNCED_AT = 'synced_at';
    public const STATUS = 'status';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_FAILED = 'failed';

    /**
     * Get product ID
     *
     * @return int
     */
    public function getProductId(): int;

    /**
     * Set product ID
     *
     * @param int $productId
     * @return self
     */
    public function setProductId(int $productId): self;

    /**
     * Get SKU
     *
     * @return string
     */
    public function getSku(): string;

    /**
     * Set SKU
     *
     * @param string $sku
     * @return self
     */
    public function setSku(string $sku): self;

    /**
     * Get store ID
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set store ID
     *
     * @param int $storeId
     * @return self
     */
    public function setStoreId(int $storeId): self;

    /**
     * Get embedding text
     *
     * @return string
     */
    public function getEmbeddingText(): string;

    /**
     * Set embedding text
     *
     * @param string $text
     * @return self
     */
    public function setEmbeddingText(string $text): self;

    /**
     * Get embedding hash
     *
     * @return string
     */
    public function getEmbeddingHash(): string;

    /**
     * Set embedding hash
     *
     * @param string $hash
     * @return self
     */
    public function setEmbeddingHash(string $hash): self;

    /**
     * Get synced at timestamp
     *
     * @return string|null
     */
    public function getSyncedAt(): ?string;

    /**
     * Set synced at timestamp
     *
     * @param string $timestamp
     * @return self
     */
    public function setSyncedAt(string $timestamp): self;

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set status
     *
     * @param string $status
     * @return self
     */
    public function setStatus(string $status): self;
}
