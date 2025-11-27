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
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Service\ChromaClient;
use Psr\Log\LoggerInterface;

/**
 * Observer for product delete before event
 */
class ProductDeleteBefore implements ObserverInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ChromaClient
     */
    private ChromaClient $chromaClient;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

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
     * @param ChromaClient $chromaClient
     * @param StoreManagerInterface $storeManager
     * @param RecommendationServiceInterface $recommendationService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        ChromaClient $chromaClient,
        StoreManagerInterface $storeManager,
        RecommendationServiceInterface $recommendationService,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->chromaClient = $chromaClient;
        $this->storeManager = $storeManager;
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

            // Delete from ChromaDB for all stores
            $collectionName = $this->config->getCollectionName();
            $collectionId = $this->chromaClient->getCollectionId($collectionName);

            $documentIds = [];
            foreach ($this->storeManager->getStores() as $store) {
                $documentIds[] = "product_{$productId}_{$store->getId()}";
            }

            $this->chromaClient->deleteDocuments($collectionId, $documentIds);

            // Clear cache
            $this->recommendationService->clearCache($productId);

            if ($this->config->isDebugMode()) {
                $this->logger->debug(sprintf(
                    '[ProductRecommendation] Product %d deleted from ChromaDB',
                    $productId
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Error in ProductDeleteBefore: ' . $e->getMessage());
        }
    }
}
