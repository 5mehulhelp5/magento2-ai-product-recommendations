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

namespace Navindbhudiya\ProductRecommendation\Cron;

use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Model\Indexer\ProductEmbedding;
use Psr\Log\LoggerInterface;

/**
 * Cron job to sync product embeddings
 */
class SyncEmbeddings
{
    /**
     * @var ProductEmbedding
     */
    private ProductEmbedding $indexer;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param ProductEmbedding $indexer
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProductEmbedding $indexer,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->indexer = $indexer;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $this->logger->info('[ProductRecommendation] Starting scheduled embedding sync');
            $this->indexer->executeFull();
            $this->logger->info('[ProductRecommendation] Scheduled embedding sync completed');
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Scheduled sync failed: ' . $e->getMessage());
        }
    }
}
