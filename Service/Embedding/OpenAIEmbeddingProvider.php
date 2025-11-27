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
 * OpenAI embedding provider
 */
class OpenAIEmbeddingProvider implements EmbeddingProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/embeddings';

    /**
     * Model dimensions
     */
    private const MODEL_DIMENSIONS = [
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
        'text-embedding-ada-002' => 1536,
    ];

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
                    'timeout' => 60,
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

        $apiKey = $this->config->getOpenAiApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is not configured');
        }

        try {
            // Process in batches to avoid rate limits
            $batchSize = 100;
            $embeddings = [];

            foreach (array_chunk($texts, $batchSize) as $batch) {
                $response = $this->getClient()->post(self::API_URL, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $this->config->getOpenAiModel(),
                        'input' => $batch,
                    ],
                ]);

                $result = json_decode($response->getBody()->getContents(), true);

                if (isset($result['data'])) {
                    foreach ($result['data'] as $item) {
                        $embeddings[] = $item['embedding'];
                    }
                }
            }

            return $embeddings;
        } catch (GuzzleException $e) {
            $this->logger->error('OpenAI embedding generation failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to generate OpenAI embeddings: ' . $e->getMessage());
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
        $model = $this->config->getOpenAiModel();
        return self::MODEL_DIMENSIONS[$model] ?? 1536;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        $apiKey = $this->config->getOpenAiApiKey();
        return !empty($apiKey);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'openai';
    }
}
