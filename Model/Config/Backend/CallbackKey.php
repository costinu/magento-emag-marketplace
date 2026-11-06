<?php

namespace Zitec\EmagMarketplace\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Encrypted;
use Magento\Framework\Exception\LocalizedException;

/**
 * Order callback key: stored encrypted; an empty value generates a new random key.
 */
class CallbackKey extends Encrypted
{
    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string)$this->getValue());

        if ($value === '') {
            $this->setValue(self::generate());
        } elseif (!preg_match('/^\*+$/', $value) && !preg_match('/^[A-Za-z0-9]{24,128}$/', $value)) {
            throw new LocalizedException(
                __('The callback key must have 24 to 128 letters and digits. Leave it empty to generate one.')
            );
        }

        parent::beforeSave();

        return $this;
    }

    /**
     * @return string
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(20));
    }
}
