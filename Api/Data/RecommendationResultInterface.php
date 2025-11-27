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

namespace Navindbhudiya\ProductRecommendation\Api\Data;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Interface for recommendation result with similarity score
 */
interface RecommendationResultInterface
{
    /**
     * Get product
     *
     * @return ProductInterface
     */
    public function getProduct(): ProductInterface;

    /**
     * Set product
     *
     * @param ProductInterface $product
     * @return self
     */
    public function setProduct(ProductInterface $product): self;

    /**
     * Get similarity score (0.0 - 1.0)
     *
     * @return float
     */
    public function getScore(): float;

    /**
     * Set similarity score
     *
     * @param float $score
     * @return self
     */
    public function setScore(float $score): self;

    /**
     * Get distance (lower is more similar)
     *
     * @return float
     */
    public function getDistance(): float;

    /**
     * Set distance
     *
     * @param float $distance
     * @return self
     */
    public function setDistance(float $distance): self;

    /**
     * Get recommendation type
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Set recommendation type
     *
     * @param string $type
     * @return self
     */
    public function setType(string $type): self;

    /**
     * Get metadata
     *
     * @return array
     */
    public function getMetadata(): array;

    /**
     * Set metadata
     *
     * @param array $metadata
     * @return self
     */
    public function setMetadata(array $metadata): self;
}
