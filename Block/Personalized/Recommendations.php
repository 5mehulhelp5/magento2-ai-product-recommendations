<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Block\Personalized;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\Url\EncoderInterface as UrlEncoder;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;

/**
 * Block for displaying personalized recommendations
 */
class Recommendations extends AbstractProduct
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
     * @var Config
     */
    private Config $config;

    /**
     * @var PostHelper
     */
    private PostHelper $postHelper;

    /**
     * @var UrlEncoder
     */
    private UrlEncoder $urlEncoder;

    /**
     * @var string
     */
    private string $recommendationType = '';

    /**
     * @var int
     */
    private int $productLimit = 8;

    /**
     * @var array|null
     */
    private ?array $loadedProducts = null;

    /**
     * @param Context $context
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param CustomerSession $customerSession
     * @param Config $config
     * @param PostHelper $postHelper
     * @param UrlEncoder $urlEncoder
     * @param array $data
     */
    public function __construct(
        Context $context,
        PersonalizedRecommendationInterface $recommendationService,
        CustomerSession $customerSession,
        Config $config,
        PostHelper $postHelper,
        UrlEncoder $urlEncoder,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->recommendationService = $recommendationService;
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->postHelper = $postHelper;
        $this->urlEncoder = $urlEncoder;
    }

    /**
     * Set recommendation type
     *
     * @param string $type
     * @return $this
     */
    public function setRecommendationType(string $type): self
    {
        $this->recommendationType = $type;
        $this->loadedProducts = null; // Reset cache
        return $this;
    }

    /**
     * Get recommendation type
     *
     * @return string
     */
    public function getRecommendationType(): string
    {
        return $this->recommendationType ?: $this->getData('recommendation_type') ?: '';
    }

    /**
     * Set product limit
     *
     * @param int $limit
     * @return $this
     */
    public function setProductLimit(int $limit): self
    {
        $this->productLimit = $limit;
        $this->loadedProducts = null;
        return $this;
    }

    /**
     * Get product limit
     *
     * @return int
     */
    public function getProductLimit(): int
    {
        return (int) ($this->getData('product_limit') ?: $this->productLimit);
    }

    /**
     * Get block title based on type
     *
     * @return string
     */
    public function getTitle(): string
    {
        $customTitle = $this->getData('title');
        if ($customTitle) {
            return $customTitle;
        }

        switch ($this->getRecommendationType()) {
            case PersonalizedRecommendationInterface::TYPE_BROWSING:
                return __('Inspired by Your Browsing')->render();
            case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                return __('Based on Your Past Purchases')->render();
            case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                return __('Inspired by Your Wishlist')->render();
            case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
                return __('Recommended Just For You')->render();
            default:
                return __('Recommended Products')->render();
        }
    }

    /**
     * Get personalized products
     *
     * @return ProductInterface[]
     */
    public function getProducts(): array
    {
        if ($this->loadedProducts !== null) {
            return $this->loadedProducts;
        }

        if (!$this->config->isEnabled()) {
            $this->loadedProducts = [];
            return $this->loadedProducts;
        }

        $customerId = $this->getCustomerId();
        $limit = $this->getProductLimit();
        $type = $this->getRecommendationType();

        try {
            switch ($type) {
                case PersonalizedRecommendationInterface::TYPE_BROWSING:
                    $this->loadedProducts = $this->recommendationService->getBrowsingInspired(
                        $customerId,
                        $limit
                    );
                    break;

                case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                    if ($customerId) {
                        $this->loadedProducts = $this->recommendationService->getPurchaseInspired(
                            $customerId,
                            $limit
                        );
                    } else {
                        $this->loadedProducts = [];
                    }
                    break;

                case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                    if ($customerId) {
                        $this->loadedProducts = $this->recommendationService->getWishlistInspired(
                            $customerId,
                            $limit
                        );
                    } else {
                        $this->loadedProducts = [];
                    }
                    break;

                case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
                    if ($customerId) {
                        $this->loadedProducts = $this->recommendationService->getJustForYou(
                            $customerId,
                            $limit
                        );
                    } else {
                        // Fall back to browsing for guests
                        $this->loadedProducts = $this->recommendationService->getBrowsingInspired(
                            null,
                            $limit
                        );
                    }
                    break;

                default:
                    $this->loadedProducts = [];
            }
        } catch (\Exception $e) {
            $this->loadedProducts = [];
        }

        return $this->loadedProducts;
    }

    /**
     * Check if block has products to display
     *
     * @return bool
     */
    public function hasProducts(): bool
    {
        return !empty($this->getProducts());
    }

    /**
     * Check if block should be displayed
     *
     * @return bool
     */
    public function canDisplay(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $type = $this->getRecommendationType();
        $customerId = $this->getCustomerId();

        // Check if we have enough data
        if (!$this->recommendationService->hasEnoughData($customerId, $type)) {
            return false;
        }

        return $this->hasProducts();
    }

    /**
     * Get current customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        if ($this->customerSession->isLoggedIn()) {
            return (int) $this->customerSession->getCustomerId();
        }
        return null;
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Get icon class for block
     *
     * @return string
     */
    public function getIconClass(): string
    {
        switch ($this->getRecommendationType()) {
            case PersonalizedRecommendationInterface::TYPE_BROWSING:
                return 'icon-eye';
            case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                return 'icon-shopping-bag';
            case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                return 'icon-heart';
            case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
                return 'icon-star';
            default:
                return 'icon-gift';
        }
    }

    /**
     * Get post parameters for adding product to cart
     *
     * @param ProductInterface $product
     * @return array|null
     */
    public function getAddToCartPostParams(ProductInterface $product): ?array
    {
        try {
            $url = $this->getAddToCartUrl($product);
            $currentUrl = $this->_urlBuilder->getCurrentUrl();
            return [
                'action' => $url,
                'data' => [
                    'product' => (int)$product->getEntityId(),
                    \Magento\Framework\App\ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlEncoder->encode($currentUrl)
                ]
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if (!$this->canDisplay()) {
            return '';
        }

        return parent::_toHtml();
    }
}
