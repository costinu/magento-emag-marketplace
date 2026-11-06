<?php

namespace Zitec\EmagMarketplace\Model\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Class RequestLoggerHandler
 * @package Zitec\EmagMarketplace\Model\Logger
 */
class RequestLoggerHandler extends Base
{
    const LOG_FILENAME_FORMAT = '/var/log/zitec_emkp/requests_%s_%s_%s.log';

    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * RequestLoggerHandler constructor.
     * @param DriverInterface $filesystem
     * @param string|null $filePath
     * @param string|null $fileName
     */
    public function __construct(DriverInterface $filesystem, ?string $filePath = null, ?string $fileName = null)
    {
        if (!$fileName) {
            $fileName = sprintf(self::LOG_FILENAME_FORMAT, date('d'), date('m'), date('Y'));
        }

        parent::__construct($filesystem, $filePath, $fileName);
    }
}
