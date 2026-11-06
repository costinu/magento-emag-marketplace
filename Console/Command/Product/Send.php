<?php

namespace Zitec\EmagMarketplace\Console\Command\Product;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zitec\EmagMarketplace\Model\Config;
use Zitec\EmagMarketplace\Model\Queue\Product\Handler;
use Zitec\EmagMarketplace\Model\Queue\Product\HandlerFactory;

/**
 * Class Send
 * @package Zitec\EmagMarketplace\Console\Command\Product
 */
class Send extends Command
{
    const COMMAND_NAME = 'zitec_emagmarketplace:product:send';

    /**
     * @var HandlerFactory
     */
    protected $handlerFactory;

    /**
     * @var Config
     */
    protected $config;

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
     * @param Config $config
     * @param HandlerFactory $handlerFactory
     * @param State $state
     * @param LoggerInterface $logger
     * @internal param LocalityRepositoryInterface $localityRepository
     */
    public function __construct(
        Config $config,
        HandlerFactory $handlerFactory,
        State $state,
        LoggerInterface $logger
    ) {
        $this->handlerFactory = $handlerFactory;
        $this->config = $config;
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
            ->setDescription('Send product offers to eMag Marketplace.');

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

        $output->writeln(date('H:i:s') . ' Start sending products.');
        for ($i = 0; $i < $this->config->getQueueLimit(); $i++) {
            try {
                $result = $this->handlerFactory->create()->handle();

                if (!$result) {
                    break;
                }
            } catch (\Throwable $exception) {
                $output->write('Error: ' . $exception->getMessage());
                $this->logger->critical($exception);
            }
        }
        $output->writeln(date('H:i:s') . ' Finished sending products.');

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
