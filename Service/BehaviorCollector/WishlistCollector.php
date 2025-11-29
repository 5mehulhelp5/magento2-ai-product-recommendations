<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Service\BehaviorCollector;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\BehaviorCollectorInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Psr\Log\LoggerInterface;

/**
 * Collects wishlist data for recommendations
 */
class WishlistCollector implements BehaviorCollectorInterface
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getProductIds(?int $customerId, int $limit = 20, ?int $storeId = null): array
    {
        if ($customerId === null) {
            return []; // Guests don't have wishlists
        }

        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $connection = $this->resourceConnection->getConnection();

            $select = $connection->select()
                ->from(
                    ['wi' => $this->resourceConnection->getTableName('wishlist_item')],
                    ['product_id']
                )
                ->join(
                    ['w' => $this->resourceConnection->getTableName('wishlist')],
                    'wi.wishlist_id = w.wishlist_id',
                    []
                )
                ->join(
                    ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                    'wi.product_id = cpe.entity_id',
                    []
                )
                ->where('w.customer_id = ?', $customerId)
                ->where('wi.store_id = ? OR wi.store_id IS NULL', $storeId)
                ->order('wi.added_at DESC')
                ->limit($limit);

            return $connection->fetchCol($select);

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] WishlistCollector error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return PersonalizedRecommendationInterface::TYPE_WISHLIST;
    }

    /**
     * @inheritDoc
     */
    public function hasData(?int $customerId, ?int $storeId = null): bool
    {
        if ($customerId === null) {
            return false;
        }
        
        $productIds = $this->getProductIds($customerId, 1, $storeId);
        return !empty($productIds);
    }

    /**
     * Get wishlist items with added dates for weighted recommendations
     *
     * @param int $customerId
     * @param int $limit
     * @param int|null $storeId
     * @return array Array of [product_id => added_at_timestamp]
     */
    public function getProductIdsWithDates(int $customerId, int $limit = 20, ?int $storeId = null): array
    {
        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $connection = $this->resourceConnection->getConnection();

            $select = $connection->select()
                ->from(
                    ['wi' => $this->resourceConnection->getTableName('wishlist_item')],
                    ['product_id', 'added_at']
                )
                ->join(
                    ['w' => $this->resourceConnection->getTableName('wishlist')],
                    'wi.wishlist_id = w.wishlist_id',
                    []
                )
                ->where('w.customer_id = ?', $customerId)
                ->where('wi.store_id = ? OR wi.store_id IS NULL', $storeId)
                ->order('wi.added_at DESC')
                ->limit($limit);

            $result = [];
            foreach ($connection->fetchAll($select) as $row) {
                $result[(int) $row['product_id']] = strtotime($row['added_at']);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] getProductIdsWithDates error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get wishlist count for customer
     *
     * @param int $customerId
     * @param int|null $storeId
     * @return int
     */
    public function getWishlistCount(int $customerId, ?int $storeId = null): int
    {
        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $connection = $this->resourceConnection->getConnection();

            $select = $connection->select()
                ->from(
                    ['wi' => $this->resourceConnection->getTableName('wishlist_item')],
                    [new \Zend_Db_Expr('COUNT(DISTINCT wi.product_id)')]
                )
                ->join(
                    ['w' => $this->resourceConnection->getTableName('wishlist')],
                    'wi.wishlist_id = w.wishlist_id',
                    []
                )
                ->where('w.customer_id = ?', $customerId)
                ->where('wi.store_id = ? OR wi.store_id IS NULL', $storeId);

            return (int) $connection->fetchOne($select);

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] getWishlistCount error: ' . $e->getMessage());
            return 0;
        }
    }
}
