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
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Navindbhudiya\ProductRecommendation\Service\ChromaClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * CLI command to clear ChromaDB collection
 */
class ClearCollection extends Command
{
    private const OPTION_FORCE = 'force';
    private const OPTION_CACHE_ONLY = 'cache-only';

    /**
     * @var ChromaClient
     */
    private ChromaClient $chromaClient;

    /**
     * @var RecommendationServiceInterface
     */
    private RecommendationServiceInterface $recommendationService;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param ChromaClient $chromaClient
     * @param RecommendationServiceInterface $recommendationService
     * @param Config $config
     * @param string|null $name
     */
    public function __construct(
        ChromaClient $chromaClient,
        RecommendationServiceInterface $recommendationService,
        Config $config,
        ?string $name = null
    ) {
        $this->chromaClient = $chromaClient;
        $this->recommendationService = $recommendationService;
        $this->config = $config;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:clear')
            ->setDescription('Clear ChromaDB collection and/or recommendation cache')
            ->addOption(
                self::OPTION_FORCE,
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt'
            )
            ->addOption(
                self::OPTION_CACHE_ONLY,
                'c',
                InputOption::VALUE_NONE,
                'Only clear the recommendation cache, not the ChromaDB collection'
            );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption(self::OPTION_FORCE);
        $cacheOnly = $input->getOption(self::OPTION_CACHE_ONLY);

        if ($cacheOnly) {
            $output->writeln('<info>Clearing recommendation cache...</info>');
            $this->recommendationService->clearAllCache();
            $output->writeln('<info>Cache cleared successfully.</info>');
            return Cli::RETURN_SUCCESS;
        }

        $collectionName = $this->config->getCollectionName();

        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf(
                    '<question>Are you sure you want to delete the ChromaDB collection "%s"? This action cannot be undone. [y/N]</question> ',
                    $collectionName
                ),
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return Cli::RETURN_SUCCESS;
            }
        }

        try {
            $output->writeln(sprintf('<info>Deleting collection: %s</info>', $collectionName));

            if ($this->chromaClient->deleteCollection($collectionName)) {
                $output->writeln('<info>Collection deleted successfully.</info>');
            } else {
                $output->writeln('<comment>Collection may not exist or could not be deleted.</comment>');
            }

            $output->writeln('<info>Clearing recommendation cache...</info>');
            $this->recommendationService->clearAllCache();
            $output->writeln('<info>Cache cleared successfully.</info>');

            $output->writeln('');
            $output->writeln('<comment>Run "bin/magento recommendation:index" to rebuild the index.</comment>');

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<e>Error: %s</e>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }
}
