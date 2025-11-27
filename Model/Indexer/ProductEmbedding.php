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

namespace Navindbhudiya\ProductRecommendation\Model\Indexer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Service\ChromaClient;
use Navindbhudiya\ProductRecommendation\Service\ProductTextBuilder;
use Psr\Log\LoggerInterface;

/**
 * Product embedding indexer
 */
class ProductEmbedding implements IndexerActionInterface, MviewActionInterface
{
    private const BATCH_SIZE = 50;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ChromaClient
     */
    private ChromaClient $chromaClient;

    /**
     * @var EmbeddingProviderInterface
     */
    private EmbeddingProviderInterface $embeddingProvider;

    /**
     * @var ProductTextBuilder
     */
    private ProductTextBuilder $textBuilder;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ChromaClient $chromaClient
     * @param EmbeddingProviderInterface $embeddingProvider
     * @param ProductTextBuilder $textBuilder
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        StoreManagerInterface $storeManager,
        ChromaClient $chromaClient,
        EmbeddingProviderInterface $embeddingProvider,
        ProductTextBuilder $textBuilder,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->storeManager = $storeManager;
        $this->chromaClient = $chromaClient;
        $this->embeddingProvider = $embeddingProvider;
        $this->textBuilder = $textBuilder;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute full reindex
     *
     * @return void
     */
    public function executeFull(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->log('Starting full reindex');

        try {
            // Ensure collection exists
            $collectionName = $this->config->getCollectionName();
            $collection = $this->chromaClient->getOrCreateCollection($collectionName);
            $collectionId = $collection['id'];

            // Index for each store
            foreach ($this->storeManager->getStores() as $store) {
                if (!$store->getIsActive()) {
                    continue;
                }

                $storeId = (int) $store->getId();
                $this->log("Indexing store: {$store->getName()} (ID: $storeId)");

                $this->indexStore($collectionId, $storeId);
            }

            $this->log('Full reindex completed');
        } catch (\Exception $e) {
            $this->logger->error('Full reindex failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute partial reindex by IDs
     *
     * @param int[] $ids
     * @return void
     */
    public function executeList(array $ids): void
    {
        if (!$this->config->isEnabled() || empty($ids)) {
            return;
        }

        $this->log('Reindexing products: ' . implode(', ', $ids));

        try {
            $collectionName = $this->config->getCollectionName();
            $collection = $this->chromaClient->getOrCreateCollection($collectionName);
            $collectionId = $collection['id'];

            foreach ($this->storeManager->getStores() as $store) {
                if (!$store->getIsActive()) {
                    continue;
                }

                $storeId = (int) $store->getId();
                $this->indexProducts($collectionId, $ids, $storeId);
            }
        } catch (\Exception $e) {
            $this->logger->error('Partial reindex failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute reindex for single row
     *
     * @param int $id
     * @return void
     */
    public function executeRow($id): void
    {
        $this->executeList([$id]);
    }

    /**
     * Execute materialized view update
     *
     * @param int[] $ids
     * @return void
     */
    public function execute($ids): void
    {
        $this->executeList($ids);
    }

    /**
     * Index all products for a store
     *
     * @param string $collectionId
     * @param int $storeId
     * @return void
     */
    private function indexStore(string $collectionId, int $storeId): void
    {
        echo "Starting indexStore for store $storeId...\n";

        // Step 1: Get all product IDs first (no duplicates)
        echo "Fetching all visible product IDs...\n";
        $productIds = $this->getAllVisibleProductIds($storeId);
        $totalProducts = count($productIds);
        echo "Found $totalProducts visible products to index\n";

        if (empty($productIds)) {
            echo "No products to index\n";
            return;
        }

        // Step 2: Process in batches
        $batches = array_chunk($productIds, self::BATCH_SIZE);
        $totalIndexed = 0;

        foreach ($batches as $batchNum => $batchIds) {
            $batchNumber = $batchNum + 1;
            echo "Indexing batch $batchNumber/" . count($batches) . " (" . count($batchIds) . " products)...\n";

            $this->indexProducts($collectionId, $batchIds, $storeId);
            $totalIndexed += count($batchIds);

            echo "Indexed batch $batchNumber (total: $totalIndexed/$totalProducts products)\n";
            $this->log("Indexed batch $batchNumber (total: $totalIndexed products)");
        }

        echo "Store $storeId: Total indexed $totalIndexed products\n";
        $this->log("Store $storeId: Total indexed $totalIndexed products");
    }

    /**
     * Get all visible product IDs for a store (no duplicates)
     *
     * @param int $storeId
     * @return array
     */
    private function getAllVisibleProductIds(int $storeId): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter($storeId)
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH
                ]
            ]);

        // Get only IDs - much faster and avoids duplicates
        $collection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS);
        $collection->getSelect()->columns('e.entity_id');
        $collection->getSelect()->group('e.entity_id');

        $ids = $collection->getConnection()->fetchCol($collection->getSelect());
        return array_map('intval', $ids);
    }

    /**
     * Index specific products
     *
     * @param string $collectionId
     * @param array $productIds
     * @param int $storeId
     * @return void
     */
    private function indexProducts(string $collectionId, array $productIds, int $storeId): void
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter($productIds)
            ->addStoreFilter($storeId)
            ->addAttributeToSelect($this->config->getProductAttributes())
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]);

        $products = $collection->getItems();

        if (!empty($products)) {
            $this->indexProductBatch($collectionId, $products, $storeId);
        }

        // Delete products that are no longer valid
        $validIds = array_map(fn($p) => (int) $p->getId(), $products);
        $deleteIds = array_diff($productIds, $validIds);

        if (!empty($deleteIds)) {
            $documentIds = array_map(
                fn($id) => $this->buildDocumentId((int) $id, $storeId),
                $deleteIds
            );
            $this->chromaClient->deleteDocuments($collectionId, $documentIds);
            $this->log('Deleted ' . count($deleteIds) . ' invalid products from index');
        }
    }

    /**
     * Index a batch of products
     *
     * @param string $collectionId
     * @param Product[] $products
     * @param int $storeId
     * @return void
     */
    private function indexProductBatch(string $collectionId, array $products, int $storeId): void
    {
        $ids = [];
        $documents = [];
        $metadatas = [];
        $texts = [];

        foreach ($products as $product) {
            $productId = (int) $product->getId();
            $documentId = $this->buildDocumentId($productId, $storeId);
            $text = $this->textBuilder->buildText($product, $storeId);

            if (empty($text)) {
                continue;
            }

            $ids[] = $documentId;
            $documents[] = $text;
            $metadatas[] = [
                'product_id' => $productId,
                'sku' => $product->getSku(),
                'store_id' => $storeId,
                'name' => $product->getName(),
                'price' => (float) $product->getPrice(),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $texts[] = $text;
        }

        if (empty($ids)) {
            return;
        }

        // ALWAYS generate embeddings - ChromaDB REST API doesn't support server-side embedding
        $embeddings = null;
        try {
            echo "  Generating embeddings for " . count($texts) . " products...\n";
            $embeddings = $this->embeddingProvider->generateEmbeddings($texts);
            
            if (empty($embeddings)) {
                $this->logger->error('Embedding generation returned empty. Check if embedding service is running!');
                echo "  ERROR: Embedding generation returned empty!\n";
                return;
            }
            
            echo "  Generated " . count($embeddings) . " embeddings successfully\n";
        } catch (\Exception $e) {
            $this->logger->error('Embedding generation failed: ' . $e->getMessage());
            echo "  ERROR: Embedding generation failed: " . $e->getMessage() . "\n";
            return; // Cannot proceed without embeddings
        }

        // Upsert to ChromaDB with embeddings
        $this->chromaClient->upsertDocuments(
            $collectionId,
            $ids,
            $documents,
            $metadatas,
            $embeddings
        );
    }

    /**
     * Get products batch
     *
     * @param int $storeId
     * @param int $page
     * @param int $pageSize
     * @return Product[]
     */
    private function getProductsBatch(int $storeId, int $page, int $pageSize): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter($storeId)
            ->addAttributeToSelect($this->config->getProductAttributes())
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH
                ]
            ])
            ->setPageSize($pageSize)
            ->setCurPage($page);

        // Ensure distinct results to avoid pagination issues
        $collection->getSelect()->group('e.entity_id');

        return $collection->getItems();
    }

    /**
     * Build document ID for ChromaDB
     *
     * @param int $productId
     * @param int $storeId
     * @return string
     */
    private function buildDocumentId(int $productId, int $storeId): string
    {
        return "product_{$productId}_{$storeId}";
    }

    /**
     * Log message if debug mode is enabled
     *
     * @param string $message
     * @return void
     */
    private function log(string $message): void
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('[ProductRecommendation Indexer] ' . $message);
        }
    }
}
