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

namespace Navindbhudiya\ProductRecommendation\Service\Embedding;

use Magento\Framework\ObjectManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;

/**
 * Factory for embedding providers
 */
class EmbeddingProviderFactory implements EmbeddingProviderInterface
{
    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var array
     */
    private array $providers;

    /**
     * @var EmbeddingProviderInterface|null
     */
    private ?EmbeddingProviderInterface $currentProvider = null;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Config $config
     * @param array $providers
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Config $config,
        array $providers = []
    ) {
        $this->objectManager = $objectManager;
        $this->config = $config;
        $this->providers = $providers;
    }

    /**
     * Get current provider instance
     *
     * @return EmbeddingProviderInterface
     */
    public function getProvider(): EmbeddingProviderInterface
    {
        if ($this->currentProvider === null) {
            $providerCode = $this->config->getEmbeddingProvider();

            if (!isset($this->providers[$providerCode])) {
                throw new \InvalidArgumentException(
                    sprintf('Embedding provider "%s" is not configured', $providerCode)
                );
            }

            $this->currentProvider = $this->objectManager->get($this->providers[$providerCode]);
        }

        return $this->currentProvider;
    }

    /**
     * Create provider by code
     *
     * @param string $providerCode
     * @return EmbeddingProviderInterface
     */
    public function create(string $providerCode): EmbeddingProviderInterface
    {
        if (!isset($this->providers[$providerCode])) {
            throw new \InvalidArgumentException(
                sprintf('Embedding provider "%s" is not configured', $providerCode)
            );
        }

        return $this->objectManager->create($this->providers[$providerCode]);
    }

    /**
     * @inheritDoc
     */
    public function generateEmbeddings(array $texts): array
    {
        return $this->getProvider()->generateEmbeddings($texts);
    }

    /**
     * @inheritDoc
     */
    public function generateEmbedding(string $text): array
    {
        return $this->getProvider()->generateEmbedding($text);
    }

    /**
     * @inheritDoc
     */
    public function getDimension(): int
    {
        return $this->getProvider()->getDimension();
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return $this->getProvider()->isAvailable();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->getProvider()->getName();
    }

    /**
     * Get available provider codes
     *
     * @return array
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }
}
