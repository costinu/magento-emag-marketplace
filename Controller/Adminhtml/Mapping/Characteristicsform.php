<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\CategoryMappingRepositoryInterface;
use Zitec\EmagMarketplace\Api\CategoryRepositoryInterface;
use Zitec\EmagMarketplace\Api\CharacteristicMappingRepositoryInterface;
use Zitec\EmagMarketplace\Api\CharacteristicRepositoryInterface;
use Zitec\EmagMarketplace\Api\Data\CharacteristicInterface;
use Zitec\EmagMarketplace\Block\Adminhtml\Mapping\Edit\Form\Characteristic as CharacteristicForm;
use Zitec\EmagMarketplace\Model\ResourceModel\Characteristic\Collection;

/**
 * Class Characteristicsform
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Mapping
 */
class Characteristicsform extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::mapping';

    /**
     * @var PageFactory
     */
    protected $layoutFactory;

    /**
     * @var Http
     */
    protected $httpRequest;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var CharacteristicRepositoryInterface
     */
    protected $characteristicRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CategoryMappingRepositoryInterface
     */
    protected $categoryMappingRepository;

    /**
     * @var CharacteristicMappingRepositoryInterface
     */
    protected $characteristicMappingRepository;

    /**
     * @param Context $context
     * @param LayoutFactory $layoutFactory
     * @param Http $httpRequest
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CharacteristicRepositoryInterface $characteristicRepository
     * @param LoggerInterface $logger
     * @param CategoryMappingRepositoryInterface $categoryMappingRepository
     * @param CharacteristicMappingRepositoryInterface $characteristicMappingRepository
     */
    public function __construct(
        Context $context,
        LayoutFactory $layoutFactory,
        Http $httpRequest,
        CategoryRepositoryInterface $categoryRepository,
        CharacteristicRepositoryInterface $characteristicRepository,
        LoggerInterface $logger,
        CategoryMappingRepositoryInterface $categoryMappingRepository,
        CharacteristicMappingRepositoryInterface $characteristicMappingRepository
    ) {
        parent::__construct($context);

        $this->layoutFactory = $layoutFactory;
        $this->httpRequest = $httpRequest;
        $this->categoryRepository = $categoryRepository;
        $this->characteristicRepository = $characteristicRepository;
        $this->logger = $logger;
        $this->categoryMappingRepository = $categoryMappingRepository;
        $this->characteristicMappingRepository = $characteristicMappingRepository;
    }

    /**
     * @return ResponseInterface|ResultInterface|Layout|Page
     */
    public function execute()
    {
        if (!$this->httpRequest->isAjax()) {
            return $this->_redirect('/');
        }

        $resultPage = $this->layoutFactory->create();

        try {
            /** @var CharacteristicForm $characteristicsBlock */
            $characteristicsBlock = $resultPage->getLayout()->getBlock('characteristic_block');
            $characteristicsBlock->setCharacteristics($this->getCategoryCharacteristics());
            $characteristicsBlock->setSelectedAttributes($this->getSelectedAttributes());

        } catch (\Throwable $exception) {
            $this->logger->critical($exception);
        }

        return $resultPage;
    }

    /**
     * @return array|CharacteristicInterface[]|Collection
     */
    protected function getCategoryCharacteristics()
    {
        $categoryId = $this->getRequest()->getParam('category_id', 0);

        if (!$categoryId) {
            return [];
        }

        try {
            $emagCategory = $this->categoryRepository->getById($categoryId);

            $characteristics = $this->characteristicRepository->getByCategory($emagCategory);
            $characteristics->getSelect()
                ->order(CharacteristicInterface::IS_MANDATORY . ' DESC')
                ->order(CharacteristicInterface::NAME . ' ASC');

            return $characteristics;
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /**
     * @return array
     */
    protected function getSelectedAttributes(): array
    {
        $mappingId = $this->getRequest()->getParam('mapping_id', 0);

        if (!$mappingId) {
            return [];
        }

        try {
            $mapping = $this->categoryMappingRepository->getById($mappingId);

            $characteristics = $this->characteristicMappingRepository->getByMapping($mapping);

            $selectedAttributes = [];

            foreach ($characteristics as $characteristic) {
                $selectedAttributes[$characteristic->getEmagCharacteristicId()] = $characteristic->getMagentoAttributeId();
            }

            return $selectedAttributes;
        } catch (\Throwable $exception) {
            return [];
        }
    }
}
