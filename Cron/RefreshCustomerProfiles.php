<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Cron;

use Magento\Framework\App\ResourceConnection;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Model\ResourceModel\CustomerProfile as CustomerProfileResource;
use Psr\Log\LoggerInterface;

/**
 * Cron job to refresh stale customer profiles
 */
class RefreshCustomerProfiles
{
    /**
     * @var CustomerProfileResource
     */
    private CustomerProfileResource $profileResource;

    /**
     * @var PersonalizedRecommendationInterface
     */
    private PersonalizedRecommendationInterface $recommendationService;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CustomerProfileResource $profileResource
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        CustomerProfileResource $profileResource,
        PersonalizedRecommendationInterface $recommendationService,
        ResourceConnection $resourceConnection,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->profileResource = $profileResource;
        $this->recommendationService = $recommendationService;
        $this->resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $this->refreshStaleProfiles();
            $this->cleanupExpiredRecommendations();
            $this->cleanupOldGuestHistory();
        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation] RefreshCustomerProfiles cron error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Refresh profiles that haven't been updated in 24 hours
     *
     * @return void
     */
    private function refreshStaleProfiles(): void
    {
        $staleProfiles = $this->profileResource->getStaleProfiles(24, 50);
        
        $refreshedCount = 0;
        foreach ($staleProfiles as $profile) {
            try {
                $customerId = (int) $profile['customer_id'];
                $type = $profile['profile_type'];
                
                $this->recommendationService->refreshProfile($customerId, $type);
                $refreshedCount++;
                
            } catch (\Exception $e) {
                $this->logger->warning(
                    '[ProductRecommendation] Failed to refresh profile for customer ' . 
                    ($profile['customer_id'] ?? 'unknown') . ': ' . $e->getMessage()
                );
            }
        }

        if ($refreshedCount > 0 && $this->config->isDebugMode()) {
            $this->logger->info(
                '[ProductRecommendation] Refreshed ' . $refreshedCount . ' customer profiles'
            );
        }
    }

    /**
     * Clean up expired recommendation cache entries
     *
     * @return void
     */
    private function cleanupExpiredRecommendations(): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('ai_personalized_recommendations');
            
            if (!$connection->isTableExists($tableName)) {
                return;
            }

            $deleted = $connection->delete(
                $tableName,
                ['expires_at < ?' => (new \DateTime())->format('Y-m-d H:i:s')]
            );

            if ($deleted > 0 && $this->config->isDebugMode()) {
                $this->logger->info(
                    '[ProductRecommendation] Cleaned up ' . $deleted . ' expired recommendations'
                );
            }

        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation] cleanupExpiredRecommendations error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Clean up old guest browsing history (older than 30 days)
     *
     * @return void
     */
    private function cleanupOldGuestHistory(): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('ai_guest_browsing_history');
            
            if (!$connection->isTableExists($tableName)) {
                return;
            }

            $cutoff = (new \DateTime())->modify('-30 days')->format('Y-m-d H:i:s');
            
            $deleted = $connection->delete(
                $tableName,
                ['viewed_at < ?' => $cutoff]
            );

            if ($deleted > 0 && $this->config->isDebugMode()) {
                $this->logger->info(
                    '[ProductRecommendation] Cleaned up ' . $deleted . ' old guest browsing records'
                );
            }

        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation] cleanupOldGuestHistory error: ' . $e->getMessage()
            );
        }
    }
}
