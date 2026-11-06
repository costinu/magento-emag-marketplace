<?php

namespace Zitec\EmagMarketplace\Model\Order;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Order\SaveRequest;
use Zitec\EmagMarketplace\Exception\OrderEditException;
use Zitec\EmagMarketplace\Model\ApiClient;
use Zitec\EmagMarketplace\Model\Config;
use Zitec\EmagMarketplace\Model\Json;
use Zitec\EmagMarketplace\Model\OrderAttributes;
use Zitec\EmagMarketplace\Model\Queue\Order\Importer;
use Zitec\EmagMarketplace\Model\VatRepository;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Handler
 * @package Zitec\EmagMarketplace\Model\Order
 */
class Handler
{
    const DEFAULT_CANCELATION_REASON_ID = 3;
    const EDITING_EMAG_ORDER_FLAG = 'EDITING_EMAG_ORDER';
    const AFTER_EDIT_EMAG_ORDER_FLAG = 'AFTER_EDIT_EMAG_ORDER';

    /**
     * @var array
     */
    protected $statusMatrix = [
        1 => [1, 2],
        2 => [2, 3, 4, 0],
        3 => [3, 4, 0],
        4 => [3, 4, 0, 5],
        0 => [2, 3, 4, 0],
        5 => [5],
    ];

    /**
     * @var array
     */
    protected $statusMagentoToEmag = [
        'new' => 2,
        'pending_payment' => 2,
        'payment_review' => 2,
        'pending' => 2,
        'processing' => 2,
        'holded' => 2,
        'complete' => 4,
        'closed' => 5,
        'canceled' => 0,
        'refunded' => 5,
    ];

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var VatRepository
     */
    protected $vatRepository;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Importer
     */
    protected $importer;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var TaxCalculationInterface
     */
    protected $taxCalculation;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * Handler constructor.
     * @param Config $config
     * @param VatRepository $vatRepository
     * @param ApiClient $apiClient
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param Importer $importer
     * @param QuoteRepository $quoteRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxCalculationInterface $taxCalculation
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Config $config,
        VatRepository $vatRepository,
        ApiClient $apiClient,
        RequestInterface $request,
        LoggerInterface $logger,
        Importer $importer,
        QuoteRepository $quoteRepository,
        ScopeConfigInterface $scopeConfig,
        TaxCalculationInterface $taxCalculation,
        ProductRepositoryInterface $productRepository
    ) {
        $this->config = $config;
        $this->vatRepository = $vatRepository;
        $this->apiClient = $apiClient;
        $this->request = $request;
        $this->logger = $logger;
        $this->importer = $importer;
        $this->quoteRepository = $quoteRepository;
        $this->taxCalculation = $taxCalculation;
        $this->scopeConfig = $scopeConfig;

        $this->initOrderStatuses();
        $this->productRepository = $productRepository;
    }

    /**
     * @param Order $order
     * @param string|null $emagData
     * @throws OrderEditException
     */
    public function beforeSave(Order $order, ?string $emagData = null)
    {
        if ($order->getStatus() && $order->getOrigData('status') && $order->getStatus() !== $order->getOrigData('status')) {
            if (!$this->isStatusChangeAllowed($order->getOrigData('status'), $order->getStatus())) {
                throw new OrderEditException(__('Status change not allowed.'));
            }
        }

        $this->editEmagOrder($order, $emagData);
    }

    /**
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    protected function isStatusChangeAllowed(string $currentStatus, string $newStatus): bool
    {
        if (!array_key_exists($currentStatus, $this->statusMagentoToEmag) || !array_key_exists($newStatus,
                $this->statusMagentoToEmag)
        ) {
            return true;
        }

        return in_array($this->statusMagentoToEmag[$newStatus],
            $this->statusMatrix[$this->statusMagentoToEmag[$currentStatus]]);
    }

    /**
     * Initialize Statuses Mapping, Magento status to eMag code
     */
    protected function initOrderStatuses()
    {
        $this->statusMagentoToEmag[$this->config->getInitialOrderStatus()] = 2;
        $this->statusMagentoToEmag[$this->config->getFinalizedOrderStatus()] = 4;
        $this->statusMagentoToEmag[$this->config->getCancelledOrderStatus()] = 0;
    }

    /**
     * @param Order $order
     * @param string|null $emagData
     * @throws OrderEditException
     */
    protected function editEmagOrder(Order $order, ?string $emagData = null)
    {
        // Get Original Order json
        if ($emagData) {
            $emagOrderData = Json::json_decode($emagData, true);
        } else {
            $emagOrderData = Json::json_decode($order->getData(OrderAttributes::EMAG_ORDER_DATA), true);
        }

        if (!$emagOrderData) {
            throw new OrderEditException(__('Error retrieving original order import data.'));
        }

        // Update status
        if ($order->getStatus() && $order->getOrigData('status') && $order->getStatus() !== $order->getOrigData('status')) {
            $emagOrderData['status'] = $this->statusMagentoToEmag[$order->getStatus()];

            if ($order->getStatus() === $this->config->getCancelledOrderStatus()) {
                $emagOrderData['cancellation_reason'] = self::DEFAULT_CANCELATION_REASON_ID;
            }
        } elseif ($this->request->getPost('invoice') || $this->request->getPost('shipment')) {
            return;
        } else {
            if ($postItems = $this->request->getPost('item')) {

                $emagOrderData['products'] = [];

                $items = $order->getAllItems();

                if (!$items) {
                    throw new OrderEditException(__('Error retrieving current order items.'));
                }

                foreach ($items as $item) {
                    $id = $item->getQuoteItemId();

                    if (!$item->getProduct() || !$item->getProduct()->getId()) {
                        throw new OrderEditException(__('Error loading item product. Item name: ' . $item->getName()));
                    }

                    $itemProduct = $this->productRepository->getById($item->getProduct()->getId());

                    $itemPrice = $this->getPriceExcludingTax(
                        $item->getPriceInclTax(),
                        $this->vatRepository->getByEmagId($this->config->getVatRate())->getVatRate()
                    );
                    if (!array_key_exists($id, $postItems)) {
                        $emagOrderData['products'][] = [
                            'product_id' => $itemProduct->getEntityId(),
                            'sale_price' => $itemPrice,
                            'quantity' => (int)$item->getQtyOrdered(),
                            'status' => 1,
                            'vat' => $this->vatRepository->getByEmagId($this->config->getVatRate())->getVatRate(),
                        ];
                    } else {
                        $product = [
                            'product_id' => $itemProduct->getEntityId(),
                            'sale_price' => $itemPrice,
                            'status' => 1,
                            'vat' => $this->vatRepository->getByEmagId($this->config->getVatRate())->getVatRate(),
                        ];

                        if (array_key_exists('qty', $postItems[$id])) {
                            $product['quantity'] = $postItems[$id]['qty'];
                        }

                        $emagOrderData['products'][] = $product;
                    }
                }
            }

            if (($order->getState() == ORDER::STATE_COMPLETE) && ($stornoItems = $this->request->getPost('creditmemo'))) {
                $items = $order->getAllItems();

                if (!$items) {
                    throw new OrderEditException(__('Error retrieving current order items.'));
                }

                $stornoItemIds = array_keys($stornoItems['items']);

                foreach ($items as $item) {
                    $id = $item->getId();

                    if (!$item->getProduct() || !$item->getProduct()->getId()) {
                        throw new OrderEditException(__('Error loading item product. Item name: %1', $item->getName()));
                    }

                    $productId = $item->getProduct()->getId();

                    if (in_array($id, $stornoItemIds) && $stornoItems['items'][$id]['qty'] > 0) {
                        foreach ($emagOrderData['products'] as $key => $emagProd) {
                            if ($emagProd['product_id'] == $productId) {
                                $emagOrderData['products'][$key]['quantity'] -= $stornoItems['items'][$id]['qty'];

                                $emagOrderData['is_storno'] = 'true';
                            }
                        }
                    }
                }
            }

            if ($this->request->getPost('order') && array_key_exists('shipping_method',
                    $this->request->getPost('order'))
            ) {
                $quoteId = $order->getQuoteId();
                $quote = $this->quoteRepository->get($quoteId);

                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setShippingMethod($this->request->getPost('order')['shipping_method']);;

                $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

                $quote->collectTotals();

                if ($shippingAddress->getShippingInclTax() != $emagOrderData['shipping_tax']) {
                    $emagOrderData['shipping_tax'] = $shippingAddress->getShippingInclTax();
                }
            }
        }

        $response = $this->sendUpdatedOrderToEmag($emagOrderData);

        if (!$response || !array_key_exists('isError', $response)) {
            throw new OrderEditException(__('Error sending edited order data to eMag.'));
        }

        if ($response['isError']) {
            throw new OrderEditException(implode(PHP_EOL, $response['messages']));
        }

        $order->setData(OrderAttributes::IS_EMAG_ORDER, 1);
        $order->setData(OrderAttributes::EMAG_ORDER_ID, $emagOrderData['id']);
        $order->setData(OrderAttributes::EMAG_ORDER_DATA, Json::json_encode($emagOrderData));
    }

    /**
     * @param float|int $itemPrice
     * @param float|int $rate
     * @return float|int
     */
    protected function getPriceExcludingTax($itemPrice, $rate)
    {
        return $itemPrice / (1 + ($rate));
    }

    /**
     * @param array $data
     * @return array|object
     */
    protected function sendUpdatedOrderToEmag(
        array $data
    ) {
        $request = new SaveRequest([$data]);
        $this->apiClient->setArrayResponse(true);

        return $this->apiClient->sendRequest($request);
    }

    /**
     * @param Order $order
     */
    public function afterOrderEdit(Order $order)
    {
        if ($order->getState() == ORDER::STATE_CLOSED ||
            $order->getState() == ORDER::STATE_COMPLETE ||
            $order->getState() == ORDER::STATE_CANCELED ||
            !$order->getData(OrderAttributes::IS_EMAG_ORDER)
        ) {
            return;
        }

        $emagData = $order->getData(OrderAttributes::EMAG_ORDER_DATA);
        $emagData = Json::json_decode($emagData, true);
        $result = $this->importer->readOrder($emagData['id']);
        $importedOrder = reset($result['results']);

        $this->importer->setDiscountToOrder($order, $importedOrder);

        $order->setData(OrderAttributes::EMAG_ORDER_DATA, Json::json_encode($importedOrder));

        $order->save();
    }
}