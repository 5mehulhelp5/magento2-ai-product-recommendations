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
 * Embedding provider options
 */
class EmbeddingProvider implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'chromadb',
                'label' => __('ChromaDB (all-MiniLM-L6-v2)')
            ],
        ];
    }
}
