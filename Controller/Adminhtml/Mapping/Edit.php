<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Class Edit
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Mapping
 */
class Edit extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::mapping';

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        if ($this->getRequest()->getParam('id')) {
            return $this->_forward('create');
        }

        return $this->_redirect('*/*/create');
    }
}
