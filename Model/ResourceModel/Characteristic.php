<?php

namespace Zitec\EmagMarketplace\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Zitec\EmagMarketplace\Api\Data\CharacteristicInterface;

/**
 * Class Characteristic
 * @package Zitec\EmagMarketplace\Model\ResourceModel
 */
class Characteristic extends AbstractDb
{
    protected $_idFieldName = CharacteristicInterface::ID;

    /**
     * @var
     */
    protected $connection;

    /**
     * {@inheritDoc}
     */
    protected function _construct()
    {
        $this->_init(CharacteristicInterface::TABLE, CharacteristicInterface::ID);
        $this->connection = $this->getConnection();
    }

    /**
     * @param array $data
     *
     * @return int
     */
    public function massInsert(array $data): int
    {
        return $this->connection->insertMultiple($this->getMainTable(), $data);
    }

    /**
     * @param array|null $exceptedIds
     * @return mixed
     */
    public function emptyTable(?array $exceptedIds = null)
    {
        $select = $this->connection->select();
        $select->from(
            [$this->getMainTable()]
        );
        if ($exceptedIds) {
            $select->where('id NOT IN (?)', $exceptedIds);
        }


        return $this->connection->query($this->connection->deleteFromSelect(
            $select,
            $this->getMainTable()
        ));
    }
}
