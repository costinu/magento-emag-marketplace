<?php

namespace Zitec\EmagMarketplace\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Escaper;
use Psr\Log\LoggerInterface as Logger;
use Zitec\EmagMarketplace\ApiWrapper\AlertManager\AlertManagerInterface;
use Zitec\EmagMarketplace\ApiWrapper\Requests\AbstractRequest;

/**
 * Class AlertManager
 * @package Zitec\EmagMarketplace\Model
 */
class AlertManager implements AlertManagerInterface
{
    const EMAIL_TEMPLATE_ID = 'zitec_emagmarketplace_email_template';

    const CACHE_KEY_PREFIX = 'zitec_emkp_alerts_';

    /**
     * @var Config
     */
    protected $config;
    /**
     * @var \Zitec\EmagMarketplace\Model\SendEmail
     */
    protected $sendEmail;
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var CacheInterface
     */
    protected $cache;
    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * AlertManager constructor.
     *
     * @param Config $config
     * @param \Zitec\EmagMarketplace\Model\SendEmail $sendEmail
     * @param Logger $logger
     * @param CacheInterface $cache
     * @param Escaper $escaper
     */
    public function __construct(
        Config $config,
        SendEmail $sendEmail,
        Logger $logger,
        CacheInterface $cache,
        Escaper $escaper
    ) {
        $this->config    = $config;
        $this->sendEmail = $sendEmail;
        $this->logger    = $logger;
        $this->cache     = $cache;
        $this->escaper   = $escaper;
    }

    /**
     * @param string $type
     * @param mixed $message
     * @param AbstractRequest|null $request
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function alert(string $type, $message, ?AbstractRequest $request = null)
    {
        $text = is_scalar($message) || $message instanceof \Stringable
            ? (string)$message
            : print_r($message, true);
        // The e-mail template prints the message unescaped (it contains markup); the text comes from
        // exceptions and eMAG responses, so it is escaped here.
        $text = $this->escaper->escapeHtml(mb_substr($text, 0, 5000));

        switch ($type) {
            case 'curl_error':
                $to           = $this->config->getApiErrorEmail();
                $subject      = __('Curl Error On Emag Marketplace API Call');
                $emailMessage = __('Curl Error: <strong>%1</strong>', $text);
                break;

            case 'emag_error':
                $to           = $this->config->getImportErrorEmail();
                $subject      = __('Emag Marketplace Response Error');
                $emailMessage = __('<p>Emag Marketplace Response: <strong>%1</strong></p>', $text);
                break;

            default:
                $this->logger->critical('eMAG alert of unknown type ' . $type);
                return;
        }

        if (!$to) {
            $this->logger->critical(__('Alert Email not set. Alert message bellow:'));
            $this->logger->critical($emailMessage);
            return;
        }

        if (!$this->withinHourlyLimit($type)) {
            $this->logger->warning('eMAG alert e-mail limit reached, not sent: ' . $emailMessage);
            return;
        }

        $templateVars = [
            'message' => $emailMessage,
            'subject' => $subject,
        ];

        try {
            foreach (explode(',', $to) as $recipient) {
                $this->sendEmail->send(trim($recipient), $templateVars, self::EMAIL_TEMPLATE_ID);
            }
        } catch (\Exception $e) {
            $this->logger->critical(__METHOD__ . ' Exception: ' . $e->getMessage());
        }
    }

    /**
     * Counts the alerts of a type in the current hour; false once the configured limit is reached.
     *
     * @param string $type
     * @return bool
     */
    protected function withinHourlyLimit(string $type): bool
    {
        $limit = $this->config->getMaxAlertEmailsPerHour();

        if ($limit === 0) {
            return true;
        }

        $key = self::CACHE_KEY_PREFIX . $type . '_' . gmdate('YmdH');
        $sent = (int)$this->cache->load($key);

        if ($sent >= $limit) {
            return false;
        }

        $this->cache->save((string)($sent + 1), $key, [], 3600);

        return true;
    }
}
