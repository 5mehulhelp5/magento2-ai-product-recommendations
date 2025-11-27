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

use Magento\Catalog\Block\Product\ProductList\Upsell;
use Magento\Framework\Registry;
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin to override upsell products with AI recommendations
 */
class UpsellProducts
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
     * After get items collection - replace with AI recommendations
     *
     * @param Upsell $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetItemCollection(Upsell $subject, $result)
    {
        if (!$this->config->isEnabled() || !$this->config->isUpSellEnabled()) {
            return $result;
        }

        try {
            $product = $this->registry->registry('current_product');

            if (!$product) {
                return $result;
            }

            $aiProducts = $this->recommendationService->getUpSellProducts($product);

            if (empty($aiProducts)) {
                if ($this->config->isFallbackToNativeEnabled()) {
                    return $result;
                }
            }

            if (!empty($aiProducts)) {
                return new \ArrayObject($aiProducts);
            }
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] UpsellProducts plugin error: ' . $e->getMessage());
        }

        return $result;
    }
}
