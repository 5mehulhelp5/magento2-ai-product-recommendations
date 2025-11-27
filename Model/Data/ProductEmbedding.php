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

namespace Navindbhudiya\ProductRecommendation\Model\Data;

use Magento\Framework\DataObject;
use Navindbhudiya\ProductRecommendation\Api\Data\ProductEmbeddingInterface;

/**
 * Product embedding data model
 */
class ProductEmbedding extends DataObject implements ProductEmbeddingInterface
{
    /**
     * @inheritDoc
     */
    public function getProductId(): int
    {
        return (int) $this->getData(self::PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setProductId(int $productId): ProductEmbeddingInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritDoc
     */
    public function getSku(): string
    {
        return (string) $this->getData(self::SKU);
    }

    /**
     * @inheritDoc
     */
    public function setSku(string $sku): ProductEmbeddingInterface
    {
        return $this->setData(self::SKU, $sku);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(int $storeId): ProductEmbeddingInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getEmbeddingText(): string
    {
        return (string) $this->getData(self::EMBEDDING_TEXT);
    }

    /**
     * @inheritDoc
     */
    public function setEmbeddingText(string $text): ProductEmbeddingInterface
    {
        return $this->setData(self::EMBEDDING_TEXT, $text);
    }

    /**
     * @inheritDoc
     */
    public function getEmbeddingHash(): string
    {
        return (string) $this->getData(self::EMBEDDING_HASH);
    }

    /**
     * @inheritDoc
     */
    public function setEmbeddingHash(string $hash): ProductEmbeddingInterface
    {
        return $this->setData(self::EMBEDDING_HASH, $hash);
    }

    /**
     * @inheritDoc
     */
    public function getSyncedAt(): ?string
    {
        return $this->getData(self::SYNCED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setSyncedAt(string $timestamp): ProductEmbeddingInterface
    {
        return $this->setData(self::SYNCED_AT, $timestamp);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return (string) ($this->getData(self::STATUS) ?: self::STATUS_PENDING);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): ProductEmbeddingInterface
    {
        return $this->setData(self::STATUS, $status);
    }
}
