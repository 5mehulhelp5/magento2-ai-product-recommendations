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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Console\Cli;
use Navindbhudiya\ProductRecommendation\Api\RecommendationServiceInterface;
use Navindbhudiya\ProductRecommendation\Helper\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * CLI command to get similar products
 */
class GetSimilarProducts extends Command
{
    private const ARG_PRODUCT_ID = 'product-id';
    private const OPTION_TYPE = 'type';
    private const OPTION_LIMIT = 'limit';
    private const OPTION_QUERY = 'query';

    /**
     * @var RecommendationServiceInterface
     */
    private RecommendationServiceInterface $recommendationService;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @param RecommendationServiceInterface $recommendationService
     * @param ProductRepositoryInterface $productRepository
     * @param Config $config
     * @param string|null $name
     */
    public function __construct(
        RecommendationServiceInterface $recommendationService,
        ProductRepositoryInterface $productRepository,
        Config $config,
        ?string $name = null
    ) {
        $this->recommendationService = $recommendationService;
        $this->productRepository = $productRepository;
        $this->config = $config;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:similar')
            ->setDescription('Get similar products for a product ID or search query')
            ->addArgument(
                self::ARG_PRODUCT_ID,
                InputArgument::OPTIONAL,
                'Product ID to find similar products for'
            )
            ->addOption(
                self::OPTION_TYPE,
                't',
                InputOption::VALUE_OPTIONAL,
                'Recommendation type: related, crosssell, upsell',
                'related'
            )
            ->addOption(
                self::OPTION_LIMIT,
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of results',
                10
            )
            ->addOption(
                self::OPTION_QUERY,
                'q',
                InputOption::VALUE_OPTIONAL,
                'Search by text query instead of product ID'
            );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->config->isEnabled()) {
            $output->writeln('<e>AI Product Recommendation module is disabled.</e>');
            return Cli::RETURN_FAILURE;
        }

        $productId = $input->getArgument(self::ARG_PRODUCT_ID);
        $query = $input->getOption(self::OPTION_QUERY);
        $type = $input->getOption(self::OPTION_TYPE);
        $limit = (int) $input->getOption(self::OPTION_LIMIT);

        if (!$productId && !$query) {
            $output->writeln('<e>Please provide either a product ID or a search query (--query).</e>');
            return Cli::RETURN_FAILURE;
        }

        try {
            if ($query) {
                $output->writeln(sprintf('<info>Searching for products similar to: "%s"</info>', $query));
                $output->writeln('');

                $products = $this->recommendationService->getSimilarProductsByQuery($query, $limit);
                $this->displayProducts($output, $products);
            } else {
                $product = $this->productRepository->getById((int) $productId);
                $output->writeln(sprintf(
                    '<info>Finding %s products for: %s (ID: %s)</info>',
                    $type,
                    $product->getName(),
                    $product->getId()
                ));
                $output->writeln('');

                $results = $this->recommendationService->getRecommendationsWithScores(
                    $product,
                    $type,
                    $limit
                );

                $this->displayResults($output, $results);
            }

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<e>Error: %s</e>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Display recommendation results with scores
     *
     * @param OutputInterface $output
     * @param array $results
     * @return void
     */
    private function displayResults(OutputInterface $output, array $results): void
    {
        if (empty($results)) {
            $output->writeln('<comment>No similar products found.</comment>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['#', 'ID', 'SKU', 'Name', 'Price', 'Score', 'Distance']);

        $index = 1;
        foreach ($results as $result) {
            $product = $result->getProduct();
            $table->addRow([
                $index++,
                $product->getId(),
                $product->getSku(),
                mb_substr($product->getName(), 0, 40),
                number_format((float) $product->getPrice(), 2),
                number_format($result->getScore(), 4),
                number_format($result->getDistance(), 4),
            ]);
        }

        $table->render();
        $output->writeln('');
        $output->writeln(sprintf('<info>Found %d similar products.</info>', count($results)));
    }

    /**
     * Display products (without scores)
     *
     * @param OutputInterface $output
     * @param array $products
     * @return void
     */
    private function displayProducts(OutputInterface $output, array $products): void
    {
        if (empty($products)) {
            $output->writeln('<comment>No products found.</comment>');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(['#', 'ID', 'SKU', 'Name', 'Price']);

        $index = 1;
        foreach ($products as $product) {
            $table->addRow([
                $index++,
                $product->getId(),
                $product->getSku(),
                mb_substr($product->getName(), 0, 50),
                number_format((float) $product->getPrice(), 2),
            ]);
        }

        $table->render();
        $output->writeln('');
        $output->writeln(sprintf('<info>Found %d products.</info>', count($products)));
    }
}
