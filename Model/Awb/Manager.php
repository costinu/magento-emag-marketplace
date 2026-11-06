<?php

namespace Zitec\EmagMarketplace\Model\Awb;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\AwbRepositoryInterface;
use Zitec\EmagMarketplace\ApiWrapper\Exceptions\FailedRequestException;
use Zitec\EmagMarketplace\ApiWrapper\Exceptions\MissingEndpointException;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Awb\ReadRequest;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Awb\SaveRequest;
use Zitec\EmagMarketplace\Exception\AwbDownloadException;
use Zitec\EmagMarketplace\Exception\AwbGenerationException;
use Zitec\EmagMarketplace\Exception\MissingAwbDataException;
use Zitec\EmagMarketplace\Model\ApiClient;
use Zitec\EmagMarketplace\Model\Awb;
use Zitec\EmagMarketplace\Model\Awb as AwbModel;
use Zitec\EmagMarketplace\Model\AwbFactory;
use Zitec\EmagMarketplace\Model\Config;
use Zitec\EmagMarketplace\Model\Json;
use Zitec\EmagMarketplace\Model\Order\Handler;
use Zitec\EmagMarketplace\Model\OrderAttributes;
use Zitec\EmagMarketplace\Model\Queue\Order\Importer;

/**
 * Class Manager
 * @package Zitec\EmagMarketplace\Model\Awb
 */
class Manager
{
    const DIR_NAME = 'zitec_emkp/awb';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var AwbFactory
     */
    protected $awbFactory;

    /**
     * @var AwbRepositoryInterface
     */
    protected $awbRepository;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Importer
     */
    protected $orderImporter;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * Manager constructor.
     * @param Config $config
     * @param ApiClient $apiClient
     * @param AwbFactory $awbFactory
     * @param AwbRepositoryInterface $awbRepository
     * @param Filesystem $filesystem
     * @param File $file
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderResourceModel $orderResourceModel
     * @param Importer $orderImporter
     * @param Registry $registry
     */
    public function __construct(
        Config $config,
        ApiClient $apiClient,
        AwbFactory $awbFactory,
        AwbRepositoryInterface $awbRepository,
        Filesystem $filesystem,
        File $file,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        OrderResourceModel $orderResourceModel,
        Importer $orderImporter,
        Registry $registry
    ) {

        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->awbFactory = $awbFactory;
        $this->awbRepository = $awbRepository;
        $this->filesystem = $filesystem;
        $this->file = $file;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->orderImporter = $orderImporter;
        $this->orderResourceModel = $orderResourceModel;
        $this->registry = $registry;
    }

    /**
     * @param array $data
     *
     * @return bool
     * @throws AwbGenerationException
     * @throws MissingAwbDataException
     * @throws FailedRequestException
     * @throws MissingEndpointException
     */
    public function create(array $data): bool
    {
        $this->validateAwbData($data);

        $awbData = $this->prepareGenerationRequestData($data);

        $request = new SaveRequest($awbData);
        $response = $this->apiClient->sendRequest($request);

        if ($response->isError) {
            throw new AwbGenerationException(__('The following errors have occurred on AWB generation: "%1"',
                implode(', ', $response->messages)));
        }

        try {
            $this->saveGeneratedAwb($response, $data['order_id']);
            sleep(3); // There is a delay on generating AWB and status change in eMag, so delay to insure order status has changed
            $this->updateOrderStatus($data['order_id']);
            return true;
        } catch (\Throwable $exception) {
            throw new AwbGenerationException(__('The AWB has been generated in eMag Marketplace but could not be saved in Magento. The following error occurred: "%1".',
                $exception->getMessage()));
        }
    }

    /**
     * Downloads the AWB PDF from eMAG (once) into var/zitec_emkp/awb/ - not web accessible, the AWB holds
     * the customer's name, address and phone - and returns its path relative to var/.
     *
     * @param int $id
     * @param string $format
     *
     * @return string
     * @throws AwbDownloadException
     */
    public function getPdfPath(int $id, string $format): string
    {
        $format = strtoupper($format);

        if (!in_array($format, AwbModel::$sizes, true)) {
            throw new AwbDownloadException(__('Invalid AWB format "%1".', $format));
        }

        try {
            $awb = $this->awbRepository->getById($id);
        } catch (NoSuchEntityException $exception) {
            throw new AwbDownloadException(__('Invalid AWB.'));
        }

        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $filePath = self::DIR_NAME . '/' . $id . '_' . $format . '.pdf';

        if ($directory->isFile($filePath)) {
            return $filePath;
        }

        $request = new ReadRequest([
            'emag_id' => $awb->getEmagId(),
            'awb_format' => $format,
        ]);

        try {
            $response = $this->apiClient->sendRequest($request);
        } catch (\Throwable $exception) {
            $this->logger->critical($exception);

            throw new AwbDownloadException(__('The AWB could not be downloaded. Please check logs and try again.'));
        }

        if (!is_string($response) || strncmp($response, '%PDF-', 5) !== 0) {
            $this->logger->critical('eMAG AWB ' . $id . ': the response is not a PDF.');

            throw new AwbDownloadException(__('The AWB could not be downloaded. Please check logs and try again.'));
        }

        $directory->writeFile($filePath, $response);

        return $filePath;
    }

    /**
     * @param \stdClass $response
     * @param int $orderId
     *
     * @return void
     * @throws \Exception
     * @throws AlreadyExistsException
     */
    protected function saveGeneratedAwb(\stdClass $response, int $orderId)
    {
        foreach ($response->results->awb as $emagAwb) {
            /** @var Awb $awb */
            $awb = $this->awbFactory->create();

            $awb->setCourierName($response->results->courier_name);
            $awb->setEmagId($emagAwb->emag_id);
            $awb->setAwbNumber($emagAwb->awb_number);
            $awb->setOrderId($orderId);

            $this->awbRepository->save($awb);
        }
    }

    /**
     * @param int $orderId
     * @throws AwbGenerationException
     */
    protected function updateOrderStatus(int $orderId)
    {
        $this->registry->register(Handler::EDITING_EMAG_ORDER_FLAG, 1);
        $this->registry->register(Handler::AFTER_EDIT_EMAG_ORDER_FLAG, 1);

        $order = $this->orderRepository->get($orderId);

        if (!$order) {
            throw new AwbGenerationException(
                __('Could not load order, after generate AWB. Order id: %1', $orderId)
            );
        }

        $response = $this->orderImporter->readOrder($order->getData(OrderAttributes::EMAG_ORDER_ID));

        // Stop here if error or no results
        if (!$response || $response['isError']) {
            throw new AwbGenerationException(
                __('Could not update Data. Empty order data, for eMAG order id: %1',
                    $order->getData(OrderAttributes::EMAG_ORDER_ID))
            );
        }

        $importedOrder = reset($response['results']);

        $this->orderImporter->updateOrderStatus($order, $importedOrder);
        $this->orderResourceModel->save($order);
    }

    /**
     * @param array $data
     *
     * @throws MissingAwbDataException
     */
    protected function validateAwbData(array $data)
    {
        $mandatoryKeys = [
            'order_id',
            'courier_account_id',
            'insured_value',
            'weight',
            'envelope_number',
            'parcel_number',
            'observation',
            'cod',
            'name',
            'contact',
            'phone1',
            'phone2',
            'person_type',
            'locality_id',
            'street',
            'zipcode',
        ];

        if (!empty(array_diff($mandatoryKeys, array_keys($data)))) {
            throw new MissingAwbDataException(__('Please complete all required fields.'));
        }
    }

    /**
     * @param array $data
     * @return array
     * @throws MissingAwbDataException
     */
    protected function prepareGenerationRequestData(array $data): array
    {
        $order = $this->orderRepository->get($data['order_id']);

        if (!$order) {
            throw new MissingAwbDataException(__('Error loading current order.'));
        }

        $emagOrderData = $order->getData(OrderAttributes::EMAG_ORDER_DATA);

        if (!$emagOrderData) {
            throw new MissingAwbDataException(__('Error getting eMag order data from current imported order.'));
        }

        $emagOrderData = Json::json_decode($emagOrderData, true);

        return [
            'order_id' => $emagOrderData['id'],
            'courier_account_id' => (int)$data['courier_account_id'],

            'insured_value' => isset($data['insured_value']) ? (float)$data['insured_value'] : null,
            'weight' => isset($data['weight']) ? (float)$data['weight'] : null,
            'envelope_number' => isset($data['envelope_number']) ? (int)$data['envelope_number'] : 0,
            'parcel_number' => isset($data['parcel_number']) ? (int)$data['parcel_number'] : 0,
            'observation' => isset($data['observation']) ? substr($data['observation'], 0, 255) : null,
            'cod' => $data['cod'] ?? null,

            'pickup_and_return' => ($data['pickup_and_return'] == 'true' ? 1 : 0),
            'saturday_delivery' => ($data['saturday_delivery'] == 'true' ? 1 : 0),
            'sameday_delivery' => ($data['sameday_delivery'] == 'true' ? 1 : 0),

            'sender' => [
                'name' => $emagOrderData['vendor_name'],
                'contact' => $this->config->getShippingContactPerson(),
                'zipcode' => $this->config->getShippingZipcode(),
                'phone1' => $this->config->getShippingPhoneOne(),
                'phone2' => $this->config->getShippingPhoneTwo(),
                'locality_id' => $this->config->getShippingLocality(),
                'street' => $this->config->getShippingStreet(),
            ],

            'receiver' => [
                'name' => $data['name'],
                'contact' => $data['contact'],
                'phone1' => $data['phone1'],
                'phone2' => $data['phone2'],
                'legal_entity' => ($data['person_type'] == 'true' ? 1 : 0),
                'locality_id' => (int)$data['locality_id'],
                'street' => $data['street'],
                'zipcode' => $data['zipcode'],
            ],
        ];
    }
}
