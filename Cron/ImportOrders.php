<?php

namespace Zitec\EmagMarketplace\Cron;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Model\AlertManager;
use Zitec\EmagMarketplace\Model\Queue\Order\Importer;
use Zitec\EmagMarketplace\Model\Queue\Order\Poller;

/**
 * Class ImportOrders
 * @package Zitec\EmagMarketplace\Cron
 */
class ImportOrders
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @var Importer
     */
    protected $importer;
    
    /**
     * @var AlertManager
     */
    protected $alertManager;

    /**
     * @var Poller
     */
    protected $poller;

    /**
     * ImportOrders constructor.
     *
     * @param State $state
     * @param Importer $importer
     * @param LoggerInterface $logger
     * @param AlertManager $alertManager
     */
    public function __construct(
        State $state,
        Importer $importer,
        LoggerInterface $logger,
        AlertManager $alertManager,
        Poller $poller
    ) {
        $this->poller = $poller;
        try {
            $state->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Throwable$exception) {
        }

        $this->logger   = $logger;
        $this->importer = $importer;
        $this->alertManager = $alertManager;
    }

    /**
     * Run Cron
     */
    public function execute()
    {
        try {
            $this->poller->poll();
        } catch (\Throwable $exception) {
            $this->logger->error('eMAG order polling: ' . $exception->getMessage());
        }

        try {
            $this->importer->importOrders();
        } catch (\Throwable $exception) {
            $this->alertManager->alert(
                'curl_error',
                __('eMAG order import failed: %1', $exception->getMessage())
            );
            $this->logger->critical($exception);
        }
    }
}
