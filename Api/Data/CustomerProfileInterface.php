<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Api\Data;

/**
 * Interface for customer AI profile
 */
interface CustomerProfileInterface
{
    public const PROFILE_ID = 'profile_id';
    public const CUSTOMER_ID = 'customer_id';
    public const PROFILE_TYPE = 'profile_type';
    public const EMBEDDING_VECTOR = 'embedding_vector';
    public const SOURCE_PRODUCT_IDS = 'source_product_ids';
    public const PRODUCT_COUNT = 'product_count';
    public const STORE_ID = 'store_id';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get profile ID
     *
     * @return int|null
     */
    public function getProfileId(): ?int;

    /**
     * Set profile ID
     *
     * @param int $profileId
     * @return $this
     */
    public function setProfileId(int $profileId): self;

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * Set customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

    /**
     * Get profile type
     *
     * @return string
     */
    public function getProfileType(): string;

    /**
     * Set profile type
     *
     * @param string $type
     * @return $this
     */
    public function setProfileType(string $type): self;

    /**
     * Get embedding vector as array
     *
     * @return array
     */
    public function getEmbeddingVector(): array;

    /**
     * Set embedding vector
     *
     * @param array $vector
     * @return $this
     */
    public function setEmbeddingVector(array $vector): self;

    /**
     * Get source product IDs
     *
     * @return array
     */
    public function getSourceProductIds(): array;

    /**
     * Set source product IDs
     *
     * @param array $productIds
     * @return $this
     */
    public function setSourceProductIds(array $productIds): self;

    /**
     * Get product count
     *
     * @return int
     */
    public function getProductCount(): int;

    /**
     * Set product count
     *
     * @param int $count
     * @return $this
     */
    public function setProductCount(int $count): self;

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
     * @return $this
     */
    public function setStoreId(int $storeId): self;
}
