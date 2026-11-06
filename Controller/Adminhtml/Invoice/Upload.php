<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Invoice;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Message\ManagerInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Exception\InvoiceUploadException;
use Zitec\EmagMarketplace\Model\Invoice\Manager;
use Zitec\EmagMarketplace\Model\Json;
use Zitec\EmagMarketplace\Model\OrderAttributes;

/**
 * Class Upload
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Invoice
 */
class Upload extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::invoice';

    const DIR_NAME = 'invoices';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var UploaderFactory
     */
    protected $uploaderFactory;

    /**
     * @var FormKey
     */
    protected $formKey;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var Manager
     */
    protected $invoiceManager;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var Redirect
     */
    protected $redirect;

    /**
     * Upload constructor.
     * @param Context $context
     * @param ManagerInterface $messageManager
     * @param Redirect $redirect
     * @param UploaderFactory $uploaderFactory
     * @param FormKey $formKey
     * @param Filesystem $filesystem
     * @param File $file
     * @param Manager $invoiceManager
     * @param OrderRepository $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ManagerInterface $messageManager,
        Redirect $redirect,
        UploaderFactory $uploaderFactory,
        FormKey $formKey,
        Filesystem $filesystem,
        File $file,
        Manager $invoiceManager,
        OrderRepository $orderRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->uploaderFactory = $uploaderFactory;
        $this->formKey = $formKey;
        $this->filesystem = $filesystem;
        $this->file = $file;
        $this->invoiceManager = $invoiceManager;
        $this->orderRepository = $orderRepository;
        $this->redirect = $redirect;
    }

    /**
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        try {
            $orderId = (int)$this->getRequest()->getPost('order_id');

            $order = $this->orderRepository->get($orderId);

            if (!$order->getEntityId() || !$order->getData(OrderAttributes::IS_EMAG_ORDER)) {
                throw new InvoiceUploadException(__('Could not load Magento order, or order is not an Emag imported order.'));
            }

            $emagOrderData = Json::json_decode($order->getData(OrderAttributes::EMAG_ORDER_DATA), true);

            if (!$emagOrderData || !array_key_exists('id', $emagOrderData)) {
                throw new InvoiceUploadException(__('Could not load Emag order data.'));
            }

            $invoice = $this->invoiceManager->saveInvoice($emagOrderData['id']);

            $result = $this->invoiceManager->uploadInvoice($invoice);

            if (!$result || false !== $result['isError']) {
                throw new InvoiceUploadException(__('Could not upload invoice to Emag Marketplace.') .
                    ' ' .
                    Json::json_encode($result['messages']));
            }
            $this->messageManager->addSuccessMessage(__('Invoice uploaded successfully.'));

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->redirect->setUrl($this->_redirect->getRefererUrl());
    }
}
