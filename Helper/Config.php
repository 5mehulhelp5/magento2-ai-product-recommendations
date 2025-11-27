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

namespace Navindbhudiya\ProductRecommendation\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration helper
 */
class Config extends AbstractHelper
{
    private const XML_PATH_GENERAL_ENABLED = 'product_recommendation/general/enabled';
    private const XML_PATH_GENERAL_DEBUG = 'product_recommendation/general/debug_mode';

    private const XML_PATH_CHROMADB_HOST = 'product_recommendation/chromadb/host';
    private const XML_PATH_CHROMADB_PORT = 'product_recommendation/chromadb/port';
    private const XML_PATH_CHROMADB_COLLECTION = 'product_recommendation/chromadb/collection_name';

    private const XML_PATH_EMBEDDING_PROVIDER = 'product_recommendation/embedding/provider';
    private const XML_PATH_EMBEDDING_OPENAI_KEY = 'product_recommendation/embedding/openai_api_key';
    private const XML_PATH_EMBEDDING_OPENAI_MODEL = 'product_recommendation/embedding/openai_model';
    private const XML_PATH_EMBEDDING_OLLAMA_HOST = 'product_recommendation/embedding/ollama_host';
    private const XML_PATH_EMBEDDING_OLLAMA_MODEL = 'product_recommendation/embedding/ollama_model';
    private const XML_PATH_EMBEDDING_ATTRIBUTES = 'product_recommendation/embedding/product_attributes';
    private const XML_PATH_EMBEDDING_CATEGORIES = 'product_recommendation/embedding/include_categories';

    private const XML_PATH_RELATED_ENABLED = 'product_recommendation/recommendation/related_enabled';
    private const XML_PATH_RELATED_COUNT = 'product_recommendation/recommendation/related_count';
    private const XML_PATH_CROSSSELL_ENABLED = 'product_recommendation/recommendation/crosssell_enabled';
    private const XML_PATH_CROSSSELL_COUNT = 'product_recommendation/recommendation/crosssell_count';
    private const XML_PATH_UPSELL_ENABLED = 'product_recommendation/recommendation/upsell_enabled';
    private const XML_PATH_UPSELL_COUNT = 'product_recommendation/recommendation/upsell_count';
    private const XML_PATH_SIMILARITY_THRESHOLD = 'product_recommendation/recommendation/similarity_threshold';
    private const XML_PATH_EXCLUDE_SAME_CATEGORY = 'product_recommendation/recommendation/exclude_same_category';
    private const XML_PATH_UPSELL_PRICE_THRESHOLD = 'product_recommendation/recommendation/upsell_price_threshold';
    private const XML_PATH_FALLBACK_NATIVE = 'product_recommendation/recommendation/fallback_to_native';

    private const XML_PATH_CACHE_ENABLED = 'product_recommendation/cache/enabled';
    private const XML_PATH_CACHE_LIFETIME = 'product_recommendation/cache/lifetime';

    /**
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param Context $context
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->encryptor = $encryptor;
    }

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERAL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_GENERAL_DEBUG);
    }

    /**
     * Get ChromaDB host
     *
     * @return string
     */
    public function getChromaDbHost(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CHROMADB_HOST) ?: 'chromadb';
    }

    /**
     * Get ChromaDB port
     *
     * @return int
     */
    public function getChromaDbPort(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_PATH_CHROMADB_PORT) ?: 8000);
    }

    /**
     * Get ChromaDB collection name
     *
     * @return string
     */
    public function getCollectionName(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CHROMADB_COLLECTION) ?: 'magento_products';
    }

    /**
     * Get ChromaDB base URL
     *
     * @return string
     */
    public function getChromaDbUrl(): string
    {
        return sprintf('http://%s:%d', $this->getChromaDbHost(), $this->getChromaDbPort());
    }

    /**
     * Get embedding provider
     *
     * @return string
     */
    public function getEmbeddingProvider(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMBEDDING_PROVIDER) ?: 'chromadb';
    }

    /**
     * Get OpenAI API key
     *
     * @return string
     */
    public function getOpenAiApiKey(): string
    {
        $encrypted = $this->scopeConfig->getValue(self::XML_PATH_EMBEDDING_OPENAI_KEY);
        return $encrypted ? $this->encryptor->decrypt($encrypted) : '';
    }

    /**
     * Get OpenAI model
     *
     * @return string
     */
    public function getOpenAiModel(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMBEDDING_OPENAI_MODEL) ?: 'text-embedding-3-small';
    }

    /**
     * Get Ollama host
     *
     * @return string
     */
    public function getOllamaHost(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMBEDDING_OLLAMA_HOST) ?: 'http://ollama:11434';
    }

    /**
     * Get Ollama model
     *
     * @return string
     */
    public function getOllamaModel(): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMBEDDING_OLLAMA_MODEL) ?: 'nomic-embed-text';
    }

    /**
     * Get product attributes for embedding
     *
     * @return array
     */
    public function getProductAttributes(): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_EMBEDDING_ATTRIBUTES);
        if (empty($value)) {
            return ['name', 'short_description', 'description', 'meta_keywords'];
        }
        return is_string($value) ? explode(',', $value) : (array) $value;
    }

    /**
     * Check if categories should be included in embedding
     *
     * @return bool
     */
    public function includeCategories(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_EMBEDDING_CATEGORIES);
    }

    /**
     * Check if related products are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isRelatedEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RELATED_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get related products count
     *
     * @param int|null $storeId
     * @return int
     */
    public function getRelatedCount(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_RELATED_COUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 4;
    }

    /**
     * Check if cross-sell products are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCrossSellEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CROSSSELL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get cross-sell products count
     *
     * @param int|null $storeId
     * @return int
     */
    public function getCrossSellCount(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_CROSSSELL_COUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 4;
    }

    /**
     * Check if up-sell products are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isUpSellEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_UPSELL_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get up-sell products count
     *
     * @param int|null $storeId
     * @return int
     */
    public function getUpSellCount(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_UPSELL_COUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 4;
    }

    /**
     * Get similarity threshold
     *
     * @param int|null $storeId
     * @return float
     */
    public function getSimilarityThreshold(?int $storeId = null): float
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_SIMILARITY_THRESHOLD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value !== null ? (float) $value : 0.5;
    }

    /**
     * Check if same category should be excluded for cross-sell
     *
     * @param int|null $storeId
     * @return bool
     */
    public function excludeSameCategoryForCrossSell(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_SAME_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get up-sell price threshold percentage
     *
     * @param int|null $storeId
     * @return int
     */
    public function getUpSellPriceThreshold(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_UPSELL_PRICE_THRESHOLD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 10;
    }

    /**
     * Check if fallback to native is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isFallbackToNativeEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FALLBACK_NATIVE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if cache is enabled
     *
     * @return bool
     */
    public function isCacheEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CACHE_ENABLED);
    }

    /**
     * Get cache lifetime in seconds
     *
     * @return int
     */
    public function getCacheLifetime(): int
    {
        return (int) ($this->scopeConfig->getValue(self::XML_PATH_CACHE_LIFETIME) ?: 3600);
    }
}
