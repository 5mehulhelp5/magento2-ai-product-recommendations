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

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product attributes options for embedding generation
 */
class ProductAttributes implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $attributeCollectionFactory;

    /**
     * @var array|null
     */
    private ?array $options = null;

    /**
     * @param CollectionFactory $attributeCollectionFactory
     */
    public function __construct(CollectionFactory $attributeCollectionFactory)
    {
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = [];

            // Add common text attributes first
            $commonAttributes = [
                'name' => 'Product Name',
                'short_description' => 'Short Description',
                'description' => 'Description',
                'meta_title' => 'Meta Title',
                'meta_description' => 'Meta Description',
                'meta_keywords' => 'Meta Keywords',
            ];

            foreach ($commonAttributes as $code => $label) {
                $this->options[] = [
                    'value' => $code,
                    'label' => __($label),
                ];
            }

            // Add separator
            $this->options[] = [
                'value' => '',
                'label' => '──────────────',
            ];

            // Get other text-based attributes
            $collection = $this->attributeCollectionFactory->create();
            $collection->addFieldToFilter('backend_type', ['in' => ['varchar', 'text']])
                ->addFieldToFilter('frontend_input', ['in' => ['text', 'textarea', 'select', 'multiselect']])
                ->addFieldToFilter('attribute_code', ['nin' => array_keys($commonAttributes)])
                ->setOrder('frontend_label', 'ASC');

            foreach ($collection as $attribute) {
                $label = $attribute->getFrontendLabel();
                if (!empty($label)) {
                    $this->options[] = [
                        'value' => $attribute->getAttributeCode(),
                        'label' => $label . ' (' . $attribute->getAttributeCode() . ')',
                    ];
                }
            }
        }

        return $this->options;
    }
}
