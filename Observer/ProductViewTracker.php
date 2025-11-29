<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Service\BehaviorCollector\BrowsingHistoryCollector;
use Psr\Log\LoggerInterface;

/**
 * Observer to track product views for personalized recommendations
 */
class ProductViewTracker implements ObserverInterface
{
    /**
     * @var BrowsingHistoryCollector
     */
    private BrowsingHistoryCollector $browsingCollector;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param BrowsingHistoryCollector $browsingCollector
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        BrowsingHistoryCollector $browsingCollector,
        StoreManagerInterface $storeManager,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->browsingCollector = $browsingCollector;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $product = $observer->getEvent()->getProduct();
            
            if (!$product || !$product->getId()) {
                return;
            }

            $storeId = (int) $this->storeManager->getStore()->getId();
            
            // Record the view for guest tracking
            // Logged-in users are tracked by Magento's native report_viewed_product_index
            $this->browsingCollector->recordGuestView(
                (int) $product->getId(),
                $storeId
            );

        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation] ProductViewTracker error: ' . $e->getMessage()
            );
        }
    }
}
