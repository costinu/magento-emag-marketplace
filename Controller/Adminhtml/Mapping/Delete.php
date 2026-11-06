<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Zitec\EmagMarketplace\Api\CategoryMappingRepositoryInterface;

/**
 * Class Delete
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Mapping
 */
class Delete extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::mapping';

    /**
     * @var CategoryMappingRepositoryInterface
     */
    protected $mappingRepository;

    /**
     * Delete constructor.
     * @param Action\Context $context
     * @param CategoryMappingRepositoryInterface $mappingRepository
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Action\Context $context,
        CategoryMappingRepositoryInterface $mappingRepository,
        ManagerInterface $messageManager
    ) {
        parent::__construct($context);

        $this->mappingRepository = $mappingRepository;
        $this->messageManager = $messageManager;
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id', 0);

        if ($id) {
            try {
                $this->mappingRepository->deleteById($id);

                $this->messageManager->addSuccessMessage(__('The mapping has been deleted.'));
            } catch (\Throwable $exception) {
                $this->messageManager->addErrorMessage(__('An error occurred at mapping deletion. Please try again'));
            }
        }

        return $this->_redirect('*/*/');
    }
}
