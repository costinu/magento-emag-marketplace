<?php

namespace Zitec\EmagMarketplace\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Model\Config;
use Zitec\EmagMarketplace\Model\MappingManager;
use Zitec\EmagMarketplace\Model\ProductAttributes;
use Zitec\EmagMarketplace\Model\Queue\Product\Manager;

/**
 * Class AddProductToQueue
 * @package Zitec\EmagMarketplace\Observer
 */
class AddProductToQueue implements ObserverInterface
{
    /**
     * @var MappingManager
     */
    protected $mappingManager;

    /**
     * @var Manager
     */
    protected $productQueueManager;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var ProductResourceModel
     */
    protected $productResourceModel;

    /**
     * @var Config
     */
    protected $config;

    /**
     * AddProductToQueue constructor.
     * @param Manager $productQueueManager
     * @param ManagerInterface $messageManager
     * @param State $state
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $productCollectionFactory
     * @param ProductResourceModel $productResourceModel
     * @param Config $config
     */
    public function __construct(
        Manager $productQueueManager,
        ManagerInterface $messageManager,
        State $state,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $productCollectionFactory,
        ProductResourceModel $productResourceModel,
        Config $config
    ) {
        $this->config = $config;
        $this->productQueueManager = $productQueueManager;
        $this->messageManager = $messageManager;
        $this->state = $state;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productResourceModel = $productResourceModel;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // Nothing to synchronise (and no product loads on every order / product save) until the
        // eMAG API credentials are configured.
        if (!$this->config->isApiConfigured()) {
            return;
        }

        try {
            $areaCode = $this->state->getAreaCode();
        } catch (LocalizedException $exception) {
            $areaCode = null;
        }

        try {
            $product = $this->getProductFromObserver($observer);
        } catch (\Throwable $exception) {
            $this->logger->critical($exception);

            return;
        }

        if (!$product || empty($product)) {
            return;
        }

        try {
            $eventName = $observer->getEvent()->getName();

            if (is_array($product) || $product instanceof \Traversable) {
                $added = true;

                foreach ($product as $item) {
                    $added = $added && $this->productQueueManager->submitToQueue($item, $eventName);
                }

                if ($added && $areaCode === 'adminhtml') {
                    $this->messageManager->addSuccessMessage(__('The products have been queued for update on eMag Marketplace.'));
                }

                return;
            }

            $added = $this->productQueueManager->submitToQueue($product, $eventName);
            if ($added && $areaCode === 'adminhtml') {
                $this->messageManager->addSuccessMessage(__('The product has been queued for update on eMag Marketplace.'));
            }
        } catch (\Throwable $exception) {
            if ($areaCode === 'adminhtml') {
                $this->messageManager->addErrorMessage($exception->getMessage() . ' ' . __('The product was not added to eMag queue.'));
            } else {
                $this->logger->critical($exception);
            }

            if ($product && !(is_array($product) || $product instanceof \Traversable)) {
                try {
                    $product->setData(ProductAttributes::IS_VISIBLE,
                        $product->getOrigData(ProductAttributes::IS_VISIBLE));
                    $this->productResourceModel->saveAttribute($product, ProductAttributes::IS_VISIBLE);
                } catch (\Throwable $exception) {
                    $this->logger->critical($exception);
                }
            }
        }
    }

    /**
     * @param Observer $observer
     * @return array|\Magento\Catalog\Api\Data\ProductInterface|Product
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getProductFromObserver(Observer $observer)
    {
        switch ($observer->getEvent()->getName()) {
            case 'cataloginventory_stock_item_save_after':
                /** @var \Magento\CatalogInventory\Model\Adminhtml\Stock\Item $item */
                $item = $observer->getItem();

                $response = $this->productRepository->getById($item->getProductId());
                break;

            case 'catalog_product_delete_before':
                $response = $observer->getDataObject();
                break;

            case 'checkout_submit_all_after':
                $response = [];

                foreach ($observer->getOrder()->getItems() as $item) {
                    if ($item) {
                        $response[] = $this->productRepository->getById($item->getProductId());
                    }
                }
                break;

            case 'catalog_product_attribute_update_before':
                $response = [];

                foreach ($observer->getProductIds() as $productId) {
                    if ($productId) {
                        $response[] = $this->productRepository->getById($productId);
                    }
                }
                break;

            default:
                $response = $observer->getProduct();
                break;
        }

        return $response;
    }
}
