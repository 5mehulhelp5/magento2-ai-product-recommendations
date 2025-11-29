<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Model\Resolver;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;

/**
 * GraphQL resolver for personalized recommendations
 */
class PersonalizedRecommendations implements ResolverInterface
{
    /**
     * @var PersonalizedRecommendationInterface
     */
    private PersonalizedRecommendationInterface $recommendationService;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        PersonalizedRecommendationInterface $recommendationService,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager
    ) {
        $this->recommendationService = $recommendationService;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $type = $this->convertType($args['type'] ?? 'JUST_FOR_YOU');
        $limit = (int) ($args['limit'] ?? 8);
        
        $customerId = null;
        if ($context->getUserId()) {
            $customerId = (int) $context->getUserId();
        } elseif ($this->customerSession->isLoggedIn()) {
            $customerId = (int) $this->customerSession->getCustomerId();
        }

        $storeId = (int) $this->storeManager->getStore()->getId();

        // Check if customer has enough data
        $hasData = $this->recommendationService->hasEnoughData($customerId, $type);
        
        if (!$hasData) {
            return [
                'items' => [],
                'total_count' => 0,
                'recommendation_type' => $type,
                'has_data' => false
            ];
        }

        // Get recommendations
        $products = $this->getRecommendations($customerId, $type, $limit, $storeId);

        $items = [];
        $position = 1;
        foreach ($products as $product) {
            $items[] = [
                'product' => $product->getData(),
                'score' => 1.0 - ($position * 0.01),
                'position' => $position
            ];
            $position++;
        }

        return [
            'items' => $items,
            'total_count' => count($items),
            'recommendation_type' => $type,
            'has_data' => true
        ];
    }

    /**
     * Convert GraphQL enum to internal type
     *
     * @param string $graphqlType
     * @return string
     */
    private function convertType(string $graphqlType): string
    {
        $mapping = [
            'BROWSING' => PersonalizedRecommendationInterface::TYPE_BROWSING,
            'PURCHASE' => PersonalizedRecommendationInterface::TYPE_PURCHASE,
            'WISHLIST' => PersonalizedRecommendationInterface::TYPE_WISHLIST,
            'JUST_FOR_YOU' => PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU,
        ];

        return $mapping[$graphqlType] ?? PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU;
    }

    /**
     * Get recommendations by type
     *
     * @param int|null $customerId
     * @param string $type
     * @param int $limit
     * @param int $storeId
     * @return array
     */
    private function getRecommendations(?int $customerId, string $type, int $limit, int $storeId): array
    {
        switch ($type) {
            case PersonalizedRecommendationInterface::TYPE_BROWSING:
                return $this->recommendationService->getBrowsingInspired($customerId, $limit, $storeId);
            case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                return $customerId 
                    ? $this->recommendationService->getPurchaseInspired($customerId, $limit, $storeId)
                    : [];
            case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                return $customerId 
                    ? $this->recommendationService->getWishlistInspired($customerId, $limit, $storeId)
                    : [];
            case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
            default:
                return $customerId 
                    ? $this->recommendationService->getJustForYou($customerId, $limit, $storeId)
                    : $this->recommendationService->getBrowsingInspired(null, $limit, $storeId);
        }
    }
}
