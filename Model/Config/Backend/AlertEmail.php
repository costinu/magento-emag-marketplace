<?php

namespace Zitec\EmagMarketplace\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class AlertEmail
 * @package Zitec\EmagMarketplace\Model\Config\Backend
 */
class AlertEmail extends Value
{
    /**
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        if (empty($value)) {
            return parent::beforeSave();
        }

        foreach (explode(',', (string) $value) as $email) {
            if (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
                throw new LocalizedException(
                    __('Please enter valid email addresses, separated by comma.')
                );
            }
        }

        return parent::beforeSave();
    }
}
