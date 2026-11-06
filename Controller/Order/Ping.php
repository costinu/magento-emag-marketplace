<?php

namespace Zitec\EmagMarketplace\Controller\Order;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\Data\OrderQueueItemInterface;
use Zitec\EmagMarketplace\Api\QueueOrderRepositoryInterface;
use Zitec\EmagMarketplace\Model\Config;

/**
 * eMAG order callback: <base url>emagmarketplace/order/ping/key/<callback key>/?order_id=N
 *
 * The callback key (Stores > Configuration > eMAG Marketplace > Order Settings) authenticates eMAG; requests
 * without the right key get a 404, as if the endpoint did not exist. The action only queues the eMAG order id,
 * the order itself is read from the eMAG API by cron.
 */
class Ping implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * Pending queue items above which new callbacks are refused (503, eMAG retries later).
     */
    const MAX_PENDING = 500;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var RawFactory
     */
    protected $rawFactory;

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
     * @param RequestInterface $request
     * @param RawFactory $rawFactory
     * @param QueueOrderRepositoryInterface $repository
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        RawFactory $rawFactory,
        QueueOrderRepositoryInterface $repository,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->rawFactory = $rawFactory;
        $this->repository = $repository;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Add the eMAG order id to the order import queue.
     *
     * @return Raw
     */
    public function execute()
    {
        $expectedKey = $this->config->getCallbackKey();
        $key = $this->request->getParam('key');

        if ($expectedKey === '' || !is_string($key) || !hash_equals($expectedKey, $key)) {
            return $this->respond(404, 'Not Found');
        }

        $orderId = $this->request->getParam('order_id');

        if (!is_string($orderId) || strlen($orderId) > 12 || !ctype_digit($orderId) || (int)$orderId <= 0) {
            return $this->respond(400, 'Invalid order_id');
        }

        $orderId = (int)$orderId;

        try {
            if ($this->repository->getByEmagId($orderId)->getSize()) {
                return $this->respond(200, 'OK');
            }

            if ($this->repository->countByStatus(OrderQueueItemInterface::STATUS_PENDING) >= self::MAX_PENDING) {
                $this->logger->warning(sprintf('eMAG order callback refused, %d orders already pending', self::MAX_PENDING));

                return $this->respond(503, 'Queue full');
            }

            $this->repository->insert($orderId, OrderQueueItemInterface::STATUS_PENDING);
        } catch (\Throwable $exception) {
            $this->logger->critical($exception);

            return $this->respond(500, 'Error');
        }

        return $this->respond(200, 'OK');
    }

    /**
     * The callback key authenticates the request; eMAG cannot send a form key.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param int $code
     * @param string $body
     * @return Raw
     */
    protected function respond(int $code, string $body): Raw
    {
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode($code);
        $result->setHeader('Content-Type', 'text/plain', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        $result->setHeader('X-Robots-Tag', 'noindex', true);
        $result->setContents($body);

        return $result;
    }
}
