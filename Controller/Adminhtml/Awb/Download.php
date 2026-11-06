<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Awb;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Zitec\EmagMarketplace\Exception\AwbDownloadException;
use Zitec\EmagMarketplace\Model\Awb\Manager;

/**
 * Class Download
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Awb
 */
class Download extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::awb';

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * Download constructor.
     *
     * @param Context $context
     * @param Manager $manager
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Context $context,
        Manager $manager,
        ManagerInterface $messageManager
    ) {
        parent::__construct($context);
        $this->manager        = $manager;
        $this->messageManager = $messageManager;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute()
    {
        $id     = $this->getRequest()->getParam('id');
        $format = $this->getRequest()->getParam('format');

        $response = $this->resultRedirectFactory->create();

        try {
            if (!$id || !$format) {
                throw new AwbDownloadException(__('Invalid download URL.'));
            }

            $downloadUrl = $this->manager->getDownloadUrl($id, $format);

            $response->setUrl($downloadUrl);
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            $response->setUrl($this->_redirect->getRefererUrl());
        }

        return $response;
    }
}
