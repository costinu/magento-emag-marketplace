<?php

namespace Zitec\EmagMarketplace\Model\Invoice;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\ApiWrapper\Exceptions\FailedRequestException;
use Zitec\EmagMarketplace\ApiWrapper\Exceptions\MissingEndpointException;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Order\AttachmentsRequest;
use Zitec\EmagMarketplace\Exception\InvoiceUploadException;
use Zitec\EmagMarketplace\Model\ApiClient;
use Zitec\EmagMarketplace\Model\Invoice;
use Zitec\EmagMarketplace\Model\InvoiceFactory;
use Zitec\EmagMarketplace\Model\InvoiceRepository;
use Zitec\EmagMarketplace\Model\Json;

/**
 * Class Manager
 * @package Zitec\EmagMarketplace\Model\Invoice
 */
class Manager
{
    const DIR_NAME = 'zitec_emkp/invoices';

    const ATTACHMENT_TYPE_INVOICE = 1;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var UploaderFactory
     */
    protected $uploaderFactory;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var InvoiceFactory
     */
    protected $invoiceFactory;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * Manager constructor.
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param File $file
     * @param DirectoryList $directoryList
     * @param ApiClient $apiClient
     * @param StoreManagerInterface $storeManager
     * @param InvoiceFactory $invoiceFactory
     * @param InvoiceRepository $invoiceRepository
     * @param DateTime $date
     * @param LoggerInterface $logger
     */
    public function __construct(
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem,
        File $file,
        DirectoryList $directoryList,
        ApiClient $apiClient,
        StoreManagerInterface $storeManager,
        InvoiceFactory $invoiceFactory,
        InvoiceRepository $invoiceRepository,
        DateTime $date,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->uploaderFactory = $uploaderFactory;
        $this->directoryList = $directoryList;
        $this->filesystem = $filesystem;
        $this->file = $file;
        $this->apiClient = $apiClient;
        $this->storeManager = $storeManager;
        $this->invoiceFactory = $invoiceFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->date = $date;
    }

    /**
     * @param int $emagOrderId
     * @return Invoice
     * @throws InvoiceUploadException
     * @throws AlreadyExistsException 
     * @throws NoSuchEntityException
     */
    public function saveInvoice(int $emagOrderId): Invoice
    {
        // eMAG downloads the invoice from its URL, so it has to stay under pub/media; a random file name
        // keeps the URL unguessable (the original name usually holds the invoice number / customer name).
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $relativeDir = self::DIR_NAME;
        $media->create($relativeDir);

        $uploader = $this->uploaderFactory->create(['fileId' => 'file']);
        $uploader->setFilesDispersion(false);
        $uploader->setAllowedExtensions(['pdf']);
        $uploader->setFilenamesCaseSensitivity(false);
        $uploader->setAllowRenameFiles(false);

        $result = $uploader->save($media->getAbsolutePath($relativeDir), bin2hex(random_bytes(16)) . '.pdf');

        if (!$result || ($result['error'] ?? 0) !== 0 || empty($result['file'])) {
            throw new InvoiceUploadException(__('Error saving invoice on local server.'));
        }

        $relativePath = $relativeDir . '/' . $result['file'];

        $file = $media->openFile($relativePath, 'r');
        $header = (string)$file->read(5);
        $file->close();

        if ($header !== '%PDF-') {
            $media->delete($relativePath);

            throw new InvoiceUploadException(__('The uploaded file is not a PDF.'));
        }

        $url = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        $invoice = $this->invoiceFactory->create();

        $invoice->setEmagOrderId($emagOrderId);
        $invoice->setPath($media->getAbsolutePath($relativePath));
        $invoice->setUrl($url . $relativePath);
        $invoice->setCreatedAt($this->date->gmtDate());

        $this->invoiceRepository->save($invoice);

        return $invoice;
    }

    /**
     * @param Invoice $invoice
     * @return array|object
     * 
     * @throws FailedRequestException
     * @throws MissingEndpointException
     */
    public function uploadInvoice(Invoice $invoice)
    {
        $data = array(
            'order_id' => $invoice->getEmagOrderId(),
            'url' => $invoice->getUrl(),
            'type' => self::ATTACHMENT_TYPE_INVOICE,
            'force_download' => 1,
        );

        $data['name'] = 'invoice-' . $invoice->getEmagOrderId() . '.pdf';

        $request = new AttachmentsRequest([$data]);

        $this->apiClient->setArrayResponse(true);
        return $this->apiClient->sendRequest($request);
    }
}
