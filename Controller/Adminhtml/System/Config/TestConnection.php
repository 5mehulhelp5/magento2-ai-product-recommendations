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

namespace Navindbhudiya\ProductRecommendation\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Navindbhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Service\ChromaClient;

/**
 * Test connection controller
 */
class TestConnection extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'Navindbhudiya_ProductRecommendation::config';

    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var ChromaClient
     */
    private ChromaClient $chromaClient;

    /**
     * @var EmbeddingProviderInterface
     */
    private EmbeddingProviderInterface $embeddingProvider;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ChromaClient $chromaClient
     * @param EmbeddingProviderInterface $embeddingProvider
     * @param Config $config
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ChromaClient $chromaClient,
        EmbeddingProviderInterface $embeddingProvider,
        Config $config
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->chromaClient = $chromaClient;
        $this->embeddingProvider = $embeddingProvider;
        $this->config = $config;
    }

    /**
     * Test connection action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Test ChromaDB connection
            if (!$this->chromaClient->testConnection()) {
                return $result->setData([
                    'success' => false,
                    'message' => 'ChromaDB connection failed. Check host and port settings.',
                ]);
            }

            // Get collection info
            $collectionName = $this->config->getCollectionName();
            $collection = $this->chromaClient->getOrCreateCollection($collectionName);
            $count = $this->chromaClient->count($collection['id']);

            // Check embedding provider
            $embeddingProvider = $this->config->getEmbeddingProvider();
            $embeddingStatus = $this->embeddingProvider->isAvailable()
                ? 'Available'
                : 'Not available';

            return $result->setData([
                'success' => true,
                'message' => 'Connection successful!',
                'details' => [
                    'ChromaDB URL' => $this->config->getChromaDbUrl(),
                    'Collection' => $collectionName,
                    'Documents indexed' => $count,
                    'Embedding Provider' => ucfirst($embeddingProvider),
                    'Embedding Status' => $embeddingStatus,
                ],
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }
}
