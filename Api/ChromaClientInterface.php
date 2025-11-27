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
 * Interface for ChromaDB client operations
 */
interface ChromaClientInterface
{
    /**
     * Test connection to ChromaDB server
     *
     * @return bool
     */
    public function testConnection(): bool;

    /**
     * Get server heartbeat/version info
     *
     * @return array
     */
    public function heartbeat(): array;

    /**
     * Create a new collection
     *
     * @param string $name
     * @param array $metadata
     * @return array
     */
    public function createCollection(string $name, array $metadata = []): array;

    /**
     * Get or create a collection
     *
     * @param string $name
     * @param array $metadata
     * @return array
     */
    public function getOrCreateCollection(string $name, array $metadata = []): array;

    /**
     * Delete a collection
     *
     * @param string $name
     * @return bool
     */
    public function deleteCollection(string $name): bool;

    /**
     * Add documents to collection
     *
     * @param string $collectionId
     * @param array $ids
     * @param array $documents
     * @param array $metadatas
     * @param array|null $embeddings
     * @return bool
     */
    public function addDocuments(
        string $collectionId,
        array $ids,
        array $documents,
        array $metadatas = [],
        ?array $embeddings = null
    ): bool;

    /**
     * Update documents in collection
     *
     * @param string $collectionId
     * @param array $ids
     * @param array $documents
     * @param array $metadatas
     * @param array|null $embeddings
     * @return bool
     */
    public function updateDocuments(
        string $collectionId,
        array $ids,
        array $documents,
        array $metadatas = [],
        ?array $embeddings = null
    ): bool;

    /**
     * Upsert documents in collection
     *
     * @param string $collectionId
     * @param array $ids
     * @param array $documents
     * @param array $metadatas
     * @param array|null $embeddings
     * @return bool
     */
    public function upsertDocuments(
        string $collectionId,
        array $ids,
        array $documents,
        array $metadatas = [],
        ?array $embeddings = null
    ): bool;

    /**
     * Delete documents from collection
     *
     * @param string $collectionId
     * @param array $ids
     * @return bool
     */
    public function deleteDocuments(string $collectionId, array $ids): bool;

    /**
     * Query collection for similar documents
     *
     * @param string $collectionId
     * @param array $queryTexts
     * @param int $nResults
     * @param array $where
     * @param array $whereDocument
     * @param array|null $queryEmbeddings
     * @return array
     */
    public function query(
        string $collectionId,
        array $queryTexts = [],
        int $nResults = 10,
        array $where = [],
        array $whereDocument = [],
        ?array $queryEmbeddings = null
    ): array;

    /**
     * Get documents by IDs
     *
     * @param string $collectionId
     * @param array $ids
     * @return array
     */
    public function getDocuments(string $collectionId, array $ids): array;

    /**
     * Get collection count
     *
     * @param string $collectionId
     * @return int
     */
    public function count(string $collectionId): int;

    /**
     * Peek at collection (get sample documents)
     *
     * @param string $collectionId
     * @param int $limit
     * @return array
     */
    public function peek(string $collectionId, int $limit = 10): array;
}
