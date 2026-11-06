<?php

namespace Zitec\EmagMarketplace\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;
use Zitec\EmagMarketplace\Model\Config;

/**
 * Shows the order callback URL to set in eMAG (Profile > Technical details > Order callback URL).
 */
class CallbackUrl extends Field
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(Context $context, Config $config, array $data = [])
    {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getCallbackUrl(): string
    {
        $key = $this->config->getCallbackKey();

        if ($key === '') {
            return '';
        }

        $store = $this->_storeManager->getDefaultStoreView();
        $baseUrl = $store ? $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true) : '';

        return $baseUrl . 'emagmarketplace/order/ping/key/' . $key . '/';
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $url = $this->getCallbackUrl();

        if ($url === '') {
            return '<p>' . $this->escapeHtml(__('Save the configuration to generate the callback key.')) . '</p>';
        }

        return '<input type="text" class="input-text admin__control-text" readonly="readonly" onclick="this.select()" value="'
            . $this->escapeHtmlAttr($url) . '"/>';
    }

    /**
     * @param AbstractElement $element
     * @return bool
     */
    protected function _isInheritCheckboxRequired(AbstractElement $element)
    {
        return false;
    }
}
