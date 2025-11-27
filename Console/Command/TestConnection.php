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

namespace Navindbhudiya\ProductRecommendation\Console\Command;

use Magento\Framework\Console\Cli;
use Navindbhudiya\ProductRecommendation\Api\EmbeddingProviderInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Service\ChromaClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to test ChromaDB connection
 */
class TestConnection extends Command
{
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
     * @param ChromaClient $chromaClient
     * @param EmbeddingProviderInterface $embeddingProvider
     * @param Config $config
     * @param string|null $name
     */
    public function __construct(
        ChromaClient $chromaClient,
        EmbeddingProviderInterface $embeddingProvider,
        Config $config,
        ?string $name = null
    ) {
        $this->chromaClient = $chromaClient;
        $this->embeddingProvider = $embeddingProvider;
        $this->config = $config;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:test')
            ->setDescription('Test ChromaDB and embedding provider connection');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Testing AI Product Recommendation connections...</info>');
        $output->writeln('');

        $allSuccess = true;

        // Test ChromaDB connection
        $output->writeln('<comment>1. Testing ChromaDB connection...</comment>');
        $output->writeln(sprintf('   URL: %s', $this->config->getChromaDbUrl()));

        try {
            if ($this->chromaClient->testConnection()) {
                $heartbeat = $this->chromaClient->heartbeat();
                $output->writeln('   <info>✓ ChromaDB connection successful</info>');
                if (isset($heartbeat['nanosecond_heartbeat'])) {
                    $output->writeln(sprintf('   Server heartbeat: %s', $heartbeat['nanosecond_heartbeat']));
                }
                
                // Try to get version
                try {
                    $version = $this->chromaClient->getVersion();
                    if (isset($version['version'])) {
                        $output->writeln(sprintf('   ChromaDB Version: %s', $version['version']));
                    }
                } catch (\Exception $e) {
                    // Version endpoint might not exist in all versions
                }
            } else {
                $output->writeln('   <error>✗ ChromaDB connection failed</error>');
                $output->writeln('   <comment>Tip: Make sure ChromaDB container is running:</comment>');
                $output->writeln('   <comment>  docker ps | grep chromadb</comment>');
                $allSuccess = false;
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('   <error>✗ Error: %s</error>', $e->getMessage()));
            $output->writeln('');
            $output->writeln('   <comment>Troubleshooting tips:</comment>');
            $output->writeln('   - Check if ChromaDB container is running: docker ps | grep chromadb');
            $output->writeln('   - Test manually: curl http://chromadb:8000/api/v1/heartbeat');
            $output->writeln('   - If using latest ChromaDB, try version 0.4.24: image: chromadb/chroma:0.4.24');
            $allSuccess = false;
        }

        $output->writeln('');

        // Test collection access
        $output->writeln('<comment>2. Testing collection access...</comment>');
        $collectionName = $this->config->getCollectionName();
        $output->writeln(sprintf('   Collection: %s', $collectionName));

        try {
            $collection = $this->chromaClient->getOrCreateCollection($collectionName);
            $output->writeln('   <info>✓ Collection accessible</info>');
            $output->writeln(sprintf('   Collection ID: %s', $collection['id'] ?? 'N/A'));

            $count = $this->chromaClient->count($collection['id']);
            $output->writeln(sprintf('   Documents indexed: %d', $count));
            
            if ($count === 0) {
                $output->writeln('   <comment>Note: No products indexed yet. Run: bin/magento recommendation:index</comment>');
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('   <error>✗ Error: %s</error>', $e->getMessage()));
            
            // Check for common API issues
            if (strpos($e->getMessage(), '405') !== false) {
                $output->writeln('');
                $output->writeln('   <comment>405 Method Not Allowed - ChromaDB API version mismatch!</comment>');
                $output->writeln('   <comment>Solution: Use ChromaDB v0.4.24:</comment>');
                $output->writeln('   <comment>  image: chromadb/chroma:0.4.24</comment>');
            }
            $allSuccess = false;
        }

        $output->writeln('');

        // Test embedding provider
        $output->writeln('<comment>3. Testing embedding provider...</comment>');
        $provider = $this->config->getEmbeddingProvider();
        $output->writeln(sprintf('   Provider: %s', $provider));

        try {
            if ($this->embeddingProvider->isAvailable()) {
                $output->writeln('   <info>✓ Embedding provider available</info>');
                $output->writeln(sprintf('   Dimension: %d', $this->embeddingProvider->getDimension()));

                // ALWAYS test embedding generation - it's required for all providers!
                $output->writeln('   Testing embedding generation...');
                $testEmbedding = $this->embeddingProvider->generateEmbedding('Test product description for AI recommendations');
                
                if (!empty($testEmbedding)) {
                    $output->writeln(sprintf('   <info>✓ Generated embedding with %d dimensions</info>', count($testEmbedding)));
                } else {
                    $output->writeln('   <error>✗ Embedding generation returned empty!</error>');
                    $output->writeln('   <comment>Make sure the embedding-service container is running:</comment>');
                    $output->writeln('   <comment>  docker ps | grep embedding</comment>');
                    $output->writeln('   <comment>  docker logs $(docker ps -qf name=embedding)</comment>');
                    $allSuccess = false;
                }
            } else {
                $output->writeln('   <error>✗ Embedding provider not available</error>');
                
                if ($provider === 'chromadb') {
                    $output->writeln('');
                    $output->writeln('   <comment>For "chromadb" provider, you need the embedding-service container.</comment>');
                    $output->writeln('   <comment>Check your .warden/warden-env.yml includes embedding-service.</comment>');
                    $output->writeln('   <comment>Test: curl http://embedding-service:8001/health</comment>');
                }
                $allSuccess = false;
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('   <error>✗ Error: %s</error>', $e->getMessage()));
            $allSuccess = false;
        }

        $output->writeln('');

        // Summary and next steps
        if ($allSuccess) {
            $output->writeln('<info>═══════════════════════════════════════════════════════════</info>');
            $output->writeln('<info>All tests passed! The module is ready to use.</info>');
            $output->writeln('<info>═══════════════════════════════════════════════════════════</info>');
            $output->writeln('');
            $output->writeln('Next steps:');
            $output->writeln('  1. Index your products: <comment>bin/magento recommendation:index</comment>');
            $output->writeln('  2. Test recommendations: <comment>bin/magento recommendation:similar 1</comment>');
            $output->writeln('  3. Visit a product page to see AI recommendations');
            return Cli::RETURN_SUCCESS;
        } else {
            $output->writeln('<error>═══════════════════════════════════════════════════════════</error>');
            $output->writeln('<error>Some tests failed. Please fix the issues above.</error>');
            $output->writeln('<error>═══════════════════════════════════════════════════════════</error>');
            $output->writeln('');
            $output->writeln('Common solutions:');
            $output->writeln('  1. Use ChromaDB 0.4.24: <comment>image: chromadb/chroma:0.4.24</comment>');
            $output->writeln('  2. Ensure embedding-service is running');
            $output->writeln('  3. Rebuild containers: <comment>warden env down && warden env up -d</comment>');
            $output->writeln('  4. Check logs: <comment>docker logs $(docker ps -qf name=chromadb)</comment>');
            return Cli::RETURN_FAILURE;
        }
    }
}
