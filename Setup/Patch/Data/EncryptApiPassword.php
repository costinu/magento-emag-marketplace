<?php

namespace Zitec\EmagMarketplace\Setup\Patch\Data;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Zitec\EmagMarketplace\Model\Config;

/**
 * The API password used to be stored in plain text; it now has the Encrypted backend model.
 * Encrypts the existing values (every scope) that are not encrypted yet.
 */
class EncryptApiPassword implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EncryptorInterface $encryptor
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup, EncryptorInterface $encryptor)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->encryptor = $encryptor;
    }

    /**
     * @return $this
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['config_id', 'value'])
                ->where('path = ?', Config::XML_PATH_API_PASSWORD)
        );

        foreach ($rows as $configId => $value) {
            if ($value === null || $value === '' || preg_match('/^\d+:\d+:/', (string)$value)) {
                continue;
            }

            $connection->update(
                $table,
                ['value' => $this->encryptor->encrypt((string)$value)],
                ['config_id = ?' => (int)$configId]
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
