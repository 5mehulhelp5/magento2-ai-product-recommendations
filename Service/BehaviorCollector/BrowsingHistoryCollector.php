<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Service\BehaviorCollector;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\BehaviorCollectorInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Psr\Log\LoggerInterface;

/**
 * Collects browsing history data for recommendations
 */
class BrowsingHistoryCollector implements BehaviorCollectorInterface
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var SessionManagerInterface
     */
    private SessionManagerInterface $session;

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
     * @param SessionManagerInterface $session
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        SessionManagerInterface $session,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getProductIds(?int $customerId, int $limit = 20, ?int $storeId = null): array
    {
        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            
            if ($customerId) {
                return $this->getLoggedInCustomerHistory($customerId, $limit, $storeId);
            }
            
            return $this->getGuestHistory($limit, $storeId);
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] BrowsingHistoryCollector error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return PersonalizedRecommendationInterface::TYPE_BROWSING;
    }

    /**
     * @inheritDoc
     */
    public function hasData(?int $customerId, ?int $storeId = null): bool
    {
        $productIds = $this->getProductIds($customerId, 1, $storeId);
        return !empty($productIds);
    }

    /**
     * Get browsing history for logged-in customer
     *
     * @param int $customerId
     * @param int $limit
     * @param int $storeId
     * @return array
     */
    private function getLoggedInCustomerHistory(int $customerId, int $limit, int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        
        // Use Magento's report_viewed_product_index table
        $select = $connection->select()
            ->from(
                ['rv' => $this->resourceConnection->getTableName('report_viewed_product_index')],
                ['product_id']
            )
            ->join(
                ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'rv.product_id = cpe.entity_id',
                []
            )
            ->where('rv.customer_id = ?', $customerId)
            ->where('rv.store_id = ?', $storeId)
            ->order('rv.added_at DESC')
            ->limit($limit);

        $result = $connection->fetchCol($select);
        
        // Also check guest history table for this session (in case they just logged in)
        $sessionHistory = $this->getGuestHistory($limit, $storeId);
        
        // Merge and deduplicate
        $merged = array_unique(array_merge($result, $sessionHistory));
        
        return array_slice($merged, 0, $limit);
    }

    /**
     * Get browsing history for guest (session-based)
     *
     * @param int $limit
     * @param int $storeId
     * @return array
     */
    private function getGuestHistory(int $limit, int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $sessionId = $this->session->getSessionId();
        
        if (empty($sessionId)) {
            return [];
        }

        // Check our custom guest browsing table
        $tableName = $this->resourceConnection->getTableName('ai_guest_browsing_history');
        
        // Check if table exists
        if (!$connection->isTableExists($tableName)) {
            return [];
        }

        $select = $connection->select()
            ->from($tableName, ['product_id'])
            ->where('session_id = ?', $sessionId)
            ->where('store_id = ?', $storeId)
            ->order('viewed_at DESC')
            ->limit($limit);

        return $connection->fetchCol($select);
    }

    /**
     * Record a product view for guest
     *
     * @param int $productId
     * @param int $storeId
     * @return void
     */
    public function recordGuestView(int $productId, int $storeId): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('ai_guest_browsing_history');
            
            if (!$connection->isTableExists($tableName)) {
                return;
            }

            $sessionId = $this->session->getSessionId();
            if (empty($sessionId)) {
                return;
            }

            // Check if already recorded recently (within last hour)
            $select = $connection->select()
                ->from($tableName, ['history_id'])
                ->where('session_id = ?', $sessionId)
                ->where('product_id = ?', $productId)
                ->where('viewed_at > ?', (new \DateTime())->modify('-1 hour')->format('Y-m-d H:i:s'));

            if ($connection->fetchOne($select)) {
                return; // Already recorded
            }

            $connection->insert($tableName, [
                'session_id' => $sessionId,
                'product_id' => $productId,
                'store_id' => $storeId,
                'viewed_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);

            // Clean up old guest history (older than 30 days)
            $this->cleanupOldGuestHistory();

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] recordGuestView error: ' . $e->getMessage());
        }
    }

    /**
     * Clean up old guest browsing history
     *
     * @return void
     */
    private function cleanupOldGuestHistory(): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('ai_guest_browsing_history');
            
            $cutoff = (new \DateTime())->modify('-30 days')->format('Y-m-d H:i:s');
            
            $connection->delete($tableName, ['viewed_at < ?' => $cutoff]);
        } catch (\Exception $e) {
            // Silently fail cleanup
        }
    }
}
