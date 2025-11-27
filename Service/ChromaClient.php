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

use GuzzleHttp\Client;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Navindbhudiya\ProductRecommendation\Api\ChromaClientInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * ChromaDB HTTP client implementation
 * 
 * Compatible with ChromaDB v0.4.x and v0.5.x+
 */
class ChromaClient implements ChromaClientInterface
{
    private const DEFAULT_TENANT = 'default_tenant';
    private const DEFAULT_DATABASE = 'default_database';

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
     * @var array
     */
    private array $collectionCache = [];

    /**
     * @var string|null
     */
    private ?string $apiVersion = null;

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
     * Get HTTP client (base URL without API path)
     *
     * @return Client
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = $this->clientFactory->create([
                'config' => [
                    'base_uri' => $this->config->getChromaDbUrl() . '/',
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ],
            ]);
        }
        return $this->client;
    }

    /**
     * Detect ChromaDB API version
     *
     * @return string 'v1' for old API, 'v2' for new multi-tenant API
     */
    private function detectApiVersion(): string
    {
        if ($this->apiVersion !== null) {
            return $this->apiVersion;
        }

        try {
            // Get ChromaDB version to determine API
            $versionInfo = $this->getVersion();
            $version = $versionInfo['version'] ?? 'unknown';

            // Parse version number (e.g., "0.4.24" or "0.5.0")
            if (preg_match('/^0\.([0-9]+)/', $version, $matches)) {
                $minorVersion = (int) $matches[1];

                // v0.5.0+ uses multi-tenant API
                if ($minorVersion >= 5) {
                    $this->apiVersion = 'v2';
                    $this->log("Detected ChromaDB v{$version} (multi-tenant API)");
                } else {
                    $this->apiVersion = 'v1';
                    $this->log("Detected ChromaDB v{$version} (legacy API)");
                }
            } else {
                // If we can't parse version, try probing the API
                try {
                    $response = $this->getClient()->get('api/v1/tenants/' . self::DEFAULT_TENANT);
                    if ($response->getStatusCode() === 200) {
                        $this->apiVersion = 'v2';
                        $this->log('Detected ChromaDB v0.5+ (multi-tenant API via probe)');
                    } else {
                        $this->apiVersion = 'v1';
                        $this->log('Detected ChromaDB v0.4.x (legacy API via probe)');
                    }
                } catch (GuzzleException $e) {
                    // Fall back to old API
                    $this->apiVersion = 'v1';
                    $this->log('Detected ChromaDB v0.4.x (legacy API via fallback)');
                }
            }
        } catch (\Exception $e) {
            // Default to v1 if anything fails
            $this->apiVersion = 'v1';
            $this->log('Failed to detect API version, defaulting to legacy API: ' . $e->getMessage());
        }

        return $this->apiVersion;
    }

    /**
     * Get collections endpoint based on API version
     *
     * @return string
     */
    private function getCollectionsEndpoint(): string
    {
        $version = $this->detectApiVersion();
        
        if ($version === 'v2') {
            return 'api/v1/tenants/' . self::DEFAULT_TENANT . '/databases/' . self::DEFAULT_DATABASE . '/collections';
        }
        
        return 'api/v1/collections';
    }

    /**
     * @inheritDoc
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->heartbeat();
            return !empty($response);
        } catch (\Exception $e) {
            $this->log('Connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function heartbeat(): array
    {
        try {
            // Try multiple heartbeat endpoints
            $endpoints = ['api/v1/heartbeat', 'api/v1'];
            
            foreach ($endpoints as $endpoint) {
                try {
                    $response = $this->getClient()->get($endpoint);
                    return $this->parseResponse($response);
                } catch (GuzzleException $e) {
                    continue;
                }
            }
            
            throw new \RuntimeException('All heartbeat endpoints failed');
        } catch (GuzzleException $e) {
            $this->log('Heartbeat failed: ' . $e->getMessage());
            throw new \RuntimeException('ChromaDB heartbeat failed: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function createCollection(string $name, array $metadata = []): array
    {
        try {
            $endpoint = $this->getCollectionsEndpoint();
            
            $payload = [
                'name' => $name,
                'get_or_create' => false,
            ];

            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            $response = $this->getClient()->post($endpoint, [
                'json' => $payload,
            ]);

            $result = $this->parseResponse($response);
            $this->collectionCache[$name] = $result['id'] ?? null;
            return $result;
        } catch (GuzzleException $e) {
            $this->log('Create collection failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create collection: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function getOrCreateCollection(string $name, array $metadata = []): array
    {
        try {
            $endpoint = $this->getCollectionsEndpoint();
            
            $payload = [
                'name' => $name,
                'get_or_create' => true,
            ];

            if (!empty($metadata)) {
                $payload['metadata'] = $metadata;
            }

            $this->log("Creating/getting collection at: {$endpoint}");

            $response = $this->getClient()->post($endpoint, [
                'json' => $payload,
            ]);

            $result = $this->parseResponse($response);
            $this->collectionCache[$name] = $result['id'] ?? null;
            return $result;
        } catch (GuzzleException $e) {
            $this->log('Get or create collection failed: ' . $e->getMessage());
            
            // Try alternative: get existing collection
            try {
                return $this->getCollection($name);
            } catch (\Exception $e2) {
                throw new \RuntimeException('Failed to get/create collection: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get existing collection by name
     *
     * @param string $name
     * @return array
     */
    public function getCollection(string $name): array
    {
        try {
            $endpoint = $this->getCollectionsEndpoint() . '/' . $name;
            $response = $this->getClient()->get($endpoint);
            $result = $this->parseResponse($response);
            $this->collectionCache[$name] = $result['id'] ?? null;
            return $result;
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Collection not found: ' . $name);
        }
    }

    /**
     * Get collection ID by name
     *
     * @param string $name
     * @return string
     */
    public function getCollectionId(string $name): string
    {
        if (isset($this->collectionCache[$name])) {
            return $this->collectionCache[$name];
        }

        $collection = $this->getOrCreateCollection($name);
        return $collection['id'];
    }

    /**
     * @inheritDoc
     */
    public function deleteCollection(string $name): bool
    {
        try {
            $endpoint = $this->getCollectionsEndpoint() . '/' . $name;
            $this->getClient()->delete($endpoint);
            unset($this->collectionCache[$name]);
            return true;
        } catch (GuzzleException $e) {
            $this->log('Delete collection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function addDocuments(
        string $collectionId,
        array $ids,
        array $documents,
        array $metadatas = [],
        ?array $embeddings = null
    ): bool {
        try {
            $payload = [
                'ids' => $ids,
            ];

            // Documents are optional if embeddings are provided
            if (!empty($documents)) {
                $payload['documents'] = $documents;
            }

            // Embeddings are REQUIRED for HTTP API
            if ($embeddings !== null && !empty($embeddings)) {
                $payload['embeddings'] = $embeddings;
            }

            if (!empty($metadatas)) {
                $payload['metadatas'] = $metadatas;
            }

            $this->getClient()->post("api/v1/collections/{$collectionId}/add", [
                'json' => $payload,
            ]);

            return true;
        } catch (GuzzleException $e) {
            $this->log('Add documents failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to add documents: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function updateDocuments(
        string $collectionId,
        array $ids,
        array $documents,
        array $metadatas = [],
        ?array $embeddings = null
    ): bool {
        try {
            $payload = [
                'ids' => $ids,
            ];

            if (!empty($documents)) {
                $payload['documents'] = $documents;
            }

            if ($embeddings !== null && !empty($embeddings)) {
                $payload['embeddings'] = $embeddings;
            }

            if (!empty($metadatas)) {
                $payload['metadatas'] = $metadatas;
            }

            $this->getClient()->post("api/v1/collections/{$collectionId}/update", [
                'json' => $payload,
            ]);

            return true;
        } catch (GuzzleException $e) {
            $this->log('Update documents failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to update documents: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function upsertDocuments(
        string $collectionId,
        array $ids,
        array $documents,
        array $metadatas = [],
        ?array $embeddings = null
    ): bool {
        try {
            $payload = [
                'ids' => $ids,
            ];

            if (!empty($documents)) {
                $payload['documents'] = $documents;
            }

            // Embeddings are REQUIRED for HTTP API
            if ($embeddings !== null && !empty($embeddings)) {
                $payload['embeddings'] = $embeddings;
            } else {
                throw new \RuntimeException('Embeddings are required for upsert operation');
            }

            if (!empty($metadatas)) {
                $payload['metadatas'] = $metadatas;
            }

            $this->getClient()->post("api/v1/collections/{$collectionId}/upsert", [
                'json' => $payload,
            ]);

            return true;
        } catch (GuzzleException $e) {
            $this->log('Upsert documents failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to upsert documents: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteDocuments(string $collectionId, array $ids): bool
    {
        try {
            $this->getClient()->post("api/v1/collections/{$collectionId}/delete", [
                'json' => ['ids' => $ids],
            ]);

            return true;
        } catch (GuzzleException $e) {
            $this->log('Delete documents failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function query(
        string $collectionId,
        array $queryTexts = [],
        int $nResults = 10,
        array $where = [],
        array $whereDocument = [],
        ?array $queryEmbeddings = null
    ): array {
        try {
            $payload = [
                'n_results' => $nResults,
                'include' => ['documents', 'metadatas', 'distances'],
            ];

            // IMPORTANT: Use query_embeddings, NOT query_texts
            // ChromaDB HTTP API doesn't support server-side embedding
            if ($queryEmbeddings !== null && !empty($queryEmbeddings)) {
                $payload['query_embeddings'] = $queryEmbeddings;
            } elseif (!empty($queryTexts)) {
                // This will likely fail with HTTP API, but include for completeness
                $payload['query_texts'] = $queryTexts;
                $this->log('WARNING: Using query_texts which may not work with HTTP API');
            }

            if (!empty($where)) {
                $payload['where'] = $where;
            }

            if (!empty($whereDocument)) {
                $payload['where_document'] = $whereDocument;
            }

            $response = $this->getClient()->post("api/v1/collections/{$collectionId}/query", [
                'json' => $payload,
            ]);

            return $this->parseResponse($response);
        } catch (GuzzleException $e) {
            $this->log('Query failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to query collection: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function getDocuments(string $collectionId, array $ids): array
    {
        try {
            $response = $this->getClient()->post("api/v1/collections/{$collectionId}/get", [
                'json' => [
                    'ids' => $ids,
                    'include' => ['documents', 'metadatas'],
                ],
            ]);

            return $this->parseResponse($response);
        } catch (GuzzleException $e) {
            $this->log('Get documents failed: ' . $e->getMessage());
            throw new \RuntimeException('Failed to get documents: ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function count(string $collectionId): int
    {
        try {
            $response = $this->getClient()->get("api/v1/collections/{$collectionId}/count");
            $result = $this->parseResponse($response);
            return (int) $result;
        } catch (GuzzleException $e) {
            $this->log('Count failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @inheritDoc
     */
    public function peek(string $collectionId, int $limit = 10): array
    {
        try {
            $response = $this->getClient()->get("api/v1/collections/{$collectionId}/peek", [
                'query' => ['limit' => $limit],
            ]);

            return $this->parseResponse($response);
        } catch (GuzzleException $e) {
            $this->log('Peek failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * List all collections
     *
     * @return array
     */
    public function listCollections(): array
    {
        try {
            $endpoint = $this->getCollectionsEndpoint();
            $response = $this->getClient()->get($endpoint);
            return $this->parseResponse($response);
        } catch (GuzzleException $e) {
            $this->log('List collections failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get ChromaDB version info
     *
     * @return array
     */
    public function getVersion(): array
    {
        try {
            $response = $this->getClient()->get('api/v1/version');
            $result = $this->parseResponse($response);

            // Ensure we always return an array
            if (!is_array($result)) {
                return ['version' => (string) $result];
            }

            return $result;
        } catch (GuzzleException $e) {
            return ['version' => 'unknown'];
        }
    }

    /**
     * Parse response body
     *
     * @param Response $response
     * @return array|mixed
     */
    private function parseResponse(Response $response)
    {
        $body = $response->getBody()->getContents();
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $body;
        }

        return $decoded;
    }

    /**
     * Log message if debug mode is enabled
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    private function log(string $message, array $context = []): void
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('[ProductRecommendation] ' . $message, $context);
        }
    }
}
