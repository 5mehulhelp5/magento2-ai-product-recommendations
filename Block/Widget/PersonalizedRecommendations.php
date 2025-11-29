<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Navindbhudiya\ProductRecommendation\Block\Personalized\Recommendations;

/**
 * Widget for displaying personalized recommendations
 */
class PersonalizedRecommendations extends Recommendations implements BlockInterface
{
    /**
     * @var string
     */
    protected $_template = 'Navindbhudiya_ProductRecommendation::personalized/recommendations.phtml';

    /**
     * Get widget title
     *
     * @return string
     */
    public function getWidgetTitle(): string
    {
        return $this->getData('title') ?: $this->getTitle();
    }

    /**
     * Get show title flag
     *
     * @return bool
     */
    public function getShowTitle(): bool
    {
        return (bool) ($this->getData('show_title') ?? true);
    }

    /**
     * Get CSS classes for widget
     *
     * @return string
     */
    public function getWidgetCssClass(): string
    {
        $classes = ['widget', 'widget-personalized-recommendations'];
        
        if ($this->getData('css_class')) {
            $classes[] = $this->getData('css_class');
        }
        
        return implode(' ', $classes);
    }
}
