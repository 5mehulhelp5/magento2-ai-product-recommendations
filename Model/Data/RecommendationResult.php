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

namespace Navindbhudiya\ProductRecommendation\Model\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Navindbhudiya\ProductRecommendation\Api\Data\RecommendationResultInterface;

/**
 * Recommendation result data model
 */
class RecommendationResult extends DataObject implements RecommendationResultInterface
{
    private const KEY_PRODUCT = 'product';
    private const KEY_SCORE = 'score';
    private const KEY_DISTANCE = 'distance';
    private const KEY_TYPE = 'type';
    private const KEY_METADATA = 'metadata';

    /**
     * @inheritDoc
     */
    public function getProduct(): ProductInterface
    {
        return $this->getData(self::KEY_PRODUCT);
    }

    /**
     * @inheritDoc
     */
    public function setProduct(ProductInterface $product): RecommendationResultInterface
    {
        return $this->setData(self::KEY_PRODUCT, $product);
    }

    /**
     * @inheritDoc
     */
    public function getScore(): float
    {
        return (float) $this->getData(self::KEY_SCORE);
    }

    /**
     * @inheritDoc
     */
    public function setScore(float $score): RecommendationResultInterface
    {
        return $this->setData(self::KEY_SCORE, $score);
    }

    /**
     * @inheritDoc
     */
    public function getDistance(): float
    {
        return (float) $this->getData(self::KEY_DISTANCE);
    }

    /**
     * @inheritDoc
     */
    public function setDistance(float $distance): RecommendationResultInterface
    {
        return $this->setData(self::KEY_DISTANCE, $distance);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return (string) $this->getData(self::KEY_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): RecommendationResultInterface
    {
        return $this->setData(self::KEY_TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return (array) ($this->getData(self::KEY_METADATA) ?? []);
    }

    /**
     * @inheritDoc
     */
    public function setMetadata(array $metadata): RecommendationResultInterface
    {
        return $this->setData(self::KEY_METADATA, $metadata);
    }
}
