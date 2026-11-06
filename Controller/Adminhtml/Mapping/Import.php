<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Zitec\EmagMarketplace\Model\DataImporter;

/**
 * Class Import
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Mapping
 */
class Import extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::emkp';

    /**
     * @var DataImporter
     */
    protected $dataImporter;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * Import constructor.
     * @param Context $context
     * @param DataImporter $dataImporter
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(Context $context, DataImporter $dataImporter, JsonFactory $resultJsonFactory)
    {
        parent::__construct($context);

        $this->dataImporter = $dataImporter;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $importResult = $this->dataImporter->import();

        /*$result = $this->resultJsonFactory->create();

        $result->setData([
            'success' => $importResult,
            'message' => $importResult ? __('The data has been updated.') : __('An error occurred. Please check logs.'),
        ]);
        return $result;*/
		
		if($importResult)
			$this->messageManager->addSuccess(__('The data has been updated.'));
		else
			$this->messageManager->addError(__('An error occurred. Please check logs.'));
		
		return $this->resultRedirectFactory->create()->setPath('emagmarketplace/mapping/index');
    }
}
