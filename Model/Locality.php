<?php

namespace Zitec\EmagMarketplace\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Zitec\EmagMarketplace\Api\Data\LocalityInterface;

/**
 * Class Locality
 * @package Zitec\EmagMarketplace\Model
 */
class Locality extends AbstractModel implements LocalityInterface
{
    /**
     * @var DateTime
     */
    protected $date;

    /**
     *
     */
    protected function _construct()
    {
        $this->_init('Zitec\EmagMarketplace\Model\ResourceModel\Locality');
    }

    /**
     * Locality constructor.
     *
     * @param DateTime $date
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        DateTime $date,
        Context $context,
        Registry $registry,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->date = $date;
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getData(self::ID);
    }

    /**
     * {@inheritdoc}
     */
    public function getEmagId(): int
    {
        return $this->getData(self::EMAG_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setEmagId($emagId)
    {
        return $this->setData(self::EMAG_ID, $emagId);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->getData(self::NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function setName(string $name)
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function getRegion(): string
    {
        return $this->getData(self::REGION);
    }

    /**
     * {@inheritdoc}
     */
    public function setRegion(string $region)
    {
        return $this->setData(self::REGION, $region);
    }

    /**
     * {@inheritdoc}
     */
    public function getRegion3(): string
    {
        return $this->getData(self::REGION3);
    }

    /**
     * {@inheritdoc}
     */
    public function setRegion3(string $region)
    {
        return $this->setData(self::REGION3, $region);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt(): string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt(string $createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatedAt(): string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setUpdatedAt(string $updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
