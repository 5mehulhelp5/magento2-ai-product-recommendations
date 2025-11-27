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
 * Observer for product save after event
 */
class ProductSaveAfter implements ObserverInterface
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
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $observer->getEvent()->getProduct();

            if (!$product || !$product->getId()) {
                return;
            }

            $productId = (int) $product->getId();

            // Clear cache for this product
            $this->recommendationService->clearCache($productId);

            // Check if indexer is on schedule
            $indexer = $this->indexerRegistry->get('product_recommendation_embedding');

            if (!$indexer->isScheduled()) {
                // Reindex immediately
                $indexer->reindexRow($productId);
            }

            if ($this->config->isDebugMode()) {
                $this->logger->debug(sprintf(
                    '[ProductRecommendation] Product %d saved, cache cleared',
                    $productId
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Error in ProductSaveAfter: ' . $e->getMessage());
        }
    }
}
