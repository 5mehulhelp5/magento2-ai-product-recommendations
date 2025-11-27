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

namespace Navindbhudiya\ProductRecommendation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * OpenAI embedding model options
 */
class OpenAIModel implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'text-embedding-3-small',
                'label' => __('text-embedding-3-small (1536 dimensions, recommended)')
            ],
            [
                'value' => 'text-embedding-3-large',
                'label' => __('text-embedding-3-large (3072 dimensions, higher quality)')
            ],
            [
                'value' => 'text-embedding-ada-002',
                'label' => __('text-embedding-ada-002 (1536 dimensions, legacy)')
            ],
        ];
    }
}
