<?php

namespace Zitec\EmagMarketplace\Model\Logger;

use Zitec\EmagMarketplace\ApiWrapper\Logger\LoggerInterface;
use Zitec\EmagMarketplace\Model\Config;

/**
 * API request log (var/log/zitec_emkp/), only when enabled in the configuration.
 * Customer data in requests and responses (orders, AWBs) is masked.
 */
class RequestLogger implements LoggerInterface
{
    const MASK = '***';

    /**
     * Whole subtrees with personal data.
     */
    const MASKED_SUBTREES = ['customer', 'receiver'];

    /**
     * Keys with personal data anywhere in the payload.
     */
    const MASKED_KEY_PATTERN = '/(phone|e-?mail|street|address|contact|iban|bank|cnp|zipcode|postal|billing_|shipping_(?!tax)|legal_entity|registration_number|fiscal|password|username)/i';

    /**
     * @var CustomLogger
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param CustomLogger $logger
     * @param Config $config
     */
    public function __construct(CustomLogger $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * @param string $url
     * @param mixed $data
     * @param mixed $response
     * @param int|float|string $executionTime
     * @param mixed $error
     * @param mixed $errorNo
     */
    public function log($url, $data, $response, $executionTime = 0, $error = null, $errorNo = null)
    {
        if (!$this->config->isRequestLogEnabled()) {
            return;
        }

        $messageParts = [
            'REQUEST URL: ' . $url,
            'REQUEST DATA: ' . print_r($this->mask($data), true),
            'EXECUTION TIME: ' . $executionTime,
            'RESPONSE: ' . $this->formatResponse($response),
            'CURL ERROR: ' . print_r($error, true),
            'CURL ERROR NO: ' . print_r($errorNo, true),
            '------------------------------------------',
        ];

        $this->logger->info(implode(PHP_EOL, $messageParts));
    }

    /**
     * @param mixed $response
     * @return string
     */
    protected function formatResponse($response): string
    {
        if (!is_string($response)) {
            return print_r($this->mask($response), true);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return print_r($this->mask($decoded), true);
        }

        // AWB PDFs and other non-JSON bodies.
        return sprintf('<%d bytes, not JSON>', strlen($response));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function mask($value)
    {
        if (is_object($value)) {
            $value = json_decode((string)json_encode($value), true);
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key)
                && (in_array(strtolower($key), self::MASKED_SUBTREES, true) || preg_match(self::MASKED_KEY_PATTERN, $key))
            ) {
                $value[$key] = self::MASK;
                continue;
            }

            if (is_string($key) && is_string($item) && in_array(strtolower($key), ['data', 'json'], true)) {
                $decoded = json_decode($item, true);
                $value[$key] = is_array($decoded) ? $this->mask($decoded) : $item;
                continue;
            }

            $value[$key] = $this->mask($item);
        }

        return $value;
    }
}
