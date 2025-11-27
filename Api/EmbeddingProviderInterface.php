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

namespace Navindbhudiya\ProductRecommendation\Api;

/**
 * Interface for embedding providers
 */
interface EmbeddingProviderInterface
{
    /**
     * Generate embeddings for given texts
     *
     * @param array $texts
     * @return array Array of embedding vectors
     */
    public function generateEmbeddings(array $texts): array;

    /**
     * Generate embedding for a single text
     *
     * @param string $text
     * @return array Embedding vector
     */
    public function generateEmbedding(string $text): array;

    /**
     * Get the dimension of embeddings from this provider
     *
     * @return int
     */
    public function getDimension(): int;

    /**
     * Check if the provider is available and configured
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getName(): string;
}
