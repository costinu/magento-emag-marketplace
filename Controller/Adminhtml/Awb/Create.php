<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Awb;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\OrderRepository;
use Zitec\EmagMarketplace\Model\OrderAttributes;

/**
 * Class Create
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Awb
 */
class Create extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::awb';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * Create constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ManagerInterface $messageManager
     * @param OrderRepository $orderRepository
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ManagerInterface $messageManager,
        OrderRepository $orderRepository,
        Registry $registry
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->messageManager = $messageManager;
        $this->orderRepository = $orderRepository;
        $this->registry = $registry;
    }

    /**
     * @return ResponseInterface|Page
     */
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order_id', 0);

        if (!$orderId) {
            return $this->_redirect('/');
        }

        try {
            $order = $this->orderRepository->get($orderId);

            if (!$order || !$order->getId() || !$order->getData(OrderAttributes::IS_EMAG_ORDER)) {
                $this->messageManager->addErrorMessage(__('AWBs can be generated only for eMag orders.'));

                return $this->_redirect('sales/order/index');
            }

            $this->registry->register('current_order', $order);
        } catch (\Exception $exception) {
            return $this->_redirect('/');
        }

        return $this->resultPageFactory->create();
    }
}
