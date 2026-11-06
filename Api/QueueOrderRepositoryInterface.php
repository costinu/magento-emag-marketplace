<?php

namespace Zitec\EmagMarketplace\Api;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zitec\EmagMarketplace\Model\ResourceModel\Queue\Order\Item\Collection;

/**
 * Interface QueueOrderRepositoryInterface
 * @package Zitec\EmagMarketplace\Api
 */
interface QueueOrderRepositoryInterface
{
    /**
     * @param int $emagId
     * @param string $status
     *
     * @return void
     */
    public function insert(int $emagId, string $status);

    /**
     * @param int $emagId
     *
     * @return AbstractCollection|Collection
     */
    public function getByEmagId(int $emagId): Collection;

    /**
     * @param string $status
     *
     * @return AbstractCollection|Collection
     */
    public function getByStatus(string $status): Collection;

    /**
     * @param string $status
     * @return int
     */
    public function countByStatus(string $status): int;

    /**
     * Oldest items with the given status first.
     *
     * @param string $status
     * @param int $limit
     * @return Collection
     */
    public function getBatchByStatus(string $status, int $limit): Collection;
}
