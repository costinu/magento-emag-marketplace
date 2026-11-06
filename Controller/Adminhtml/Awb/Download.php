<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Awb;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Zitec\EmagMarketplace\Exception\AwbDownloadException;
use Zitec\EmagMarketplace\Model\Awb\Manager;

/**
 * Sends the AWB PDF to the admin user (the file is kept outside the web root).
 */
class Download extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::awb';

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @param Context $context
     * @param Manager $manager
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        Manager $manager,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->manager = $manager;
        $this->fileFactory = $fileFactory;
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        $id     = (int)$this->getRequest()->getParam('id');
        $format = (string)$this->getRequest()->getParam('format');

        try {
            if (!$id || $format === '') {
                throw new AwbDownloadException(__('Invalid download URL.'));
            }

            $path = $this->manager->getPdfPath($id, $format);

            return $this->fileFactory->create(
                'awb_' . $id . '_' . strtoupper($format) . '.pdf',
                ['type' => 'filename', 'value' => $path],
                DirectoryList::VAR_DIR,
                'application/pdf'
            );
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            return $this->resultRedirectFactory->create()->setUrl($this->_redirect->getRefererUrl());
        }
    }
}
