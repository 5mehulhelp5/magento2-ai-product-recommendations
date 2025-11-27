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

namespace Navindbhudiya\ProductRecommendation\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Navindbhudiya\ProductRecommendation\Helper\Config;

/**
 * Builds text representation of products for embedding
 */
class ProductTextBuilder
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @param Config $config
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        Config $config,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->config = $config;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Build text for embedding from product
     *
     * @param ProductInterface|Product $product
     * @param int|null $storeId
     * @return string
     */
    public function buildText(ProductInterface $product, ?int $storeId = null): string
    {
        $parts = [];
        $attributes = $this->config->getProductAttributes();

        foreach ($attributes as $attributeCode) {
            $value = $this->getAttributeValue($product, $attributeCode);
            if (!empty($value)) {
                $parts[] = $this->cleanText($value);
            }
        }

        // Add category names if configured
        if ($this->config->includeCategories()) {
            $categoryNames = $this->getCategoryNames($product);
            if (!empty($categoryNames)) {
                $parts[] = 'Categories: ' . implode(', ', $categoryNames);
            }
        }

        return implode('. ', array_filter($parts));
    }

    /**
     * Build text for multiple products
     *
     * @param ProductInterface[] $products
     * @param int|null $storeId
     * @return array [productId => text]
     */
    public function buildTexts(array $products, ?int $storeId = null): array
    {
        $texts = [];
        foreach ($products as $product) {
            $texts[$product->getId()] = $this->buildText($product, $storeId);
        }
        return $texts;
    }

    /**
     * Generate hash for embedding text (to detect changes)
     *
     * @param string $text
     * @return string
     */
    public function generateHash(string $text): string
    {
        return md5($text);
    }

    /**
     * Get attribute value from product
     *
     * @param ProductInterface|Product $product
     * @param string $attributeCode
     * @return string
     */
    private function getAttributeValue(ProductInterface $product, string $attributeCode): string
    {
        $value = '';

        if ($product instanceof Product) {
            // Try to get the text value for select/multiselect attributes
            $attribute = $product->getResource()->getAttribute($attributeCode);
            if ($attribute && $attribute->usesSource()) {
                $value = $product->getAttributeText($attributeCode);
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
            } else {
                $value = $product->getData($attributeCode);
            }
        } else {
            $customAttributes = $product->getCustomAttributes();
            foreach ($customAttributes as $attribute) {
                if ($attribute->getAttributeCode() === $attributeCode) {
                    $value = $attribute->getValue();
                    break;
                }
            }

            if (empty($value)) {
                // Try common getters
                $getter = 'get' . str_replace('_', '', ucwords($attributeCode, '_'));
                if (method_exists($product, $getter)) {
                    $value = $product->$getter();
                }
            }
        }

        return (string) ($value ?? '');
    }

    /**
     * Get category names for product
     *
     * @param ProductInterface|Product $product
     * @return array
     */
    private function getCategoryNames(ProductInterface $product): array
    {
        $categoryIds = [];

        if ($product instanceof Product) {
            $categoryIds = $product->getCategoryIds();
        } elseif (method_exists($product, 'getCategoryIds')) {
            $categoryIds = $product->getCategoryIds();
        }

        if (empty($categoryIds)) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addFieldToFilter('entity_id', ['in' => $categoryIds])
            ->addFieldToFilter('level', ['gt' => 1]); // Exclude root categories

        $names = [];
        foreach ($collection as $category) {
            $names[] = $category->getName();
        }

        return $names;
    }

    /**
     * Clean text for embedding
     *
     * @param string $text
     * @return string
     */
    private function cleanText(string $text): string
    {
        // Remove HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        $text = trim($text);

        return $text;
    }
}
