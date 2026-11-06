<?php

namespace Zitec\EmagMarketplace\Model\Queue\Order;

use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\Data\OrderQueueItemInterface;
use Zitec\EmagMarketplace\Api\QueueOrderRepositoryInterface;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Order\ReadRequest;
use Zitec\EmagMarketplace\Model\ApiClient;
use Zitec\EmagMarketplace\Model\Config;

/**
 * Fallback for the order callback: reads the new (not yet acknowledged, status 1) eMAG orders and queues
 * the ones that are not in the import queue. Imported orders are acknowledged, so they leave status 1.
 */
class Poller
{
    const EMAG_STATUS_NEW = 1;
    const ITEMS_PER_PAGE = 100;
    const MAX_PAGES = 5;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var QueueOrderRepositoryInterface
     */
    protected $repository;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ApiClient $apiClient
     * @param QueueOrderRepositoryInterface $repository
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ApiClient $apiClient,
        QueueOrderRepositoryInterface $repository,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->apiClient = $apiClient;
        $this->repository = $repository;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return int number of orders added to the queue
     */
    public function poll(): int
    {
        if (!$this->config->isOrderPollingEnabled()) {
            return 0;
        }

        $queued = 0;
        $this->apiClient->setArrayResponse(true);

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = $this->apiClient->sendRequest(new ReadRequest([
                'status' => self::EMAG_STATUS_NEW,
                'currentPage' => $page,
                'itemsPerPage' => self::ITEMS_PER_PAGE,
            ]));

            if (!is_array($response) || !empty($response['isError']) || !isset($response['results'])) {
                $this->logger->warning('eMAG order polling: unexpected response, stopping.');
                break;
            }

            $orders = is_array($response['results']) ? $response['results'] : [];

            foreach ($orders as $order) {
                $emagId = isset($order['id']) ? (int)$order['id'] : 0;

                if ($emagId <= 0 || $this->repository->getByEmagId($emagId)->getSize()) {
                    continue;
                }

                $this->repository->insert($emagId, OrderQueueItemInterface::STATUS_PENDING);
                $queued++;
            }

            if (count($orders) < self::ITEMS_PER_PAGE) {
                break;
            }
        }

        return $queued;
    }
}
