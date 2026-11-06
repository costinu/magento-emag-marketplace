<?php

namespace Zitec\EmagMarketplace\Model\ResourceModel\CategoryMapping\Grid;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Search\AggregationInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\Data\CategoryInterface;
use Zitec\EmagMarketplace\Api\Data\CategoryMappingInterface;
use Zitec\EmagMarketplace\Model\ResourceModel\CategoryMapping\Collection as CategoryMappingCollection;

/**
 * Class Collection
 * @package Zitec\EmagMarketplace\Model\ResourceModel\CategoryMapping\Grid
 */
class Collection extends CategoryMappingCollection implements SearchResultInterface
{
    /**
     * @var AggregationInterface
     */
    protected $aggregations;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param mixed|null $mainTable
     * @param string $eventPrefix
     * @param mixed $eventObject
     * @param mixed $resourceModel
     * @param string $model
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     *
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable,
        $eventPrefix,
        $eventObject,
        $resourceModel,
        $model = Document::class,
        $connection = null,
        ?AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->_eventPrefix = $eventPrefix;
        $this->_eventObject = $eventObject;
        $this->_init($model, $resourceModel);
        $this->setMainTable($mainTable);
    }

    /**
     * {@inheritDoc}
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        $this->getSelect()
            ->joinLeft(
                ['emag_categories_table' => $this->getTable(CategoryInterface::TABLE),],
                'main_table.emag_category_id = emag_categories_table.id',
                ['emag_categories_table.name', 'emag_categories_table.' . CategoryInterface::IS_EAN_MANDATORY,]
            )
            ->joinLeft(
                ['magento_categories_table' => $this->getTable('catalog_category_entity'),],
                'main_table.' . CategoryMappingInterface::MAGENTO_CATEGORY_ID . '= magento_categories_table.entity_id',
                []
            )
            ->joinInner(
                ['catalog_category_varchar' => $this->getTable('catalog_category_entity_varchar'),],
                'magento_categories_table.entity_id = catalog_category_varchar.entity_id AND catalog_category_varchar.store_id = 0',
                ['value',]
            )
            ->joinInner(
                ['ea' => $this->getTable('eav_attribute'),],
                'catalog_category_varchar.attribute_id = ea.attribute_id',
                ['']
            );

        $this->addFieldToFilter('attribute_code', 'name');

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setItems(?array $items = null)
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAggregations()
    {
        return $this->aggregations;
    }

    /**
     * {@inheritDoc}
     */
    public function setAggregations($aggregations)
    {
        $this->aggregations = $aggregations;
    }

    /**
     * {@inheritDoc}
     */
    public function getSearchCriteria()
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria)
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalCount()
    {
        return $this->getSize();
    }

    /**
     * {@inheritDoc}
     */
    public function setTotalCount($totalCount)
    {
        return $this;
    }
}
