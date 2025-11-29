<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Controller\Ajax;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * AJAX controller for fetching personalized recommendations
 */
class Personalized implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var PersonalizedRecommendationInterface
     */
    private PersonalizedRecommendationInterface $recommendationService;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var ImageHelper
     */
    private ImageHelper $imageHelper;

    /**
     * @var PricingHelper
     */
    private PricingHelper $pricingHelper;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param JsonFactory $jsonFactory
     * @param RequestInterface $request
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param CustomerSession $customerSession
     * @param ImageHelper $imageHelper
     * @param PricingHelper $pricingHelper
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        JsonFactory $jsonFactory,
        RequestInterface $request,
        PersonalizedRecommendationInterface $recommendationService,
        CustomerSession $customerSession,
        ImageHelper $imageHelper,
        PricingHelper $pricingHelper,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->recommendationService = $recommendationService;
        $this->customerSession = $customerSession;
        $this->imageHelper = $imageHelper;
        $this->pricingHelper = $pricingHelper;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => 'Module is disabled',
                'products' => []
            ]);
        }

        try {
            $type = $this->request->getParam('type', PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU);
            $limit = (int) $this->request->getParam('limit', 8);
            
            $customerId = $this->customerSession->isLoggedIn() 
                ? (int) $this->customerSession->getCustomerId() 
                : null;

            // Get products based on type
            $products = $this->getProducts($customerId, $type, $limit);

            // Format products for JSON response
            $formattedProducts = $this->formatProducts($products);

            return $result->setData([
                'success' => true,
                'type' => $type,
                'count' => count($formattedProducts),
                'products' => $formattedProducts
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] AJAX error: ' . $e->getMessage());
            
            return $result->setData([
                'success' => false,
                'message' => 'Error fetching recommendations',
                'products' => []
            ]);
        }
    }

    /**
     * Get products by recommendation type
     *
     * @param int|null $customerId
     * @param string $type
     * @param int $limit
     * @return array
     */
    private function getProducts(?int $customerId, string $type, int $limit): array
    {
        switch ($type) {
            case PersonalizedRecommendationInterface::TYPE_BROWSING:
                return $this->recommendationService->getBrowsingInspired($customerId, $limit);

            case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                if ($customerId === null) {
                    return [];
                }
                return $this->recommendationService->getPurchaseInspired($customerId, $limit);

            case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                if ($customerId === null) {
                    return [];
                }
                return $this->recommendationService->getWishlistInspired($customerId, $limit);

            case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
                if ($customerId !== null) {
                    return $this->recommendationService->getJustForYou($customerId, $limit);
                }
                return $this->recommendationService->getBrowsingInspired(null, $limit);

            default:
                return [];
        }
    }

    /**
     * Format products for JSON response
     *
     * @param array $products
     * @return array
     */
    private function formatProducts(array $products): array
    {
        $formatted = [];

        foreach ($products as $product) {
            /** @var ProductInterface $product */
            $formatted[] = [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'url' => $product->getProductUrl(),
                'image' => $this->getProductImage($product),
                'price' => $this->pricingHelper->currency($product->getFinalPrice(), true, false),
                'price_raw' => (float) $product->getFinalPrice(),
                'special_price' => $product->getSpecialPrice() 
                    ? $this->pricingHelper->currency($product->getSpecialPrice(), true, false) 
                    : null,
                'in_stock' => $product->isSaleable(),
            ];
        }

        return $formatted;
    }

    /**
     * Get product image URL
     *
     * @param ProductInterface $product
     * @return string
     */
    private function getProductImage(ProductInterface $product): string
    {
        return $this->imageHelper
            ->init($product, 'category_page_grid')
            ->getUrl();
    }
}
