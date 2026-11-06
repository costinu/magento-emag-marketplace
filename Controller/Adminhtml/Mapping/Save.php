<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Zitec\EmagMarketplace\Model\CategoryRepository;
use Zitec\EmagMarketplace\Model\DataImporter;
use Zitec\EmagMarketplace\Model\MappingManager;

/**
 * Class Save
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Mapping
 */
class Save extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::mapping';

    /**
     * @var JsonFactory
     */
    protected $jsonResponseFactory;

    /**
     * @var MappingManager
     */
    protected $mappingManager;

    /**
     * @var DataImporter
     */
    protected $dataImporter;

    /**
     * @var CategoryRepository
     */
    protected $categoryRepository;

    /**
     * Save constructor.
     * @param Action\Context $context
     * @param JsonFactory $jsonResponseFactory
     * @param MappingManager $mappingManager
     * @param ManagerInterface $messageManager
     * @param DataImporter $dataImporter
     * @param CategoryRepository $categoryRepository
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $jsonResponseFactory,
        MappingManager $mappingManager,
        ManagerInterface $messageManager,
        DataImporter $dataImporter,
        CategoryRepository $categoryRepository
    ) {
        parent::__construct($context);

        $this->jsonResponseFactory = $jsonResponseFactory;
        $this->mappingManager = $mappingManager;
        $this->messageManager = $messageManager;
        $this->dataImporter = $dataImporter;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $response = $this->jsonResponseFactory->create();

        $id = $this->getRequest()->getParam('id');
        $id = $id !== null && $id !== '' ? (int)$id : null;
        $emagCategoryId = $this->getRequest()->getParam('emag_category_id');
        $magentoCategoryId = $this->getRequest()->getParam('magento_category_id');
        $characteristics = $this->getRequest()->getParam('characteristic', []);

        if (!$emagCategoryId || !$magentoCategoryId) {
            $response->setData(['error' => true, 'message' => __('Please complete all required field.')]);

            return $response;
        }

        try {

            $emagCategory = $this->categoryRepository->getById($emagCategoryId);
            
            $this->dataImporter->importCharactersticsByCategoryId($emagCategory->getEmagId());
            
            $mapping = $this->mappingManager->save((int)$emagCategoryId, (int)$magentoCategoryId, $characteristics, $id);
            
            $response->setData([
                'error' => false,
                'message' => __('Mapping saved!'),
                'redirectUrl' => $this->getUrl('*/*/edit', ['id' => $mapping->getId()]),
            ]);

            $this->messageManager->addSuccessMessage(__('The mapping has been saved!'));
        } catch (\Throwable $exception) {
            $response->setData(['error' => true, 'message' => $exception->getMessage()]);
        }

        return $response;
    }
}
