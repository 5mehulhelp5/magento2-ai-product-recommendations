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

namespace Navindbhudiya\ProductRecommendation\Plugin\Checkout;

use Magento\Checkout\Block\Cart\Crosssell;
use Magento\Checkout\Model\Session as CheckoutSession;
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin to override crosssell products with AI recommendations
 */
class CrosssellProducts
{
    /**
     * @var RecommendationServiceInterface
     */
    private RecommendationServiceInterface $recommendationService;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param RecommendationServiceInterface $recommendationService
     * @param Config $config
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        RecommendationServiceInterface $recommendationService,
        Config $config,
        CheckoutSession $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->recommendationService = $recommendationService;
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * After get items - replace with AI recommendations
     *
     * @param Crosssell $subject
     * @param array $result
     * @return array
     */
    public function afterGetItems(Crosssell $subject, $result)
    {
        if (!$this->config->isEnabled() || !$this->config->isCrossSellEnabled()) {
            return $result;
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            $items = $quote->getAllVisibleItems();

            if (empty($items)) {
                return $result;
            }

            // Get AI cross-sell recommendations for the last added item
            $lastItem = end($items);
            $product = $lastItem->getProduct();

            if (!$product) {
                return $result;
            }

            $aiProducts = $this->recommendationService->getCrossSellProducts($product);

            if (empty($aiProducts)) {
                if ($this->config->isFallbackToNativeEnabled()) {
                    return $result;
                }
            }

            // Filter out products already in cart
            $cartProductIds = [];
            foreach ($items as $item) {
                $cartProductIds[] = $item->getProduct()->getId();
            }

            $filteredProducts = [];
            foreach ($aiProducts as $aiProduct) {
                if (!in_array($aiProduct->getId(), $cartProductIds)) {
                    $filteredProducts[] = $aiProduct;
                }
            }

            if (!empty($filteredProducts)) {
                return $filteredProducts;
            }
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] CrosssellProducts plugin error: ' . $e->getMessage());
        }

        return $result;
    }
}
