<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Model\ResourceModel\CustomerProfile;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Navindbhudiya\ProductRecommendation\Model\Data\CustomerProfile as Model;
use Navindbhudiya\ProductRecommendation\Model\ResourceModel\CustomerProfile as ResourceModel;

/**
 * Customer Profile Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'profile_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * Filter by customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function filterByCustomer(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    /**
     * Filter by profile type
     *
     * @param string $type
     * @return $this
     */
    public function filterByType(string $type): self
    {
        $this->addFieldToFilter('profile_type', $type);
        return $this;
    }

    /**
     * Filter by store ID
     *
     * @param int $storeId
     * @return $this
     */
    public function filterByStore(int $storeId): self
    {
        $this->addFieldToFilter('store_id', $storeId);
        return $this;
    }
}
