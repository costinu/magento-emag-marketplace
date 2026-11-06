<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Zitec\EmagMarketplace\Api\CategoryMappingRepositoryInterface;
use Zitec\EmagMarketplace\Block\Adminhtml\Mapping\Edit\Form\Category as CategoryForm;

/**
 * Class Create
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Mapping
 */
class Create extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::mapping';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var CategoryMappingRepositoryInterface
     */
    protected $categoryMappingRepository;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param CategoryMappingRepositoryInterface $categoryMappingRepository
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CategoryMappingRepositoryInterface $categoryMappingRepository,
        ManagerInterface $messageManager
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->categoryMappingRepository = $categoryMappingRepository;
        $this->messageManager = $messageManager;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        if ($id = $this->getRequest()->getParam('id')) {
            try {
                $mapping = $this->categoryMappingRepository->getById($id);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                return $this->_redirect('/*/*');
            }

            /** @var CategoryForm $categoriesBlock */
            $categoriesBlock = $resultPage->getLayout()->getBlock('ca_form_block');

            $categoriesBlock->setMappingId($id);
            $categoriesBlock->setEmagCategoryId($mapping->getEmagCategoryId());
            $categoriesBlock->setMagentoCategoryId($mapping->getMagentoCategoryId());
        }

        return $resultPage;
    }
}
