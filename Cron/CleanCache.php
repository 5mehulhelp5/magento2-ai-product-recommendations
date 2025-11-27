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

use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Cron job to clean expired recommendation cache
 */
class CleanCache
{
    /**
     * @var RecommendationServiceInterface
     */
    private RecommendationServiceInterface $recommendationService;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param RecommendationServiceInterface $recommendationService
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        RecommendationServiceInterface $recommendationService,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->recommendationService = $recommendationService;
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
        if (!$this->config->isEnabled() || !$this->config->isCacheEnabled()) {
            return;
        }

        try {
            $this->logger->info('[ProductRecommendation] Starting cache cleanup');
            $this->recommendationService->clearAllCache();
            $this->logger->info('[ProductRecommendation] Cache cleanup completed');
        } catch (\Exception $e) {
            $this->logger->error('[ProductRecommendation] Cache cleanup failed: ' . $e->getMessage());
        }
    }
}
