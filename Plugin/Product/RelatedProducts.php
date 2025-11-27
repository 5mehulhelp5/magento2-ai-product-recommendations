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

namespace Navindbhudiya\ProductRecommendation\Plugin\Product;

use Magento\Catalog\Block\Product\ProductList\Related;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Registry;
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin to override related products with AI recommendations
 */
class RelatedProducts
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
     * @var Registry
     */
    private Registry $registry;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param RecommendationServiceInterface $recommendationService
     * @param Config $config
     * @param Registry $registry
     * @param LoggerInterface $logger
     */
    public function __construct(
        RecommendationServiceInterface $recommendationService,
        Config $config,
        Registry $registry,
        LoggerInterface $logger
    ) {
        $this->recommendationService = $recommendationService;
        $this->config = $config;
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     * After get items - replace with AI recommendations
     *
     * @param Related $subject
     * @param Collection $result
     * @return Collection
     */
    public function afterGetItems(Related $subject, $result)
    {
        if (!$this->config->isEnabled() || !$this->config->isRelatedEnabled()) {
            return $result;
        }

        try {
            $product = $this->registry->registry('current_product');

            if (!$product) {
                return $result;
            }

            $aiProducts = $this->recommendationService->getRelatedProducts($product);
            if (empty($aiProducts)) {
                // Fallback to native if configured
                if ($this->config->isFallbackToNativeEnabled()) {
                    return $result;
                }
            }

            // Return AI recommendations as collection
            if (!empty($aiProducts)) {
                // Convert array to collection-like object
                return $this->createProductCollection($aiProducts);
            }
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] RelatedProducts plugin error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Create a pseudo-collection from product array
     *
     * @param array $products
     * @return \ArrayObject
     */
    private function createProductCollection(array $products)
    {
        return new \ArrayObject($products);
    }
}
