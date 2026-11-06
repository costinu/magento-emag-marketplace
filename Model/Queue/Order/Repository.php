<?php

namespace Zitec\EmagMarketplace\Model\Queue\Order;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Zitec\EmagMarketplace\Api\Data\OrderQueueItemInterface;
use Zitec\EmagMarketplace\Api\QueueOrderRepositoryInterface;
use Zitec\EmagMarketplace\Model\ResourceModel\Queue\Order\Item as ItemResourceModel;
use Zitec\EmagMarketplace\Model\ResourceModel\Queue\Order\Item\Collection;
use Zitec\EmagMarketplace\Model\ResourceModel\Queue\Order\Item\CollectionFactory;

/**
 * Class Repository
 * @package Zitec\EmagMarketplace\Model\Queue\Order
 */
class Repository implements QueueOrderRepositoryInterface
{
    /**
     * @var ItemFactory
     */
    protected $factory;

    /**
     * @var ItemResourceModel
     */
    protected $resourceModel;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * {@inheritDoc}
     */
    public function __construct(
        ItemFactory $factory,
        ItemResourceModel $resourceModel,
        CollectionFactory $collectionFactory,
        DateTime $date
    ) {

        $this->factory           = $factory;
        $this->resourceModel     = $resourceModel;
        $this->collectionFactory = $collectionFactory;
        $this->date              = $date;
    }

    /**
     * {@inheritDoc}
     */
    public function insert(int $emagId, string $status)
    {
        $object = $this->factory->create();

        $object->setEmagId($emagId);
        $object->setStatus($status);
        $object->setCreatedAt($this->date->gmtDate());

        $this->resourceModel->save($object);
    }

    /**
     * {@inheritDoc}
     */
    public function getByEmagId(int $emagId): Collection
    {
        return $this->collectionFactory->create()
                                       ->addFieldToFilter(OrderQueueItemInterface::EMAG_ID, $emagId);
    }

    /**
     * {@inheritDoc}
     */
    public function getByStatus(string $status): Collection
    {
        return $this->collectionFactory->create()
                                       ->addFieldToFilter(OrderQueueItemInterface::STATUS, $status);
    }

    /**
     * {@inheritDoc}
     */
    public function countByStatus(string $status): int
    {
        return $this->getByStatus($status)->getSize();
    }

    /**
     * {@inheritDoc}
     */
    public function getBatchByStatus(string $status, int $limit): Collection
    {
        return $this->getByStatus($status)
            ->setOrder(OrderQueueItemInterface::ID, Collection::SORT_ORDER_ASC)
            ->setPageSize($limit)
            ->setCurPage(1);
    }
}
