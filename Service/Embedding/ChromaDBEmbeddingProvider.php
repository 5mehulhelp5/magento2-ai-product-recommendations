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
 * ChromaDB embedding provider using a separate embedding service
 *
 * IMPORTANT: ChromaDB's REST API does NOT support query_texts without a
 * server-side embedding function. This provider requires the embedding-service
 * container to be running.
 *
 * The embedding-service is a lightweight Python service included in the
 * docker/ folder that uses sentence-transformers (all-MiniLM-L6-v2 model)
 * to generate embeddings with 384 dimensions.
 */
class ChromaDBEmbeddingProvider implements EmbeddingProviderInterface
{
    /**
     * Default embedding dimension (all-MiniLM-L6-v2)
     */
    private const EMBEDDING_DIMENSION = 384;

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
     * Get HTTP client for embedding service
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            // Embedding service runs on same host as chromadb, port 8001
            $host = $this->config->getChromaDbHost();
            // Handle case where host might be 'chromadb' in docker
            if ($host === 'chromadb') {
                $host = 'embedding-service';
            }
            
            $this->client = $this->clientFactory->create([
                'config' => [
                    'base_uri' => "http://{$host}:8001/",
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

        try {
            $response = $this->getClient()->post('embed', [
                'json' => ['texts' => $texts],
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['embeddings'])) {
                return $result['embeddings'];
            }

            $this->logger->error('[ProductRecommendation] Invalid embedding response: ' . json_encode($result));
            return [];
        } catch (GuzzleException $e) {
            $this->logger->error('[ProductRecommendation] Embedding service error: ' . $e->getMessage());
            $this->logger->error('[ProductRecommendation] Make sure the embedding-service container is running!');
            return [];
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Embedding error: ' . $e->getMessage());
            return [];
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
        return self::EMBEDDING_DIMENSION;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->getClient()->get('health');
            $result = json_decode($response->getBody()->getContents(), true);
            return ($result['status'] ?? '') === 'ok';
        } catch (\Exception $e) {
            $this->logger->debug('[ProductRecommendation] Embedding service not available: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'chromadb';
    }
}
