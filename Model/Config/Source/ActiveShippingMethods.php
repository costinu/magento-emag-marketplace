<?php

namespace Zitec\EmagMarketplace\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Option\ArrayInterface;
use Magento\Shipping\Model\Config;

/**
 * Class ActiveShippingMethods
 * @package Zitec\EmagMarketplace\Model\Config\Source
 */
class ActiveShippingMethods implements ArrayInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    
    /**
     * @var Config
     */
    protected $shippingModelConfig;

    /**
     * ActiveShippingMethods constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $shippingModelConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Config $shippingModelConfig
    ) {
        $this->scopeConfig         = $scopeConfig;
        $this->shippingModelConfig = $shippingModelConfig;
    }

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        $methods = [];
        $activeCarriers = $this->shippingModelConfig->getActiveCarriers();
        
        if (!$activeCarriers) {
            return $methods;
        }

        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $options = [];
            $carrierTitle = $carrierCode;
            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code      = $carrierCode . '_' . $methodCode;
                    $options[] = ['value' => $code, 'label' => $method,];
                }
                $carrierTitle = $this->scopeConfig->getValue('carriers/' . $carrierCode . '/title');
            }
            $methods[] = ['value' => $options, 'label' => $carrierTitle,];
        }

        return $methods;
    }
}
