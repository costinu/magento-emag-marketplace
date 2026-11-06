<?php

namespace Zitec\EmagMarketplace\Model\ResourceModel\Awb\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Model\ResourceModel\Awb\Collection as AwbCollection;

/**
 * Class Collection
 * @package Zitec\EmagMarketplace\Model\ResourceModel\Awb\Grid
 */
class Collection extends AwbCollection
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * Collection constructor.
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param Registry $registry
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        Registry $registry,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null
    ) {
        $this->registry = $registry;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
    }

    /**
     * @return $this
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $currentOrder = $this->registry->registry('current_order');

        if ($currentOrder) {
            $this->addFieldToFilter('order_id', $currentOrder->getId());
        }

        return $this;
    }

}
