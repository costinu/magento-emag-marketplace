<?php

namespace Zitec\EmagMarketplace\Console\Command\Product;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zitec\EmagMarketplace\Model\ProductStatus;
use Zitec\EmagMarketplace\Model\ProductStatusFactory;

/**
 * Class Read
 * @package Zitec\EmagMarketplace\Console\Command\Product
 */
class Read extends Command
{
    const COMMAND_NAME = 'zitec_emagmarketplace:product:read';

    /**
     * @var ProductStatusFactory
     */
    protected $statusFactory;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Locality constructor.
     * @param ProductStatusFactory $statusFactory
     * @param State $state
     * @param LoggerInterface $logger
     * @internal param LocalityRepositoryInterface $localityRepository
     */
    public function __construct(
        ProductStatusFactory $statusFactory,
        State $state,
        LoggerInterface $logger
    ) {
        $this->statusFactory = $statusFactory;
        $this->logger = $logger;
        $this->state = $state;

        parent::__construct();
    }

    /**
     *
     */
    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Read product offers validation statuses.');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_GLOBAL);
        } catch (\Exception $exception) {}

        try {
            $output->writeln(date('H:i:s') . ' Start reading product offers validation statuses.');
            $this->statusFactory->create()->importProductStatus();
            $output->writeln(date('H:i:s') . ' Finished reading product offers validation statuses.');
        } catch (\Exception $exception) {
            $output->write('Error: ' . $exception->getMessage());
            $this->logger->critical($exception);
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
