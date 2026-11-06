<?php

namespace Zitec\EmagMarketplace\ApiWrapper\AlertManager;

use Zitec\EmagMarketplace\ApiWrapper\Requests\AbstractRequest;

/**
 * Class NullManager
 * @package Zitec\EmagMarketplace\ApiWrapper\AlertManager
 */
class NullManager implements AlertManagerInterface
{
    /**
     * @param string $type
     * @param mixed $message
     * @param AbstractRequest|null $request
     *
     * @return null
     */
    public function alert(string $type, $message, ?AbstractRequest $request = null)
    {
        return null;
    }
}
