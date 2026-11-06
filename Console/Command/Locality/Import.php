<?php

namespace Zitec\EmagMarketplace\Console\Command\Locality;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zitec\EmagMarketplace\Api\LocalityRepositoryInterface;
use Zitec\EmagMarketplace\Api\LocalityRepositoryInterfaceFactory;

/**
 * Class Import
 * @package Zitec\EmagMarketplace\Console\Command\Locality
 */
class Import extends Command
{
    const COMMAND_NAME = 'zitec_emagmarketplace:locality:import';

    /**
     * @var array
     */
    protected $localities;

    /**
     * @var LocalityRepositoryInterfaceFactory
     */
    protected $localityRepositoryFactory;

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
     * @param LocalityRepositoryInterfaceFactory $localityRepositoryFactory
     * @param State $state
     * @param LoggerInterface $logger
     */
    public function __construct(
        LocalityRepositoryInterfaceFactory $localityRepositoryFactory,
        State $state,
        LoggerInterface $logger
    ) {
        $this->localityRepositoryFactory = $localityRepositoryFactory;
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
            ->setDescription('Import/reimport eMag localities.');

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
        } catch (\Exception $exception) {
        }

        try {
            /** @var LocalityRepositoryInterface $localityRepository */
            $localityRepository = $this->localityRepositoryFactory->create();
            $output->writeln(date('H:i:s') . ' Start reading localities.');
            $this->localities = $localityRepository->fetchData();
            $output->writeln(date('H:i:s') . ' Finished reading localities.');

            $output->writeln(date('H:i:s') . ' Start saving localities in database.');
            $localityRepository->updateData($this->localities);
            $output->writeln(date('H:i:s') . ' Finished saving localities in database.');
        } catch (\Exception $exception) {
            $output->write('Error: ' . $exception->getMessage());
            $this->logger->critical($exception);
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
