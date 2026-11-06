<?php

namespace Zitec\EmagMarketplace\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Config
 * @package Zitec\EmagMarketplace\Model
 */
class Config
{
    const XML_PATH_API_URL = 'api_settings/api_group/api_url';
    const XML_PATH_API_USERNAME = 'api_settings/api_group/api_username';
    const XML_PATH_API_PASSWORD = 'api_settings/api_group/api_password';
    const XML_PATH_API_LOCALE = 'api_settings/api_group/locale';
    const XML_PATH_API_CURRENCY = 'api_settings/api_group/currency';

    const XML_PATH_QUEUE_LIMIT = 'products_settings/products_group/queue_limit';
    const XML_PATH_HANDLING_TIME = 'products_settings/products_group/handling_time';
    const XML_PATH_VAT_RATE = 'products_settings/products_group/vat_rate';
    const XML_PATH_MIN_SALE_PRICE = 'products_settings/products_group/min_sale_price';
    const XML_PATH_MAX_SALE_PRICE = 'products_settings/products_group/max_sale_price';

    const XML_PATH_ORDER_STATUS_INITIAL = 'orders_settings/orders_group/initial_order_status';
    const XML_PATH_ORDER_STATUS_FINALIZED = 'orders_settings/orders_group/finalized_order_status';
    const XML_PATH_ORDER_STATUS_CANCELLED = 'orders_settings/orders_group/cancelled_order_status';

    const XML_PATH_PAYMENT_CASH_ON_DELIVERY = 'payments_settings/payments_group/cash_on_delivery';
    const XML_PATH_PAYMENT_BANK_TRANSFER = 'payments_settings/payments_group/bank_transfer';
    const XML_PATH_PAYMENT_ONLINE_CARD = 'payments_settings/payments_group/online_card_payment';

    const XML_PATH_SHIPPING_METHOD = 'shipping_settings/shipping_group/method';
    const XML_PATH_SHIPPING_NAME = 'shipping_settings/shipping_group/name';
    const XML_PATH_SHIPPING_CONTACT_PERSON = 'shipping_settings/shipping_group/contact_person';
    const XML_PATH_SHIPPING_PHONE1 = 'shipping_settings/shipping_group/phone_number1';
    const XML_PATH_SHIPPING_PHONE2 = 'shipping_settings/shipping_group/phone_number2';
    const XML_PATH_SHIPPING_LOCALITY = 'shipping_settings/shipping_group/locality';
    const XML_PATH_SHIPPING_STREET = 'shipping_settings/shipping_group/street';
    const XML_PATH_SHIPPING_ZIPCODE = 'shipping_settings/shipping_group/zipcode';

    const XML_PATH_ALERTS_ERROR_EMAIL = 'alerts_settings/alerts_group/api_error_email';
    const XML_PATH_ALERTS_IMPORT_ERROR_EMAIL = 'alerts_settings/alerts_group/import_error_email';
    const XML_PATH_ALERTS_MAX_PER_HOUR = 'alerts_settings/alerts_group/max_emails_per_hour';

    const XML_PATH_ORDER_CALLBACK_KEY = 'orders_settings/orders_group/callback_key';
    const XML_PATH_ORDER_IMPORT_BATCH_SIZE = 'orders_settings/orders_group/import_batch_size';

    const XML_PATH_LOG_REQUESTS = 'alerts_settings/logging_group/log_requests';
    const XML_PATH_LOG_RETENTION_DAYS = 'alerts_settings/logging_group/retention_days';

    /**
     * @var array
     */
    protected $emagPaymentCodes = [
        '1' => self::XML_PATH_PAYMENT_CASH_ON_DELIVERY,
        '2' => self::XML_PATH_PAYMENT_BANK_TRANSFER,
        '3' => self::XML_PATH_PAYMENT_ONLINE_CARD,
    ];

    /**
     * @var ScopeConfigInterface
     */
    protected $config;

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $config
     */
    public function __construct(ScopeConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getApiUrl(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_URL, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getApiUsername(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_USERNAME, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getApiPassword(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_PASSWORD, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getLocale(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_LOCALE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getCurrency(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_API_CURRENCY, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getQueueLimit(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_QUEUE_LIMIT, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getHandlingTime(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_HANDLING_TIME, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return float
     */
    public function getVatRate(?int $storeId = null): float
    {
        return (float)$this->getValue(self::XML_PATH_VAT_RATE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getMinSalePrice(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_MIN_SALE_PRICE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getMaxSalePrice(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_MAX_SALE_PRICE, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getInitialOrderStatus(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_ORDER_STATUS_INITIAL, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getFinalizedOrderStatus(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_ORDER_STATUS_FINALIZED, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getCancelledOrderStatus(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_ORDER_STATUS_CANCELLED, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getApiErrorEmail(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_ALERTS_ERROR_EMAIL, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getCashOnDeliver(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_PAYMENT_CASH_ON_DELIVERY, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getBankTransfer(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_PAYMENT_BANK_TRANSFER, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getOnlineCardPayment(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_PAYMENT_ONLINE_CARD, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getShippingMethod(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_SHIPPING_METHOD, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getShippingName(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_SHIPPING_NAME, $storeId);
    }

    /**
     * @return string
     */
    public function getShippingContactPerson(): string
    {
        return 'AUTO-GENERATED';
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getShippingPhoneOne(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_SHIPPING_PHONE1, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getShippingPhoneTwo(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_SHIPPING_PHONE2, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return int
     */
    public function getShippingLocality(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_SHIPPING_LOCALITY, $storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getShippingStreet(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_SHIPPING_STREET, $storeId);
    }

    /**
     * @return string
     */
    public function getShippingZipcode(): string
    {
        return 'AUTO-GENERATED';
    }

    /**
     * @param int|null $storeId
     *
     * @return string
     */
    public function getImportErrorEmail(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_ALERTS_IMPORT_ERROR_EMAIL, $storeId);
    }

    /**
     * Secret part of the order callback URL (decrypted by the backend model declared in config.xml).
     *
     * @return string
     */
    public function getCallbackKey(): string
    {
        return trim((string)$this->config->getValue(self::XML_PATH_ORDER_CALLBACK_KEY));
    }

    /**
     * @return int
     */
    public function getOrderImportBatchSize(): int
    {
        $size = (int)$this->config->getValue(self::XML_PATH_ORDER_IMPORT_BATCH_SIZE);

        return $size > 0 ? min($size, 200) : 20;
    }

    /**
     * Maximum alert e-mails per type and hour (0 = unlimited).
     *
     * @return int
     */
    public function getMaxAlertEmailsPerHour(): int
    {
        $value = $this->config->getValue(self::XML_PATH_ALERTS_MAX_PER_HOUR);

        return $value === null || $value === '' ? 10 : max(0, (int)$value);
    }

    /**
     * @return bool
     */
    public function isRequestLogEnabled(): bool
    {
        return $this->config->isSetFlag(self::XML_PATH_LOG_REQUESTS);
    }

    /**
     * @return int
     */
    public function getLogRetentionDays(): int
    {
        $days = (int)$this->config->getValue(self::XML_PATH_LOG_RETENTION_DAYS);

        return $days > 0 ? $days : 14;
    }

    /**
     * @param int $emagId
     *
     * @return string
     */
    public function getPaymentByEmagId(int $emagId): string
    {
        return (string)$this->config->getValue($this->emagPaymentCodes[$emagId]);
    }

    /**
     * @param string $field
     * @param int|null $storeId
     *
     * @return mixed
     */
    protected function getValue(string $field, ?int $storeId = null)
    {
        return $this->config->getValue($field, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
