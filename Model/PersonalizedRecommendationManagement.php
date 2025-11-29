<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Model;

use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationManagementInterface;
use Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface;
use Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterfaceFactory;

/**
 * REST API implementation for personalized recommendations
 */
class PersonalizedRecommendationManagement implements PersonalizedRecommendationManagementInterface
{
    /**
     * @var PersonalizedRecommendationInterface
     */
    private PersonalizedRecommendationInterface $recommendationService;

    /**
     * @var RecommendationResultInterfaceFactory
     */
    private RecommendationResultInterfaceFactory $resultFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param RecommendationResultInterfaceFactory $resultFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        PersonalizedRecommendationInterface $recommendationService,
        RecommendationResultInterfaceFactory $resultFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->recommendationService = $recommendationService;
        $this->resultFactory = $resultFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function getBrowsingInspired(int $customerId, int $limit = 8): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $products = $this->recommendationService->getBrowsingInspired($customerId, $limit, $storeId);
        
        return $this->convertProductsToResults($products, 'browsing');
    }

    /**
     * @inheritDoc
     */
    public function getPurchaseInspired(int $customerId, int $limit = 8): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $products = $this->recommendationService->getPurchaseInspired($customerId, $limit, $storeId);
        
        return $this->convertProductsToResults($products, 'purchase');
    }

    /**
     * @inheritDoc
     */
    public function getWishlistInspired(int $customerId, int $limit = 8): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $products = $this->recommendationService->getWishlistInspired($customerId, $limit, $storeId);
        
        return $this->convertProductsToResults($products, 'wishlist');
    }

    /**
     * @inheritDoc
     */
    public function getJustForYou(int $customerId, int $limit = 12): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $products = $this->recommendationService->getJustForYou($customerId, $limit, $storeId);
        
        return $this->convertProductsToResults($products, 'just_for_you');
    }

    /**
     * @inheritDoc
     */
    public function getGuestBrowsingInspired(int $limit = 8): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $products = $this->recommendationService->getBrowsingInspired(null, $limit, $storeId);
        
        return $this->convertProductsToResults($products, 'guest_browsing');
    }

    /**
     * @inheritDoc
     */
    public function getCustomerRecommendations(int $customerId, string $type, int $limit = 8): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        
        switch ($type) {
            case PersonalizedRecommendationInterface::TYPE_BROWSING:
                $products = $this->recommendationService->getBrowsingInspired($customerId, $limit, $storeId);
                break;
            case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                $products = $this->recommendationService->getPurchaseInspired($customerId, $limit, $storeId);
                break;
            case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                $products = $this->recommendationService->getWishlistInspired($customerId, $limit, $storeId);
                break;
            case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
            default:
                $products = $this->recommendationService->getJustForYou($customerId, $limit, $storeId);
                break;
        }
        
        return $this->convertProductsToResults($products, $type);
    }

    /**
     * @inheritDoc
     */
    public function refreshCustomerProfile(int $customerId, ?string $type = null): bool
    {
        try {
            if ($type) {
                $this->recommendationService->refreshProfile($customerId, $type);
            } else {
                // Refresh all types
                $types = [
                    PersonalizedRecommendationInterface::TYPE_BROWSING,
                    PersonalizedRecommendationInterface::TYPE_PURCHASE,
                    PersonalizedRecommendationInterface::TYPE_WISHLIST,
                    PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU,
                ];
                
                foreach ($types as $profileType) {
                    $this->recommendationService->refreshProfile($customerId, $profileType);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Convert product objects to recommendation results
     *
     * @param array $products
     * @param string $type
     * @return RecommendationResultInterface[]
     */
    private function convertProductsToResults(array $products, string $type): array
    {
        $results = [];
        $position = 1;
        
        foreach ($products as $product) {
            /** @var RecommendationResultInterface $result */
            $result = $this->resultFactory->create();
            $result->setProductId((int) $product->getId());
            $result->setSku($product->getSku());
            $result->setName($product->getName());
            $result->setScore(1.0 - ($position * 0.01)); // Approximate score based on position
            $result->setRecommendationType($type);
            $result->setPosition($position);
            
            $results[] = $result;
            $position++;
        }
        
        return $results;
    }
}
