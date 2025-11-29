<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Navindbhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Model\ResourceModel\CustomerProfile as CustomerProfileResource;
use Navindbhudiya\ProductRecommendation\Service\BehaviorCollector\BrowsingHistoryCollector;
use Navindbhudiya\ProductRecommendation\Service\BehaviorCollector\PurchaseHistoryCollector;
use Navindbhudiya\ProductRecommendation\Service\BehaviorCollector\WishlistCollector;
use Psr\Log\LoggerInterface;

/**
 * Service for generating personalized AI recommendations
 */
class PersonalizedRecommendationService implements PersonalizedRecommendationInterface
{
    /**
     * Minimum products needed to generate a profile
     */
    private const MIN_PRODUCTS_FOR_PROFILE = 2;

    /**
     * Default weights for "Just For You" combination
     */
    private const DEFAULT_WEIGHTS = [
        self::TYPE_WISHLIST => 0.40,
        self::TYPE_PURCHASE => 0.35,
        self::TYPE_BROWSING => 0.25,
    ];

    /**
     * @var BrowsingHistoryCollector
     */
    private BrowsingHistoryCollector $browsingCollector;

    /**
     * @var PurchaseHistoryCollector
     */
    private PurchaseHistoryCollector $purchaseCollector;

    /**
     * @var WishlistCollector
     */
    private WishlistCollector $wishlistCollector;

    /**
     * @var EmbeddingProviderInterface
     */
    private EmbeddingProviderInterface $embeddingProvider;

    /**
     * @var ChromaClient
     */
    private ChromaClient $chromaClient;

    /**
     * @var ProductTextBuilder
     */
    private ProductTextBuilder $textBuilder;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var CustomerProfileResource
     */
    private CustomerProfileResource $profileResource;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param BrowsingHistoryCollector $browsingCollector
     * @param PurchaseHistoryCollector $purchaseCollector
     * @param WishlistCollector $wishlistCollector
     * @param EmbeddingProviderInterface $embeddingProvider
     * @param ChromaClient $chromaClient
     * @param ProductTextBuilder $textBuilder
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ProductRepositoryInterface $productRepository
     * @param CustomerProfileResource $profileResource
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        BrowsingHistoryCollector $browsingCollector,
        PurchaseHistoryCollector $purchaseCollector,
        WishlistCollector $wishlistCollector,
        EmbeddingProviderInterface $embeddingProvider,
        ChromaClient $chromaClient,
        ProductTextBuilder $textBuilder,
        ProductCollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        CustomerProfileResource $profileResource,
        ResourceConnection $resourceConnection,
        Config $config,
        StoreManagerInterface $storeManager,
        SerializerInterface $serializer,
        LoggerInterface $logger
    ) {
        $this->browsingCollector = $browsingCollector;
        $this->purchaseCollector = $purchaseCollector;
        $this->wishlistCollector = $wishlistCollector;
        $this->embeddingProvider = $embeddingProvider;
        $this->chromaClient = $chromaClient;
        $this->textBuilder = $textBuilder;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->profileResource = $profileResource;
        $this->resourceConnection = $resourceConnection;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function getBrowsingInspired(?int $customerId = null, int $limit = 8, ?int $storeId = null): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }

        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            
            // Get browsing history product IDs
            $browsingProductIds = $this->browsingCollector->getProductIds($customerId, 15, $storeId);
            
            if (count($browsingProductIds) < self::MIN_PRODUCTS_FOR_PROFILE) {
                $this->log("Not enough browsing history for personalization");
                return [];
            }

            // Check cached recommendations first
            if ($customerId) {
                $cached = $this->getCachedRecommendations($customerId, self::TYPE_BROWSING, $storeId);
                if ($cached !== null) {
                    return $this->loadProducts(array_slice($cached, 0, $limit), $storeId);
                }
            }

            // Get or create browsing profile embedding
            $profileEmbedding = $this->getOrCreateProfileEmbedding(
                $customerId,
                self::TYPE_BROWSING,
                $browsingProductIds,
                $storeId
            );

            if (empty($profileEmbedding)) {
                return [];
            }

            // Query ChromaDB for similar products
            $recommendedIds = $this->queryChromaDB(
                $profileEmbedding,
                $browsingProductIds, // Exclude already browsed
                $limit + 5,
                $storeId
            );

            // Cache results
            if ($customerId && !empty($recommendedIds)) {
                $this->cacheRecommendations($customerId, self::TYPE_BROWSING, $recommendedIds, $storeId);
            }

            return $this->loadProducts(array_slice($recommendedIds, 0, $limit), $storeId);

        } catch (\Exception $e) {
            $this->log("getBrowsingInspired error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getPurchaseInspired(int $customerId, int $limit = 8, ?int $storeId = null): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }

        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            
            // Get purchase history product IDs
            $purchaseProductIds = $this->purchaseCollector->getProductIds($customerId, 20, $storeId);
            
            if (count($purchaseProductIds) < self::MIN_PRODUCTS_FOR_PROFILE) {
                $this->log("Not enough purchase history for personalization");
                return [];
            }

            // Check cached recommendations
            $cached = $this->getCachedRecommendations($customerId, self::TYPE_PURCHASE, $storeId);
            if ($cached !== null) {
                return $this->loadProducts(array_slice($cached, 0, $limit), $storeId);
            }

            // Get or create purchase profile embedding
            $profileEmbedding = $this->getOrCreateProfileEmbedding(
                $customerId,
                self::TYPE_PURCHASE,
                $purchaseProductIds,
                $storeId
            );

            if (empty($profileEmbedding)) {
                return [];
            }

            // Query ChromaDB for complementary products
            $recommendedIds = $this->queryChromaDB(
                $profileEmbedding,
                $purchaseProductIds, // Exclude already purchased
                $limit + 5,
                $storeId
            );

            // Cache results
            if (!empty($recommendedIds)) {
                $this->cacheRecommendations($customerId, self::TYPE_PURCHASE, $recommendedIds, $storeId);
            }

            return $this->loadProducts(array_slice($recommendedIds, 0, $limit), $storeId);

        } catch (\Exception $e) {
            $this->log("getPurchaseInspired error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getWishlistInspired(int $customerId, int $limit = 8, ?int $storeId = null): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }

        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();
            
            // Get wishlist product IDs
            $wishlistProductIds = $this->wishlistCollector->getProductIds($customerId, 20, $storeId);
            
            if (count($wishlistProductIds) < 1) {
                $this->log("No wishlist items for personalization");
                return [];
            }

            // Check cached recommendations
            $cached = $this->getCachedRecommendations($customerId, self::TYPE_WISHLIST, $storeId);
            if ($cached !== null) {
                return $this->loadProducts(array_slice($cached, 0, $limit), $storeId);
            }

            // Get or create wishlist profile embedding
            $profileEmbedding = $this->getOrCreateProfileEmbedding(
                $customerId,
                self::TYPE_WISHLIST,
                $wishlistProductIds,
                $storeId
            );

            if (empty($profileEmbedding)) {
                return [];
            }

            // Query ChromaDB for similar products
            $recommendedIds = $this->queryChromaDB(
                $profileEmbedding,
                $wishlistProductIds, // Exclude wishlist items
                $limit + 5,
                $storeId
            );

            // Cache results
            if (!empty($recommendedIds)) {
                $this->cacheRecommendations($customerId, self::TYPE_WISHLIST, $recommendedIds, $storeId);
            }

            return $this->loadProducts(array_slice($recommendedIds, 0, $limit), $storeId);

        } catch (\Exception $e) {
            $this->log("getWishlistInspired error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getJustForYou(int $customerId, int $limit = 12, ?int $storeId = null): array
    {
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }

        try {
            $storeId = $storeId ?? (int) $this->storeManager->getStore()->getId();

            // Check cached recommendations
            $cached = $this->getCachedRecommendations($customerId, self::TYPE_JUST_FOR_YOU, $storeId);
            if ($cached !== null) {
                return $this->loadProducts(array_slice($cached, 0, $limit), $storeId);
            }

            // Collect all behavior data
            $wishlistIds = $this->wishlistCollector->getProductIds($customerId, 20, $storeId);
            $purchaseIds = $this->purchaseCollector->getProductIds($customerId, 20, $storeId);
            $browsingIds = $this->browsingCollector->getProductIds($customerId, 20, $storeId);

            // Calculate combined profile embedding with weights
            $combinedEmbedding = $this->calculateCombinedEmbedding(
                $wishlistIds,
                $purchaseIds,
                $browsingIds,
                $storeId
            );

            if (empty($combinedEmbedding)) {
                // Fall back to browsing if no combined data
                return $this->getBrowsingInspired($customerId, $limit, $storeId);
            }

            // Exclude all known products
            $excludeIds = array_unique(array_merge($wishlistIds, $purchaseIds, $browsingIds));

            // Query ChromaDB
            $recommendedIds = $this->queryChromaDB(
                $combinedEmbedding,
                $excludeIds,
                $limit + 10,
                $storeId
            );

            // Cache results
            if (!empty($recommendedIds)) {
                $this->cacheRecommendations($customerId, self::TYPE_JUST_FOR_YOU, $recommendedIds, $storeId);
                
                // Also save the combined profile
                $this->profileResource->saveProfile($customerId, self::TYPE_JUST_FOR_YOU, $storeId, [
                    'embedding_vector' => json_encode($combinedEmbedding),
                    'source_product_ids' => implode(',', array_slice($excludeIds, 0, 50)),
                    'product_count' => count($excludeIds)
                ]);
            }

            return $this->loadProducts(array_slice($recommendedIds, 0, $limit), $storeId);

        } catch (\Exception $e) {
            $this->log("getJustForYou error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function hasEnoughData(?int $customerId, string $type): bool
    {
        switch ($type) {
            case self::TYPE_BROWSING:
                return $this->browsingCollector->hasData($customerId);
            case self::TYPE_PURCHASE:
                return $customerId !== null && $this->purchaseCollector->hasData($customerId);
            case self::TYPE_WISHLIST:
                return $customerId !== null && $this->wishlistCollector->hasData($customerId);
            case self::TYPE_JUST_FOR_YOU:
                return $customerId !== null && (
                    $this->browsingCollector->hasData($customerId) ||
                    $this->purchaseCollector->hasData($customerId) ||
                    $this->wishlistCollector->hasData($customerId)
                );
            default:
                return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function refreshProfile(int $customerId, string $type): void
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            
            // Delete existing profile
            $this->profileResource->deleteByCustomer($customerId, $type);
            
            // Clear cached recommendations
            $this->clearCache($customerId, $type);
            
            // Regenerate by calling the appropriate method
            switch ($type) {
                case self::TYPE_BROWSING:
                    $this->getBrowsingInspired($customerId, 1, $storeId);
                    break;
                case self::TYPE_PURCHASE:
                    $this->getPurchaseInspired($customerId, 1, $storeId);
                    break;
                case self::TYPE_WISHLIST:
                    $this->getWishlistInspired($customerId, 1, $storeId);
                    break;
                case self::TYPE_JUST_FOR_YOU:
                    $this->getJustForYou($customerId, 1, $storeId);
                    break;
            }
        } catch (\Exception $e) {
            $this->log("refreshProfile error: " . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function clearCache(int $customerId, ?string $type = null): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('ai_personalized_recommendations');
            
            $where = ['customer_id = ?' => $customerId];
            if ($type !== null) {
                $where['recommendation_type = ?'] = $type;
            }
            
            $connection->delete($tableName, $where);
        } catch (\Exception $e) {
            $this->log("clearCache error: " . $e->getMessage());
        }
    }

    /**
     * Get or create profile embedding from product IDs
     *
     * @param int|null $customerId
     * @param string $type
     * @param array $productIds
     * @param int $storeId
     * @return array
     */
    private function getOrCreateProfileEmbedding(?int $customerId, string $type, array $productIds, int $storeId): array
    {
        // For guests, always generate fresh
        if ($customerId === null) {
            return $this->generateProfileEmbedding($productIds, $storeId);
        }

        // Check for existing profile
        $existingProfile = $this->profileResource->loadByCustomerAndType($customerId, $type, $storeId);
        
        if ($existingProfile && !empty($existingProfile['embedding_vector'])) {
            $existingIds = !empty($existingProfile['source_product_ids']) 
                ? array_map('intval', explode(',', $existingProfile['source_product_ids']))
                : [];
            
            // Check if product list has changed significantly (>30% different)
            $intersection = array_intersect($productIds, $existingIds);
            $similarity = count($intersection) / max(count($productIds), 1);
            
            if ($similarity > 0.7) {
                // Use cached embedding
                $vector = json_decode($existingProfile['embedding_vector'], true);
                if (!empty($vector)) {
                    return $vector;
                }
            }
        }

        // Generate new embedding
        $embedding = $this->generateProfileEmbedding($productIds, $storeId);
        
        if (!empty($embedding) && $customerId) {
            $this->profileResource->saveProfile($customerId, $type, $storeId, [
                'embedding_vector' => json_encode($embedding),
                'source_product_ids' => implode(',', array_slice($productIds, 0, 50)),
                'product_count' => count($productIds)
            ]);
        }

        return $embedding;
    }

    /**
     * Generate profile embedding by averaging product embeddings
     *
     * @param array $productIds
     * @param int $storeId
     * @return array
     */
    private function generateProfileEmbedding(array $productIds, int $storeId): array
    {
        if (empty($productIds)) {
            return [];
        }

        $embeddings = [];
        
        // Load products
        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter($productIds)
            ->addAttributeToSelect(['name', 'description', 'short_description', 'meta_keyword'])
            ->addStoreFilter($storeId);

        foreach ($collection as $product) {
            $text = $this->textBuilder->buildText($product, $storeId);
            if (!empty($text)) {
                $embedding = $this->embeddingProvider->generateEmbedding($text);
                if (!empty($embedding)) {
                    $embeddings[] = $embedding;
                }
            }
        }

        if (empty($embeddings)) {
            return [];
        }

        // Average all embeddings
        return $this->averageEmbeddings($embeddings);
    }

    /**
     * Calculate weighted average of embeddings
     *
     * @param array $embeddings
     * @param array $weights Optional weights for each embedding
     * @return array
     */
    private function averageEmbeddings(array $embeddings, array $weights = []): array
    {
        if (empty($embeddings)) {
            return [];
        }

        $dimensions = count($embeddings[0]);
        $result = array_fill(0, $dimensions, 0.0);
        $totalWeight = 0.0;

        foreach ($embeddings as $index => $embedding) {
            $weight = $weights[$index] ?? 1.0;
            $totalWeight += $weight;
            
            foreach ($embedding as $i => $value) {
                $result[$i] += $value * $weight;
            }
        }

        // Normalize
        if ($totalWeight > 0) {
            foreach ($result as $i => $value) {
                $result[$i] = $value / $totalWeight;
            }
        }

        return $result;
    }

    /**
     * Calculate combined embedding with weighted profiles
     *
     * @param array $wishlistIds
     * @param array $purchaseIds
     * @param array $browsingIds
     * @param int $storeId
     * @return array
     */
    private function calculateCombinedEmbedding(
        array $wishlistIds,
        array $purchaseIds,
        array $browsingIds,
        int $storeId
    ): array {
        $embeddings = [];
        $weights = [];

        // Wishlist embedding (highest weight)
        if (!empty($wishlistIds)) {
            $wishlistEmbedding = $this->generateProfileEmbedding($wishlistIds, $storeId);
            if (!empty($wishlistEmbedding)) {
                $embeddings[] = $wishlistEmbedding;
                $weights[] = self::DEFAULT_WEIGHTS[self::TYPE_WISHLIST];
            }
        }

        // Purchase embedding
        if (!empty($purchaseIds)) {
            $purchaseEmbedding = $this->generateProfileEmbedding($purchaseIds, $storeId);
            if (!empty($purchaseEmbedding)) {
                $embeddings[] = $purchaseEmbedding;
                $weights[] = self::DEFAULT_WEIGHTS[self::TYPE_PURCHASE];
            }
        }

        // Browsing embedding (lowest weight)
        if (!empty($browsingIds)) {
            $browsingEmbedding = $this->generateProfileEmbedding($browsingIds, $storeId);
            if (!empty($browsingEmbedding)) {
                $embeddings[] = $browsingEmbedding;
                $weights[] = self::DEFAULT_WEIGHTS[self::TYPE_BROWSING];
            }
        }

        return $this->averageEmbeddings($embeddings, $weights);
    }

    /**
     * Query ChromaDB for similar products
     *
     * @param array $embedding
     * @param array $excludeProductIds
     * @param int $limit
     * @param int $storeId
     * @return array Product IDs
     */
    private function queryChromaDB(array $embedding, array $excludeProductIds, int $limit, int $storeId): array
    {
        try {
            $collectionName = $this->config->getCollectionName();
            $collectionId = $this->chromaClient->getCollectionId($collectionName);

            // Build where filter
            $where = ['store_id' => $storeId];

            // Query ChromaDB
            $queryResult = $this->chromaClient->query(
                $collectionId,
                [],
                $limit + count($excludeProductIds),
                $where,
                [],
                [$embedding]
            );

            if (empty($queryResult['ids'][0] ?? [])) {
                return [];
            }

            $productIds = [];
            foreach ($queryResult['ids'][0] as $id) {
                if (preg_match('/^product_(\d+)(?:_\d+)?$/', $id, $matches)) {
                    $productId = (int) $matches[1];
                    if (!in_array($productId, $excludeProductIds, true)) {
                        $productIds[] = $productId;
                    }
                }
            }

            return array_unique($productIds);

        } catch (\Exception $e) {
            $this->log("queryChromaDB error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Load products by IDs
     *
     * @param array $productIds
     * @param int $storeId
     * @return ProductInterface[]
     */
    private function loadProducts(array $productIds, int $storeId): array
    {
        if (empty($productIds)) {
            return [];
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter($productIds)
            ->addAttributeToSelect([
                'name', 'sku', 'price', 'special_price',
                'small_image', 'thumbnail', 'url_key', 'short_description'
            ])
            ->addStoreFilter($storeId)
            ->addAttributeToFilter(
                'status',
                \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED
            )
            ->addAttributeToFilter(
                'visibility',
                ['in' => [
                    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
                    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
                ]]
            )
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents();

        // Maintain order
        if (!empty($productIds)) {
            $collection->getSelect()->order(
                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $productIds) . ')')
            );
        }

        return array_values($collection->getItems());
    }

    /**
     * Get cached recommendations
     *
     * @param int $customerId
     * @param string $type
     * @param int $storeId
     * @return array|null
     */
    private function getCachedRecommendations(int $customerId, string $type, int $storeId): ?array
    {
        if (!$this->config->isCacheEnabled()) {
            return null;
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('ai_personalized_recommendations');

            $select = $connection->select()
                ->from($tableName)
                ->where('customer_id = ?', $customerId)
                ->where('recommendation_type = ?', $type)
                ->where('store_id = ?', $storeId)
                ->where('expires_at > ?', (new \DateTime())->format('Y-m-d H:i:s'));

            $result = $connection->fetchRow($select);

            if ($result && !empty($result['product_ids'])) {
                return array_map('intval', explode(',', $result['product_ids']));
            }

        } catch (\Exception $e) {
            $this->log("getCachedRecommendations error: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Cache recommendations
     *
     * @param int $customerId
     * @param string $type
     * @param array $productIds
     * @param int $storeId
     * @return void
     */
    private function cacheRecommendations(int $customerId, string $type, array $productIds, int $storeId): void
    {
        if (!$this->config->isCacheEnabled()) {
            return;
        }

        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('ai_personalized_recommendations');

            $expiresAt = (new \DateTime())
                ->modify('+' . $this->config->getCacheLifetime() . ' seconds')
                ->format('Y-m-d H:i:s');

            $data = [
                'customer_id' => $customerId,
                'recommendation_type' => $type,
                'product_ids' => implode(',', $productIds),
                'store_id' => $storeId,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt
            ];

            $connection->insertOnDuplicate($tableName, $data, [
                'product_ids',
                'created_at',
                'expires_at'
            ]);

        } catch (\Exception $e) {
            $this->log("cacheRecommendations error: " . $e->getMessage());
        }
    }

    /**
     * Log message if debug mode enabled
     *
     * @param string $message
     * @return void
     */
    private function log(string $message): void
    {
        if ($this->config->isDebugMode()) {
            $this->logger->debug('[ProductRecommendation] Personalized: ' . $message);
        }
    }
}
