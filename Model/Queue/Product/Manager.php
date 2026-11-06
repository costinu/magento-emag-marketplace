<?php

namespace Zitec\EmagMarketplace\Model\Queue\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\Message\ManagerInterface;
use Zitec\EmagMarketplace\Api\Data\ProductQueueItemInterface;
use Zitec\EmagMarketplace\Api\QueueProductRepositoryInterface;
use Zitec\EmagMarketplace\Exception\MissingProductDataException;
use Zitec\EmagMarketplace\Model\MappingManager;
use Zitec\EmagMarketplace\Model\ProductAttributes;

/**
 * Class Manager
 * @package Zitec\EmagMarketplace\Model\Queue\Product
 */
class Manager
{
    /**
     * @var MappingManager
     */
    protected $mappingManager;

    /**
     * @var QueueProductRepositoryInterface
     */
    protected $queueProductRepository;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * Manager constructor.
     * @param MappingManager $mappingManager
     * @param QueueProductRepositoryInterface $queueProductRepository
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        MappingManager $mappingManager,
        QueueProductRepositoryInterface $queueProductRepository,
        ManagerInterface $messageManager
    ) {
        $this->mappingManager = $mappingManager;
        $this->queueProductRepository = $queueProductRepository;
        $this->messageManager = $messageManager;
    }

    /**
     * @return Item
     */
    public function pop()
    {
        /** @var Item $item */
        $item = $this->queueProductRepository
            ->getByState(ProductQueueItemInterface::STATE_PENDING)
            ->setOrder(ProductQueueItemInterface::UPDATED_AT, 'ASC')
            ->setPageSize(1)
            ->getFirstItem();

        return $item->getId() ? $item : null;
    }

    /**
     * @param Product $product
     * @param null|string $eventName
     * @return bool
     * @throws MissingProductDataException
     */
    public function submitToQueue(Product $product, ?string $eventName = null): bool
    {
        if (!($product->getTypeInstance() instanceof Product\Type\Simple)) {
            return false;
        }

        $action = $this->determineAction($product, $eventName);

        if ($action === ProductQueueItemInterface::ACTION_DELETE) {
            $this->saveToQueue($product, $action);
            return true;
        }

        if ($action === ProductQueueItemInterface::STATE_CANCELLED) {
            $this->cancelItems($product);
            return true;
        }

        if (!$this->validateProduct($product)) {
            return false;
        }

        $this->saveToQueue($product, $action);
        return true;
    }

    /**
     * @param Product $product
     * @return bool
     * @throws MissingProductDataException
     */
    protected function validateProduct(Product $product): bool
    {
        $visibleToEmag = $product->getData(ProductAttributes::IS_VISIBLE);
        $brand = $product->getData(ProductAttributes::BRAND);
        $ean = $product->getData(ProductAttributes::EAN);

        if (!$visibleToEmag) {
            return false;
        }

        try {
            if (!$brand) {
                throw new MissingProductDataException(__('The eMag brand is required for products that are visible in eMag Marketplace.'));
            }

            if (!($category = $this->mappingManager->getProductMappedCategory($product))) {
                throw new MissingProductDataException(__('None of this product\'s categories are mapped to an eMag category.'));
            }

            if (!$ean && $category->isEanMandatory()) {
                throw new MissingProductDataException(__('The EAN is required for the category that this product is mapped to.'));
            }

            $qty = null;
            if (($stockData = $product->getStockData()) || ($stockData = $product->getQuantityAndStockStatus())) {
                if (isset($stockData['qty'])) {
                    $qty = $stockData['qty'];
                }
            }

            if (!is_numeric($qty)) {
                throw new MissingProductDataException(__('The quantity is mandatory for eMag Marketplace products.'));
            }

            if (!$product->getMediaGalleryImages() || !$product->getMediaGalleryImages()->getSize()) {
                throw new MissingProductDataException(__('At least one product image is mandatory for eMag Marketplace products.'));
            }
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $this->messageManager->addWarningMessage(__('The product can not be published in eMAG Marketplace until the error is resolved.'));
            $this->messageManager->addWarningMessage(__('The product has not been set as "Visible in eMAG Marketplace".'));
            throw new \Exception(__('Please correct the issue above, set "Visible in eMAG Marketplace" to "Yes" and save the product again in order to publish this product in eMAG Marketplace.'));
        }

        return true;
    }

    /**
     * @param Product $product
     * @param null|string $eventName
     * @return string
     */
    protected function determineAction(Product $product, ?string $eventName = null): string
    {
        if ($eventName === 'catalog_product_delete_before' && $product->getData(ProductAttributes::IS_SENT)) {
            return ProductQueueItemInterface::ACTION_DELETE;
        }

        if ($product->getData(ProductAttributes::IS_VISIBLE)) {
            if ($eventName === 'catalog_product_delete_before') {
                return ProductQueueItemInterface::ACTION_DELETE;
            }
        } else { // if the product is not visible anymore in emag
            // if it was sent to emag
            if ($product->getData(ProductAttributes::IS_SENT)) {
                // should queue for deletion
                return ProductQueueItemInterface::ACTION_DELETE;
            }

            // if it was not sent to emag yet
            if ($product->getOrigData(ProductAttributes::IS_VISIBLE)) {
                // should cancel queued items
                return ProductQueueItemInterface::STATE_CANCELLED;
            }
        }

        return ProductQueueItemInterface::ACTION_UPDATE;
    }

    /**
     * @param Product $product
     */
    protected function cancelItems(Product $product)
    {
        $nonPendingItemsCount = $this->queueProductRepository->getByProductId($product->getId())
            ->addFieldToFilter(ProductQueueItemInterface::STATE, ['neq' => ProductQueueItemInterface::STATE_PENDING])
            ->addFieldToFilter(ProductQueueItemInterface::STATE, ['neq' => ProductQueueItemInterface::STATE_CANCELLED])
            ->count();

        if ($nonPendingItemsCount > 0) {
            $this->saveToQueue($product, ProductQueueItemInterface::ACTION_DELETE);

            return;
        }

        $this->queueProductRepository->cancelPendingByProductId($product->getId());
    }

    /**
     * @param Product $product
     * @param string $action
     */
    protected function saveToQueue(Product $product, string $action)
    {
        /*
         * if the last item has the same action & is in pending => return
         * if the last item has different action & is in pending => cancel it and insert
         */

        /** @var Item $lastItem */
        $lastItem = $this->queueProductRepository->getByProductId($product->getId())
            ->addFieldToFilter(ProductQueueItemInterface::STATE, ProductQueueItemInterface::STATE_PENDING)
            ->setOrder(ProductQueueItemInterface::UPDATED_AT)
            ->setPageSize(1)
            ->getFirstItem();

        if ($lastItem->getId()) {
            if ($lastItem->getAction() === $action) {
                return;
            }

            $this->queueProductRepository->cancelPendingByProductId($product->getId());
        }

        $this->queueProductRepository->insert($product->getId(), $action);
    }
}
