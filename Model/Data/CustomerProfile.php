<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Model\Data;

use Magento\Framework\Model\AbstractModel;
use Navindbhudiya\ProductRecommendation\Api\Data\CustomerProfileInterface;
use Navindbhudiya\ProductRecommendation\Model\ResourceModel\CustomerProfile as ResourceModel;

/**
 * Customer AI Profile Model
 */
class CustomerProfile extends AbstractModel implements CustomerProfileInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getProfileId(): ?int
    {
        $id = $this->getData(self::PROFILE_ID);
        return $id ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setProfileId(int $profileId): CustomerProfileInterface
    {
        return $this->setData(self::PROFILE_ID, $profileId);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId(int $customerId): CustomerProfileInterface
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getProfileType(): string
    {
        return (string) $this->getData(self::PROFILE_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setProfileType(string $type): CustomerProfileInterface
    {
        return $this->setData(self::PROFILE_TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getEmbeddingVector(): array
    {
        $vector = $this->getData(self::EMBEDDING_VECTOR);
        if (empty($vector)) {
            return [];
        }
        if (is_string($vector)) {
            $decoded = json_decode($vector, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($vector) ? $vector : [];
    }

    /**
     * @inheritDoc
     */
    public function setEmbeddingVector(array $vector): CustomerProfileInterface
    {
        return $this->setData(self::EMBEDDING_VECTOR, json_encode($vector));
    }

    /**
     * @inheritDoc
     */
    public function getSourceProductIds(): array
    {
        $ids = $this->getData(self::SOURCE_PRODUCT_IDS);
        if (empty($ids)) {
            return [];
        }
        if (is_string($ids)) {
            return array_filter(array_map('intval', explode(',', $ids)));
        }
        return is_array($ids) ? $ids : [];
    }

    /**
     * @inheritDoc
     */
    public function setSourceProductIds(array $productIds): CustomerProfileInterface
    {
        return $this->setData(self::SOURCE_PRODUCT_IDS, implode(',', $productIds));
    }

    /**
     * @inheritDoc
     */
    public function getProductCount(): int
    {
        return (int) $this->getData(self::PRODUCT_COUNT);
    }

    /**
     * @inheritDoc
     */
    public function setProductCount(int $count): CustomerProfileInterface
    {
        return $this->setData(self::PRODUCT_COUNT, $count);
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
    public function setStoreId(int $storeId): CustomerProfileInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }
}
