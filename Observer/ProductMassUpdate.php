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

namespace Navindbhudiya\ProductRecommendation\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Observer for mass product attribute update
 */
class ProductMassUpdate implements ObserverInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var IndexerRegistry
     */
    private IndexerRegistry $indexerRegistry;

    /**
     * @var RecommendationServiceInterface
     */
    private RecommendationServiceInterface $recommendationService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param IndexerRegistry $indexerRegistry
     * @param RecommendationServiceInterface $recommendationService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        IndexerRegistry $indexerRegistry,
        RecommendationServiceInterface $recommendationService,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->indexerRegistry = $indexerRegistry;
        $this->recommendationService = $recommendationService;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $productIds = $observer->getEvent()->getProductIds();

            if (empty($productIds)) {
                return;
            }

            // Clear cache for affected products
            foreach ($productIds as $productId) {
                $this->recommendationService->clearCache((int) $productId);
            }

            // Check if any embedding-related attributes were updated
            $attributesData = $observer->getEvent()->getAttributesData();
            $embeddingAttributes = $this->config->getProductAttributes();

            $needsReindex = false;
            if (!empty($attributesData)) {
                foreach (array_keys($attributesData) as $attributeCode) {
                    if (in_array($attributeCode, $embeddingAttributes)) {
                        $needsReindex = true;
                        break;
                    }
                }
            }

            if ($needsReindex) {
                $indexer = $this->indexerRegistry->get('product_recommendation_embedding');

                if (!$indexer->isScheduled()) {
                    $indexer->reindexList($productIds);
                }

                if ($this->config->isDebugMode()) {
                    $this->logger->debug(sprintf(
                        '[ProductRecommendation] Mass update: %d products reindexed',
                        count($productIds)
                    ));
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Error in ProductMassUpdate: ' . $e->getMessage());
        }
    }
}
