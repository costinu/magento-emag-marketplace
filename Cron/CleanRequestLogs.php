<?php

namespace Zitec\EmagMarketplace\Cron;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Model\Config;

/**
 * Deletes API request logs (var/log/zitec_emkp/requests_*.log) older than the configured retention.
 */
class CleanRequestLogs
{
    const LOG_DIR = 'log/zitec_emkp';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Filesystem $filesystem
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(Filesystem $filesystem, Config $config, LoggerInterface $logger)
    {
        $this->filesystem = $filesystem;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute()
    {
        $var = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);

        if (!$var->isDirectory(self::LOG_DIR)) {
            return;
        }

        $threshold = time() - $this->config->getLogRetentionDays() * 86400;

        try {
            foreach ($var->read(self::LOG_DIR) as $path) {
                if (!preg_match('#/requests_[0-9_]+\.log$#', $path) || !$var->isFile($path)) {
                    continue;
                }

                $stat = $var->stat($path);

                if (($stat['mtime'] ?? time()) < $threshold) {
                    $var->delete($path);
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->error('eMAG request log cleanup: ' . $exception->getMessage());
        }
    }
}
