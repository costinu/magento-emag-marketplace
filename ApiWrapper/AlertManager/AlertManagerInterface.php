<?php

namespace Zitec\EmagMarketplace\ApiWrapper\AlertManager;

use Zitec\EmagMarketplace\ApiWrapper\Requests\AbstractRequest;

/**
 * Interface AlertManagerInterface
 * @package Zitec\EmagMarketplace\ApiWrapper\AlertManager
 */
interface AlertManagerInterface
{
    /**
     * @param string $type
     * @param mixed $message
     * @param AbstractRequest|null $request
     *
     * @return mixed
     */
    public function alert(string $type, $message, ?AbstractRequest $request = null);
}
