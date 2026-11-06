<?php

namespace Zitec\EmagMarketplace\Model;

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
     * AlertManager constructor.
     *
     * @param Config $config
     * @param \Zitec\EmagMarketplace\Model\SendEmail $sendEmail
     */
    public function __construct(
        Config $config,
        SendEmail $sendEmail,
        Logger $logger
    ) {
        $this->config    = $config;
        $this->sendEmail = $sendEmail;
        $this->logger    = $logger;
    }

    /**
     * @param string $type
     * @param mixed $message
     * @param AbstractRequest|null $request
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function alert(string $type, $message, ?AbstractRequest $request = null)
    {
        $apiErrorEmails         = $this->config->getApiErrorEmail();
        $importOrderErrorEmails = $this->config->getImportErrorEmail();

        switch ($type) {
            case 'curl_error':
                $to           = $apiErrorEmails;
                $subject      = __('Curl Error On Emag Marketplace API Call');
                $emailMessage = __('Curl Error: <strong>' . $message . '</strong>');
                break;

            case 'emag_error':
                $to           = $importOrderErrorEmails;
                $subject      = __('Emag Marketplace Response Error');
                $emailMessage = __('<p>Emag Marketplace Response: <strong>' . print_r($message,
                        true) . '</strong></p>');
                break;

            default:
                $this->logger->critical('eMAG alert of unknown type ' . $type);
                return;
        }

        if ($to) {
            $templateVars = [
                'message' => $emailMessage,
                'subject' => $subject,
            ];

            try {
                $recipients = explode(',', $to);
                foreach ($recipients as $recipient) {
                    $this->sendEmail->send($recipient, $templateVars, self::EMAIL_TEMPLATE_ID);
                }
            } catch (\Exception $e) {
                $this->logger->critical(__METHOD__ . ' Exception: ' . $e->getMessage());
            }
        } else {
            $this->logger->critical(__('Alert Email not set. Alert message bellow:'));
            $this->logger->critical($emailMessage);
        }
    }
}
