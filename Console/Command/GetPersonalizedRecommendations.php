<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Console\Command;

use Magento\Framework\Console\Cli;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

/**
 * CLI command to get personalized recommendations for a customer
 */
class GetPersonalizedRecommendations extends Command
{
    private const ARGUMENT_CUSTOMER_ID = 'customer_id';
    private const OPTION_TYPE = 'type';
    private const OPTION_LIMIT = 'limit';
    private const OPTION_STORE = 'store';

    /**
     * @var PersonalizedRecommendationInterface
     */
    private PersonalizedRecommendationInterface $recommendationService;

    /**
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param string|null $name
     */
    public function __construct(
        PersonalizedRecommendationInterface $recommendationService,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->recommendationService = $recommendationService;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:personalized')
            ->setDescription('Get personalized AI recommendations for a customer')
            ->addArgument(
                self::ARGUMENT_CUSTOMER_ID,
                InputArgument::REQUIRED,
                'Customer ID'
            )
            ->addOption(
                self::OPTION_TYPE,
                't',
                InputOption::VALUE_OPTIONAL,
                'Recommendation type: browsing, purchase, wishlist, just_for_you (default: just_for_you)',
                'just_for_you'
            )
            ->addOption(
                self::OPTION_LIMIT,
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of products to return',
                8
            )
            ->addOption(
                self::OPTION_STORE,
                's',
                InputOption::VALUE_OPTIONAL,
                'Store ID',
                1
            );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerId = (int) $input->getArgument(self::ARGUMENT_CUSTOMER_ID);
        $type = $input->getOption(self::OPTION_TYPE);
        $limit = (int) $input->getOption(self::OPTION_LIMIT);
        $storeId = (int) $input->getOption(self::OPTION_STORE);

        $output->writeln('<info>Getting personalized recommendations...</info>');
        $output->writeln(sprintf('Customer ID: %d', $customerId));
        $output->writeln(sprintf('Type: %s', $type));
        $output->writeln(sprintf('Limit: %d', $limit));
        $output->writeln(sprintf('Store ID: %d', $storeId));
        $output->writeln('');

        try {
            // Check if customer has enough data
            if (!$this->recommendationService->hasEnoughData($customerId, $type)) {
                $output->writeln('<comment>Customer does not have enough data for this recommendation type.</comment>');
                return Cli::RETURN_SUCCESS;
            }

            // Get recommendations based on type
            $products = $this->getRecommendations($customerId, $type, $limit, $storeId);

            if (empty($products)) {
                $output->writeln('<comment>No recommendations found.</comment>');
                return Cli::RETURN_SUCCESS;
            }

            // Display results
            $output->writeln(sprintf('<info>Found %d recommendations:</info>', count($products)));
            $output->writeln('');

            $table = new Table($output);
            $table->setHeaders(['#', 'ID', 'SKU', 'Name', 'Price']);

            $index = 1;
            foreach ($products as $product) {
                $table->addRow([
                    $index++,
                    $product->getId(),
                    $product->getSku(),
                    mb_substr($product->getName(), 0, 50),
                    number_format((float) $product->getFinalPrice(), 2)
                ]);
            }

            $table->render();

            return Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Get recommendations based on type
     *
     * @param int $customerId
     * @param string $type
     * @param int $limit
     * @param int $storeId
     * @return array
     */
    private function getRecommendations(int $customerId, string $type, int $limit, int $storeId): array
    {
        switch ($type) {
            case PersonalizedRecommendationInterface::TYPE_BROWSING:
                return $this->recommendationService->getBrowsingInspired($customerId, $limit, $storeId);
            case PersonalizedRecommendationInterface::TYPE_PURCHASE:
                return $this->recommendationService->getPurchaseInspired($customerId, $limit, $storeId);
            case PersonalizedRecommendationInterface::TYPE_WISHLIST:
                return $this->recommendationService->getWishlistInspired($customerId, $limit, $storeId);
            case PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU:
            default:
                return $this->recommendationService->getJustForYou($customerId, $limit, $storeId);
        }
    }
}
