<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Customer Profile Resource Model
 */
class CustomerProfile extends AbstractDb
{
    /**
     * Table name
     */
    public const TABLE_NAME = 'ai_customer_profile';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'profile_id');
    }

    /**
     * Load profile by customer ID and type
     *
     * @param int $customerId
     * @param string $profileType
     * @param int $storeId
     * @return array|null
     */
    public function loadByCustomerAndType(int $customerId, string $profileType, int $storeId): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('customer_id = ?', $customerId)
            ->where('profile_type = ?', $profileType)
            ->where('store_id = ?', $storeId);

        $result = $connection->fetchRow($select);
        return $result ?: null;
    }

    /**
     * Save or update profile
     *
     * @param int $customerId
     * @param string $profileType
     * @param int $storeId
     * @param array $data
     * @return void
     */
    public function saveProfile(int $customerId, string $profileType, int $storeId, array $data): void
    {
        $connection = $this->getConnection();
        
        $existing = $this->loadByCustomerAndType($customerId, $profileType, $storeId);
        
        $data['customer_id'] = $customerId;
        $data['profile_type'] = $profileType;
        $data['store_id'] = $storeId;
        $data['updated_at'] = (new \DateTime())->format('Y-m-d H:i:s');
        
        if ($existing) {
            $connection->update(
                $this->getMainTable(),
                $data,
                [
                    'customer_id = ?' => $customerId,
                    'profile_type = ?' => $profileType,
                    'store_id = ?' => $storeId
                ]
            );
        } else {
            $data['created_at'] = $data['updated_at'];
            $connection->insert($this->getMainTable(), $data);
        }
    }

    /**
     * Delete profiles by customer ID
     *
     * @param int $customerId
     * @param string|null $profileType
     * @return void
     */
    public function deleteByCustomer(int $customerId, ?string $profileType = null): void
    {
        $connection = $this->getConnection();
        $where = ['customer_id = ?' => $customerId];
        
        if ($profileType !== null) {
            $where['profile_type = ?'] = $profileType;
        }
        
        $connection->delete($this->getMainTable(), $where);
    }

    /**
     * Get stale profiles that need refresh
     *
     * @param int $maxAgeHours
     * @param int $limit
     * @return array
     */
    public function getStaleProfiles(int $maxAgeHours = 24, int $limit = 100): array
    {
        $connection = $this->getConnection();
        $cutoff = (new \DateTime())->modify("-{$maxAgeHours} hours")->format('Y-m-d H:i:s');
        
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('updated_at < ?', $cutoff)
            ->limit($limit);

        return $connection->fetchAll($select);
    }
}
