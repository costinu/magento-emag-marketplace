<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Awb;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Zitec\EmagMarketplace\Model\ApiClient;
use Zitec\EmagMarketplace\Model\Awb;
use Zitec\EmagMarketplace\Model\Config;

/**
 * Class Save
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Awb
 */
class Save extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::awb';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var Awb\Manager
     */
    protected $awbManager;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param ManagerInterface $messageManager
     * @param Awb\Manager $awbManager
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        ManagerInterface $messageManager,
        Awb\Manager $awbManager,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
        $this->messageManager = $messageManager;
        $this->awbManager = $awbManager;
        $this->dataPersistor = $dataPersistor;
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $postData = $this->getRequest()->getParams();

        $this->dataPersistor->set('awb_form_data', $postData);

        try {
            $this->awbManager->create($postData);

            $this->messageManager->addSuccessMessage(__('The AWB has been generated!'));
            $this->dataPersistor->clear('awb_form_data');

            return $this->_redirect('sales/order/view/', ['order_id' => $postData['order_id']]);
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            if (!isset($postData['order_id'])) {
                return $this->_redirect('dashboard/index/index');
            }

            return $this->_redirect('*/*/create', ['order_id' => $postData['order_id']]);
        }
    }
}
