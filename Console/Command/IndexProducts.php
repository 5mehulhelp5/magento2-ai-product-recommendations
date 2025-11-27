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
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Model\Indexer\ProductEmbedding;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * CLI command to index products in ChromaDB
 */
class IndexProducts extends Command
{
    private const OPTION_PRODUCT_IDS = 'product-ids';
    private const OPTION_STORE_ID = 'store-id';

    /**
     * @var ProductEmbedding
     */
    private ProductEmbedding $indexer;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param ProductEmbedding $indexer
     * @param Config $config
     * @param string|null $name
     */
    public function __construct(
        ProductEmbedding $indexer,
        Config $config,
        ?string $name = null
    ) {
        $this->indexer = $indexer;
        $this->config = $config;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:index')
            ->setDescription('Index products in ChromaDB for AI recommendations')
            ->addOption(
                self::OPTION_PRODUCT_IDS,
                'p',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated product IDs to index (optional, indexes all if not specified)'
            )
            ->addOption(
                self::OPTION_STORE_ID,
                's',
                InputOption::VALUE_OPTIONAL,
                'Store ID to index (optional, indexes all stores if not specified)'
            );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('<error>AI Product Recommendation module is disabled.</error>');
            return Cli::RETURN_FAILURE;
        }

        $productIds = $input->getOption(self::OPTION_PRODUCT_IDS);

        try {
            $output->writeln('<info>Starting product indexing...</info>');
            $output->writeln('');

            $startTime = microtime(true);

            if ($productIds) {
                $ids = array_map('intval', explode(',', $productIds));
                $output->writeln(sprintf('<comment>Indexing %d specific products...</comment>', count($ids)));
                $this->indexer->executeList($ids);
            } else {
                $output->writeln('<comment>Running full reindex...</comment>');
                $this->indexer->executeFull();
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $output->writeln('');
            $output->writeln(sprintf('<info>Indexing completed in %s seconds.</info>', $duration));

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }
}
