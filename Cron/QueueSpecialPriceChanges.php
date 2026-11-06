<?php

namespace Zitec\EmagMarketplace\Cron;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Model\Config;
use Zitec\EmagMarketplace\Model\ProductAttributes;
use Zitec\EmagMarketplace\Model\Queue\Product\Manager;

/**
 * Re-sends the price of eMAG products whose special price starts today or ended yesterday.
 *
 * Replaces the observers on clean_cache_by_tags / clean_cache_after_reindex, which ran on every model save
 * and every reindex of the store and re-queued all eMAG products having a special price each time.
 */
class QueueSpecialPriceChanges
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Manager
     */
    protected $queueManager;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param CollectionFactory $collectionFactory
     * @param Manager $queueManager
     * @param TimezoneInterface $timezone
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        Manager $queueManager,
        TimezoneInterface $timezone,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->queueManager = $queueManager;
        $this->timezone = $timezone;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute()
    {
        if (!$this->config->isApiConfigured()) {
            return;
        }

        $today = $this->timezone->date()->format('Y-m-d');
        $yesterday = $this->timezone->date()->modify('-1 day')->format('Y-m-d');

        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter(ProductAttributes::IS_VISIBLE, 1)
            ->addAttributeToFilter('special_price', ['gt' => 0])
            ->addAttributeToFilter([
                ['attribute' => 'special_from_date', 'from' => $today . ' 00:00:00', 'to' => $today . ' 23:59:59'],
                ['attribute' => 'special_to_date', 'from' => $yesterday . ' 00:00:00', 'to' => $yesterday . ' 23:59:59'],
            ], null, 'left');

        /** @var Product $product */
        foreach ($collection as $product) {
            try {
                $this->queueManager->submitToQueue($product);
            } catch (\Throwable $exception) {
                $this->logger->error(sprintf(
                    'eMAG special price update, product %d: %s',
                    $product->getId(),
                    $exception->getMessage()
                ));
            }
        }
    }
}
