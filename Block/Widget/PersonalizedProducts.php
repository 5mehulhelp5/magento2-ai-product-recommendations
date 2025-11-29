<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Block\Widget;

use Magento\Catalog\Block\Product\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Widget\Block\BlockInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Navindbhudiya\ProductRecommendation\Block\Personalized\Recommendations;
use Navindbhudiya\ProductRecommendation\Helper\Config;

/**
 * Widget for displaying personalized recommendations in CMS content
 */
class PersonalizedProducts extends Recommendations implements BlockInterface
{
    /**
     * @var string
     */
    protected $_template = 'Navindbhudiya_ProductRecommendation::personalized/recommendations.phtml';

    /**
     * @inheritDoc
     */
    public function getRecommendationType(): string
    {
        return $this->getData('recommendation_type') 
            ?: PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU;
    }

    /**
     * @inheritDoc
     */
    public function getProductLimit(): int
    {
        return (int) ($this->getData('products_count') ?: 8);
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        $customTitle = $this->getData('block_title');
        if ($customTitle) {
            return $customTitle;
        }
        return parent::getTitle();
    }
}
