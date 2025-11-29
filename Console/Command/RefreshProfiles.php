<?php
/**
 * Navindbhudiya ProductRecommendation
 */

declare(strict_types=1);

namespace Navindbhudiya\ProductRecommendation\Console\Command;

use Magento\Framework\Console\Cli;
use Navindbhudiya\ProductRecommendation\Api\PersonalizedRecommendationInterface;
use Navindbhudiya\ProductRecommendation\Model\ResourceModel\CustomerProfile as CustomerProfileResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * CLI command to refresh customer profiles
 */
class RefreshProfiles extends Command
{
    private const ARGUMENT_CUSTOMER_ID = 'customer_id';
    private const OPTION_TYPE = 'type';
    private const OPTION_ALL = 'all';
    private const OPTION_STALE = 'stale';

    /**
     * @var PersonalizedRecommendationInterface
     */
    private PersonalizedRecommendationInterface $recommendationService;

    /**
     * @var CustomerProfileResource
     */
    private CustomerProfileResource $profileResource;

    /**
     * @param PersonalizedRecommendationInterface $recommendationService
     * @param CustomerProfileResource $profileResource
     * @param string|null $name
     */
    public function __construct(
        PersonalizedRecommendationInterface $recommendationService,
        CustomerProfileResource $profileResource,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->recommendationService = $recommendationService;
        $this->profileResource = $profileResource;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('recommendation:refresh-profiles')
            ->setDescription('Refresh customer AI profiles for personalized recommendations')
            ->addArgument(
                self::ARGUMENT_CUSTOMER_ID,
                InputArgument::OPTIONAL,
                'Specific customer ID to refresh'
            )
            ->addOption(
                self::OPTION_TYPE,
                't',
                InputOption::VALUE_OPTIONAL,
                'Profile type: browsing, purchase, wishlist, just_for_you (default: all types)',
                null
            )
            ->addOption(
                self::OPTION_ALL,
                'a',
                InputOption::VALUE_NONE,
                'Refresh all customer profiles'
            )
            ->addOption(
                self::OPTION_STALE,
                's',
                InputOption::VALUE_OPTIONAL,
                'Refresh profiles older than X hours (default: 24)',
                24
            );

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerId = $input->getArgument(self::ARGUMENT_CUSTOMER_ID);
        $type = $input->getOption(self::OPTION_TYPE);
        $refreshAll = $input->getOption(self::OPTION_ALL);
        $staleHours = (int) $input->getOption(self::OPTION_STALE);

        try {
            if ($customerId) {
                // Refresh specific customer
                return $this->refreshCustomer((int) $customerId, $type, $output);
            }

            if ($refreshAll) {
                // Refresh all stale profiles
                return $this->refreshStaleProfiles($staleHours, $output);
            }

            $output->writeln('<comment>Please specify a customer ID or use --all flag.</comment>');
            $output->writeln('');
            $output->writeln('Usage:');
            $output->writeln('  bin/magento recommendation:refresh-profiles 123           # Refresh customer 123');
            $output->writeln('  bin/magento recommendation:refresh-profiles 123 -t browsing  # Refresh only browsing profile');
            $output->writeln('  bin/magento recommendation:refresh-profiles --all        # Refresh all stale profiles');
            $output->writeln('  bin/magento recommendation:refresh-profiles --all -s 12  # Refresh profiles older than 12 hours');

            return Cli::RETURN_SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Refresh profiles for a specific customer
     *
     * @param int $customerId
     * @param string|null $type
     * @param OutputInterface $output
     * @return int
     */
    private function refreshCustomer(int $customerId, ?string $type, OutputInterface $output): int
    {
        $output->writeln(sprintf('<info>Refreshing profiles for customer %d...</info>', $customerId));

        $types = $type ? [$type] : [
            PersonalizedRecommendationInterface::TYPE_BROWSING,
            PersonalizedRecommendationInterface::TYPE_PURCHASE,
            PersonalizedRecommendationInterface::TYPE_WISHLIST,
            PersonalizedRecommendationInterface::TYPE_JUST_FOR_YOU,
        ];

        foreach ($types as $profileType) {
            $output->write(sprintf('  - Refreshing %s profile... ', $profileType));
            
            try {
                $this->recommendationService->refreshProfile($customerId, $profileType);
                $output->writeln('<info>Done</info>');
            } catch (\Exception $e) {
                $output->writeln('<error>Failed: ' . $e->getMessage() . '</error>');
            }
        }

        $output->writeln('<info>Profile refresh complete!</info>');
        return Cli::RETURN_SUCCESS;
    }

    /**
     * Refresh all stale profiles
     *
     * @param int $staleHours
     * @param OutputInterface $output
     * @return int
     */
    private function refreshStaleProfiles(int $staleHours, OutputInterface $output): int
    {
        $output->writeln(sprintf('<info>Finding profiles older than %d hours...</info>', $staleHours));

        $staleProfiles = $this->profileResource->getStaleProfiles($staleHours, 500);
        $count = count($staleProfiles);

        if ($count === 0) {
            $output->writeln('<info>No stale profiles found.</info>');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln(sprintf('<info>Found %d stale profiles to refresh.</info>', $count));

        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();

        $success = 0;
        $failed = 0;

        foreach ($staleProfiles as $profile) {
            try {
                $this->recommendationService->refreshProfile(
                    (int) $profile['customer_id'],
                    $profile['profile_type']
                );
                $success++;
            } catch (\Exception $e) {
                $failed++;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln(sprintf('<info>Refreshed %d profiles successfully, %d failed.</info>', $success, $failed));

        return Cli::RETURN_SUCCESS;
    }
}
