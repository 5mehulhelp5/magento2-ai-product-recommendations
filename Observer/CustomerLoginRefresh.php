<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Observer to refresh customer profiles on login
 */
class CustomerLoginRefresh implements ObserverInterface
{
    /**
     * @var PersonalizedRecommendationInterface
     */
    private PersonalizedRecommendationInterface $recommendationService;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        PersonalizedRecommendationInterface $recommendationService,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->recommendationService = $recommendationService;
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
            $customer = $observer->getEvent()->getCustomer();
            
            if (!$customer || !$customer->getId()) {
                return;
            }

            $customerId = (int) $customer->getId();

            // Clear cached recommendations to force refresh
            $this->recommendationService->clearCache($customerId);

            // Pre-warm the "Just For You" recommendations in background
            // This is optional and can be commented out for performance
            // $this->recommendationService->refreshProfile($customerId, PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU);

        } catch (\Exception $e) {
            $this->logger->error(
                '[ProductRecommendation] CustomerLoginRefresh error: ' . $e->getMessage()
            );
        }
    }
}
