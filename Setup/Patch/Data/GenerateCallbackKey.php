<?php

namespace Zitec\EmagMarketplace\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Zitec\EmagMarketplace\Model\Config;
use Zitec\EmagMarketplace\Model\Config\Backend\CallbackKey;

/**
 * Generates the order callback key, so the callback is protected right after install / upgrade.
 */
class GenerateCallbackKey implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param WriterInterface $configWriter
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
    }

    /**
     * @return $this
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $existing = $connection->fetchOne(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('core_config_data'), ['value'])
                ->where('path = ?', Config::XML_PATH_ORDER_CALLBACK_KEY)
                ->where('scope = ?', 'default')
        );

        if ($existing === false || $existing === null || $existing === '') {
            $this->configWriter->save(
                Config::XML_PATH_ORDER_CALLBACK_KEY,
                $this->encryptor->encrypt(CallbackKey::generate())
            );
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases()
    {
        return [];
    }
}
