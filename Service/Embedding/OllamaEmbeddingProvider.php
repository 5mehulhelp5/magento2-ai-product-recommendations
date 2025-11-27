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

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use Navindbhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Ollama embedding provider for local AI
 */
class OllamaEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * Model dimensions (approximate)
     */
    private const MODEL_DIMENSIONS = [
        'nomic-embed-text' => 768,
        'mxbai-embed-large' => 1024,
        'all-minilm' => 384,
        'snowflake-arctic-embed' => 1024,
    ];

    private const DEFAULT_DIMENSION = 768;

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Client|null
     */
    private ?Client $client = null;

    /**
     * @param ClientFactory $clientFactory
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ClientFactory $clientFactory,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->clientFactory = $clientFactory;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Get HTTP client
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create([
                'config' => [
                    'base_uri' => rtrim($this->config->getOllamaHost(), '/') . '/',
                    'timeout' => 120,
                    'connect_timeout' => 10,
                ],
            ]);
        }
        return $this->client;
    }

    /**
     * @inheritDoc
     */
    public function generateEmbeddings(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $embeddings = [];
        $model = $this->config->getOllamaModel();

        try {
            foreach ($texts as $text) {
                $response = $this->getClient()->post('api/embeddings', [
                    'json' => [
                        'model' => $model,
                        'prompt' => $text,
                    ],
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                if (isset($result['embedding'])) {
                    $embeddings[] = $result['embedding'];
                } else {
                    throw new \RuntimeException('Invalid response from Ollama: no embedding found');
                }
            }

            return $embeddings;
        } catch (GuzzleException $e) {
            $this->logger->error('Ollama embedding generation failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to generate Ollama embeddings: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function generateEmbedding(string $text): array
    {
        $embeddings = $this->generateEmbeddings([$text]);
        return $embeddings[0] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getDimension(): int
    {
        $model = $this->config->getOllamaModel();

        foreach (self::MODEL_DIMENSIONS as $modelName => $dimension) {
            if (stripos($model, $modelName) !== false) {
                return $dimension;
            }
        }

        return self::DEFAULT_DIMENSION;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->getClient()->get('api/tags');
            $result = json_decode($response->getBody()->getContents(), true);
            return isset($result['models']);
        } catch (\Exception $e) {
            $this->logger->debug('Ollama availability check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'ollama';
    }
}
