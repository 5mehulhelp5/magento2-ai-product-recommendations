<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Service\BehaviorCollector;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\BehaviorCollectorInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Psr\Log\LoggerInterface;

/**
 * Collects purchase history data for recommendations
 */
class PurchaseHistoryCollector implements BehaviorCollectorInterface
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
            return []; // Guests don't have purchase history
        }

        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $connection = $this->resourceConnection->getConnection();

            // Get products from completed orders
            $select = $connection->select()
                ->from(
                    ['soi' => $this->resourceConnection->getTableName('sales_order_item')],
                    ['product_id']
                )
                ->join(
                    ['so' => $this->resourceConnection->getTableName('sales_order')],
                    'soi.order_id = so.entity_id',
                    []
                )
                ->join(
                    ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                    'soi.product_id = cpe.entity_id',
                    []
                )
                ->where('so.customer_id = ?', $customerId)
                ->where('so.store_id = ?', $storeId)
                ->where('so.state IN (?)', [Order::STATE_COMPLETE, Order::STATE_PROCESSING])
                ->where('soi.parent_item_id IS NULL') // Only main products, not children
                ->group('soi.product_id')
                ->order('MAX(so.created_at) DESC')
                ->limit($limit);

            return $connection->fetchCol($select);

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] PurchaseHistoryCollector error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return PersonalizedRecommendationInterface::TYPE_PURCHASE;
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
     * Get purchase history with quantities for weighted recommendations
     *
     * @param int $customerId
     * @param int $limit
     * @param int|null $storeId
     * @return array Array of [product_id => quantity_ordered]
     */
    public function getProductIdsWithQuantities(int $customerId, int $limit = 20, ?int $storeId = null): array
    {
        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $connection = $this->resourceConnection->getConnection();

            $select = $connection->select()
                ->from(
                    ['soi' => $this->resourceConnection->getTableName('sales_order_item')],
                    [
                        'product_id',
                        'total_qty' => new \Zend_Db_Expr('SUM(soi.qty_ordered)')
                    ]
                )
                ->join(
                    ['so' => $this->resourceConnection->getTableName('sales_order')],
                    'soi.order_id = so.entity_id',
                    []
                )
                ->where('so.customer_id = ?', $customerId)
                ->where('so.store_id = ?', $storeId)
                ->where('so.state IN (?)', [Order::STATE_COMPLETE, Order::STATE_PROCESSING])
                ->where('soi.parent_item_id IS NULL')
                ->group('soi.product_id')
                ->order('total_qty DESC')
                ->limit($limit);

            $result = [];
            foreach ($connection->fetchAll($select) as $row) {
                $result[(int) $row['product_id']] = (int) $row['total_qty'];
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] getProductIdsWithQuantities error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent purchases (within specified days)
     *
     * @param int $customerId
     * @param int $days
     * @param int $limit
     * @param int|null $storeId
     * @return array
     */
    public function getRecentPurchases(int $customerId, int $days = 90, int $limit = 10, ?int $storeId = null): array
    {
        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            $connection = $this->resourceConnection->getConnection();
            $cutoff = (new \DateTime())->modify("-{$days} days")->format('Y-m-d H:i:s');

            $select = $connection->select()
                ->from(
                    ['soi' => $this->resourceConnection->getTableName('sales_order_item')],
                    ['product_id']
                )
                ->join(
                    ['so' => $this->resourceConnection->getTableName('sales_order')],
                    'soi.order_id = so.entity_id',
                    []
                )
                ->where('so.customer_id = ?', $customerId)
                ->where('so.store_id = ?', $storeId)
                ->where('so.state IN (?)', [Order::STATE_COMPLETE, Order::STATE_PROCESSING])
                ->where('so.created_at >= ?', $cutoff)
                ->where('soi.parent_item_id IS NULL')
                ->group('soi.product_id')
                ->order('MAX(so.created_at) DESC')
                ->limit($limit);

            return $connection->fetchCol($select);

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] getRecentPurchases error: ' . $e->getMessage());
            return [];
        }
    }
}
