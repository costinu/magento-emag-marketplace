<?php

namespace Zitec\EmagMarketplace\Model\Queue\Order;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\ItemFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\Data\OrderQueueItemInterface;
use Zitec\EmagMarketplace\Api\QueueOrderRepositoryInterface;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Order\AcknowledgeRequest;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Order\ReadRequest;
use Zitec\EmagMarketplace\Model\AlertManager;
use Zitec\EmagMarketplace\Model\ApiClient;
use Zitec\EmagMarketplace\Model\Config;
use Zitec\EmagMarketplace\Model\Json;
use Zitec\EmagMarketplace\Model\OrderAttributes;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Importer
 * @package Zitec\EmagMarketplace\Model\Queue\Order
 */
class Importer
{
    const DEFAULT_EMAIL = 'default_email@defaultmarketplaceemag.com';
    const IMPORTING_ORDER_FLAG = 'importing_emag_order';

    /**
     * @var QueueOrderRepositoryInterface
     */
    protected $repository;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var OrderInterface
     */
    protected $order;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepositoryInterface;

    /**
     * @var CartManagementInterface
     */
    protected $cartManagementInterface;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var AddressFactory
     */
    protected $addressFactory;

    /**
     * @var
     */
    public $statuses;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var AlertManager
     */
    protected $alertManager;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var ItemFactory
     */
    protected $itemFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var TaxCalculationInterface
     */
    protected $taxCalculation;

    /**
     * Importer constructor.
     * @param ApiClient $apiClient
     * @param QueueOrderRepositoryInterface $repository
     * @param OrderInterface $order
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param CartRepositoryInterface $cartRepositoryInterface
     * @param CartManagementInterface $cartManagementInterface
     * @param AddressFactory $addressFactory
     * @param LoggerInterface $logger
     * @param AlertManager $alertManager
     * @param ProductFactory $productFactory
     * @param Registry $registry
     * @param ItemFactory $itemFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxCalculationInterface $taxCalculation
     */
    public function __construct(
        ApiClient $apiClient,
        QueueOrderRepositoryInterface $repository,
        OrderInterface $order,
        Config $config,
        StoreManagerInterface $storeManager,
        CartRepositoryInterface $cartRepositoryInterface,
        CartManagementInterface $cartManagementInterface,
        AddressFactory $addressFactory,
        LoggerInterface $logger,
        AlertManager $alertManager,
        ProductFactory $productFactory,
        Registry $registry,
        ItemFactory $itemFactory,
        ScopeConfigInterface $scopeConfig,
        TaxCalculationInterface $taxCalculation
    ) {
        $this->repository = $repository;
        $this->apiClient = $apiClient;
        $this->order = $order;
        $this->storeManager = $storeManager;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->config = $config;
        $this->addressFactory = $addressFactory;
        $this->logger = $logger;
        $this->alertManager = $alertManager;
        $this->productFactory = $productFactory;
        $this->registry = $registry;
        $this->itemFactory = $itemFactory;
        $this->storeManager->setCurrentStore($this->storeManager->getDefaultStoreView()->getStoreGroupId());
        $this->scopeConfig = $scopeConfig;
        $this->taxCalculation = $taxCalculation;

        $this->initOrderStatusesMapping();
    }

    /**
     *
     */
    public function importOrders()
    {
        $orderCollection = $this->repository->getByStatus(OrderQueueItemInterface::STATUS_PENDING);

        foreach ($orderCollection as $queueItem) {
            try {
                $response = $this->readOrder($queueItem->getEmagId());

                // Stop here if error or no results
                if (!$response || $response['isError'] || empty($response['results'])) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Empty order data, for eMAG order id: %1', $queueItem->getEmagId())
                    );
                }

                $importedOrder = reset($response['results']);
                $orderId = $this->insertUpdateOrder($importedOrder);

                $queueItem->setMessage(Json::json_encode($response));

                if ($orderId) {
                    $queueItem->setStatus(OrderQueueItemInterface::STATUS_SYNCHRONISED);
                    $queueItem->setMagentoId($orderId);

                    $this->acknowledgeOrder($importedOrder['id']);
                } else {
                    $queueItem->setStatus(OrderQueueItemInterface::STATUS_FAILED);
                }
            } catch (\Throwable $exception) {
                $this->alertManager->alert(
                    'curl_error',
                    __($exception->getMessage()) . $exception->getMessage()
                );

                $this->logger->critical($exception);
                $queueItem->setStatus(OrderQueueItemInterface::STATUS_FAILED);
            }
        }

        $orderCollection->save();
    }

    /**
     * @param int $emagOrderId
     * @return string
     *
     */
    protected function acknowledgeOrder(int $emagOrderId)
    {
        $request = new AcknowledgeRequest(['order_id' => $emagOrderId,]);

        $this->apiClient->setArrayResponse(true);

        return $this->apiClient->sendRequest($request);
    }

    /**
     * @param array $data
     * @return string
     */
    protected function insertUpdateOrder(array $data)
    {
        return $this->saveData($data);
    }

    /**
     * @param array $data
     * @param Order|null $existingOrder
     *
     * @return string
     */
    protected function saveData(array $data, ?Order $existingOrder = null): string
    {
        $store = $this->storeManager->getStore();
        $websiteId = $this->storeManager->getStore()->getWebsiteId();

        if ($existingOrder) {
            $existingOrder = null;
        }

        if (!$this->registry->registry(self::IMPORTING_ORDER_FLAG)) {
            $this->registry->register(self::IMPORTING_ORDER_FLAG, 1);
        }

        // create quote
        $cartId = $this->cartManagementInterface->createEmptyCart();
        $quote = $this->cartRepositoryInterface->get($cartId);
        $quote->setStore($store);

        $quote->setCurrency();

        $this->extractNames($data);

        $this->setCustomerToQuote($data, $quote, $websiteId);

        $this->setProductsToQuote($data, $quote);

        $quote->setBillingAddress($this->extractBillingAddressData($data));
        $quote->setShippingAddress($this->extractShippingAddressData($data));

        $this->setShippingToQuote($quote);

        $this->setPaymentToQuote($data, $quote);

        $quote->save();

        // Collect Totals
        $quote->collectTotals();

        // Create Order From Quote
        $quote = $this->cartRepositoryInterface->get($quote->getId());
        $orderId = $this->cartManagementInterface->placeOrder($quote->getId());
        $order = $this->order->load($orderId);

        $this->updateOrderStatus($order, $data);

        $this->forceShippingAmount($order, $data);

        $this->setDiscountToOrder($order, $data);

        $order->setEmailSent(1);

        // save eMag data status as "preluata"
        if ($data['status'] == 1) {
            $data['status'] = 2;
        }

        $order->setData(OrderAttributes::IS_EMAG_ORDER, 1);
        $order->setData(OrderAttributes::EMAG_ORDER_ID, $data['id']);
        $order->setData(OrderAttributes::EMAG_ORDER_DATA, Json::json_encode($data));

        $order->save();

        $this->registry->unregister(self::IMPORTING_ORDER_FLAG);

        return $order->getRealOrderId();
    }

    /**
     * @param Order $order
     * @param array $data
     */
    protected function forceShippingAmount(Order $order, array $data)
    {
        // Get eMag shipping tax
        $shippingPrice = $data['shipping_tax'];

        if ($shippingPrice != 0) {
            $shippingPriceExclTax = $shippingPrice;
        } else {
            $shippingPriceExclTax = 0;
        }

        // Force eMag shipping tax value on order
        $originalShippingAmount = $order->getShippingInclTax() != 0
            ? $order->getShippingInclTax()
            : $order->getBaseShippingInclTax();

        $order->setShippingAmount($shippingPriceExclTax);
        $order->setShippingInclTax($shippingPrice);
        $order->setBaseShippingAmount($shippingPriceExclTax);
        $order->setBaseInclShippingAmount($shippingPrice);
        // Recalculate total
        $order->setGrandTotal($order->getGrandTotal() - $originalShippingAmount + $shippingPrice);
        $order->setBaseGrandTotal(
            $order->getBaseGrandTotal() - $originalShippingAmount + $shippingPrice
        );
    }

    /**
     * @param Order $order
     * @param array $data
     */
    public function setDiscountToOrder(Order $order, array $data)
    {
        if (array_key_exists('vouchers', $data) && !empty($data['vouchers'])) {
            foreach ($data['vouchers'] as $voucher) {
                if ($voucher['status'] == 0) {
                    continue;
                }

                $discountAmount = $voucher['sale_price'];
                $discountAmountTax = $voucher['sale_price_vat'];

                $order->setBaseDiscountAmount($discountAmount);
                $order->setDiscountAmount($discountAmount + $discountAmountTax);

                $order->setGrandTotal($order->getGrandTotal() + $discountAmount)
                    ->setBaseGrandTotal($order->getBaseGrandTotal() + $discountAmount)
                    ->setSubtotalWithDiscount($order->getSubtotalWithDiscount() + $discountAmount)
                    ->setBaseSubtotalWithDiscount($order->getBaseSubtotalWithDiscount() + $discountAmount);

                $order->addStatusHistoryComment(
                    __('Order has applied an eMAG Voucher: ') . $voucher['voucher_name']
                );
            }
        }
    }

    /**
     * @param Order $order
     * @param array $data
     */
    public function updateOrderStatus(Order $order, array $data)
    {
        $status = array_search($data['status'], array_reverse($this->statuses));
        if ($status) {
            // Status pending has state new, only case that status and state do not coincide
            if ($status == 'pending') {
                $order->setState(Order::STATE_NEW);
            }
            $order->setStatus($status);

            $order->addStatusToHistory($order->getStatus(), __('Order status received from eMAG: ') . $data['status']);
        }
    }

    /**
     * @param array $data
     * @param Quote $quote
     */
    protected function setCustomerToQuote(array $data, Quote $quote)
    {
        $quote->setCustomerId(null);
        $quote->setCustomerEmail($data['customer']['email'] ?: self::DEFAULT_EMAIL);
        $quote->setCustomerIsGuest(true);
        $quote->setCustomerGroupId(\Magento\Customer\Api\Data\GroupInterface::NOT_LOGGED_IN_ID);
    }

    /**
     * @param array $data
     * @param Quote $quote
     *
     * @throws \Exception
     */
    protected function setProductsToQuote(array $data, Quote $quote)
    {
        $errorMessages = null;
        //add items in quote
        foreach ($data['products'] as $item) {
            if ($item['status'] == 0) {
                continue;
            }

            $product = $this->productFactory->create()->load($item['product_id']);

            if (!$product->getId()) {
                $errorMessages[] =
                    __('Error importing order from eMAG Marketplace. eMAG order id: ') . $data['id'] .
                    PHP_EOL .
                    __('Product id not found. Id: ') . $item['product_id'];
            } else {
                
                $itemPrice = $this->getTaxCalculatedPrice($item['sale_price'],(float)$item['vat'],$product);
                
                $product->setPrice($itemPrice);

                if ($product->getSpecialPrice()) {
                    $product->setSpecialPrice($itemPrice);
                }

                $newItem = $this->itemFactory->create();
                $newItem->setProduct($product);
                $newItem->setQty((float)$item['quantity']);

                $newItem->setCustomPrice($itemPrice);
                $newItem->setOriginalCustomPrice($itemPrice);
                $newItem->getProduct()->setIsSuperMode(true);

                $quote->addItem($newItem);

            }
        }

        if ($errorMessages) {
            throw new \Exception(implode(PHP_EOL, $errorMessages));
        }
    }

    /**
     * @param float|int $itemPrice
     * @param float|int $itemVat
     * @param Product $product
     * @return float|int
     */
    protected function getTaxCalculatedPrice($itemPrice, $itemVat, Product $product)
    {
        if ($itemVat !== 0) {
            $itemPrice = $itemPrice + ($itemPrice * ($itemVat));
        }
        
        if ($taxAttribute = $product->getCustomAttribute('tax_class_id')) {
            $productRateId = $taxAttribute->getValue();
            $rate = $this->taxCalculation->getCalculatedRate($productRateId);
            if ((int)$this->scopeConfig->getValue('tax/calculation/price_includes_tax',
                    ScopeInterface::SCOPE_STORE) === 1
            ) {
                // Product price in catalog is including tax.
                return $itemPrice;
            }

            // Product price in catalog is excluding tax
            // Remove VAT from current price (it will be added automatically by Magento)
            return $itemPrice / (1 + ($rate / 100));
        }

        return $itemPrice;
    }

    /**
     * @param array $data
     *
     * @return Quote\Address
     */
    public function extractBillingAddressData(array $data)
    {
        $address = $this->addressFactory->create();

        $address->setFirstname($data['customer']['billing_firstname'] ?: $data['customer']['firstname']);
        $address->setLastname($data['customer']['billing_lastname'] ?: $data['customer']['lastname']);
        $address->setEmail($data['customer']['email'] ?: self::DEFAULT_EMAIL);
        $address->setCity($data['customer']['billing_city']);
        $address->setStreet($data['customer']['billing_street']);
        $address->setTelephone($data['customer']['billing_phone'] ?: $data['customer']['phone_1']);
        $address->setPostcode($data['customer']['billing_postal_code']);
        $address->setCountryId($data['customer']['billing_country']);

        $address->setShouldIgnoreValidation(true);

        return $address;
    }

    /**
     * @param array $data
     *
     * @return Quote\Address
     */
    public function extractShippingAddressData(array $data)
    {
        $address = $this->addressFactory->create();

        $address->setFirstname($data['customer']['shipping_firstname'] ?: $data['customer']['firstname']);
        $address->setLastname($data['customer']['shipping_lastname'] ?: $data['customer']['lastname']);
        $address->setEmail($data['customer']['email'] ?: self::DEFAULT_EMAIL);
        $address->setCity($data['customer']['shipping_city']);
        $address->setStreet($data['customer']['shipping_street']);
        $address->setTelephone($data['customer']['shipping_phone']);
        if ($data['customer']['shipping_postal_code']) {
            $address->setPostcode($data['customer']['shipping_postal_code']);
        }
        $address->setCountryId($data['customer']['shipping_country']);

        $address->setShouldIgnoreValidation(true);

        return $address;
    }

    /**
     * @param array $data
     */
    public function extractNames(array &$data)
    {
        $nameParts = $this->extractName($data['customer']['name']);
        $data['customer']['firstname'] = $nameParts['firstname'];
        $data['customer']['lastname'] = $nameParts['lastname'];

        $nameParts = $this->extractName($data['customer']['billing_name']);
        $data['customer']['billing_firstname'] = $nameParts['firstname'];
        $data['customer']['billing_lastname'] = $nameParts['lastname'];

        $nameParts = $this->extractName($data['customer']['shipping_contact']);
        $data['customer']['shipping_firstname'] = $nameParts['firstname'];
        $data['customer']['shipping_lastname'] = $nameParts['lastname'];
    }

    /**
     * @param Quote $quote
     */
    protected function setShippingToQuote(Quote $quote)
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setShippingMethod($this->config->getShippingMethod()); //shipping method
    }

    /**
     * @param array $data
     * @param Quote $quote
     */
    protected function setPaymentToQuote(array $data, Quote $quote)
    {
        $paymentCode = $this->config->getPaymentByEmagId((int)$data['payment_mode_id']);

        $quote->setPaymentMethod($paymentCode); //payment method
        // Set Sales Order Payment
        $quote->getPayment()->importData(['method' => $paymentCode]);
    }

    /**
     * @param string|null $fullName
     *
     * @return array
     */
    protected function extractName(?string $fullName = null): array
    {
        $name = explode(' ', $fullName);

        $response = [
            'firstname' => $fullName,
            'lastname' => $fullName,
        ];

        if (array_key_exists(0, $name)) {
            $response['lastname'] = $name[0];
        }

        unset($name[0]);
        $response['firstname'] = implode(' ', $name);

        return $response;
    }

    /**
     * @param int $emagOrderId
     *
     * @return array
     */
    public function readOrder(int $emagOrderId): array
    {
        $request = new ReadRequest(['id' => $emagOrderId,]);

        $this->apiClient->setArrayResponse(true);

        return $this->apiClient->sendRequest($request);
    }

    /**
     *
     */
    public function initOrderStatusesMapping()
    {
        $this->statuses = [
            Order::STATE_NEW => 2,
            Order::STATE_PENDING_PAYMENT => 2,
            Order::STATE_PAYMENT_REVIEW => 2,
            Order::STATE_PROCESSING => 2,
            Order::STATE_HOLDED => 2,
            Order::STATE_COMPLETE => 4,
            Order::STATE_CANCELED => 0,
            Order::STATE_CLOSED => 5,
        ];

        $this->statuses[$this->config->getInitialOrderStatus()] = 2;
        $this->statuses[$this->config->getFinalizedOrderStatus()] = 4;
        $this->statuses[$this->config->getCancelledOrderStatus()] = 0;
    }
}