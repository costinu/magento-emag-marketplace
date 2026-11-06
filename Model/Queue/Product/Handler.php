<?php

namespace Zitec\EmagMarketplace\Model\Queue\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filter\Template;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\CharacteristicMappingRepositoryInterface;
use Zitec\EmagMarketplace\Api\CharacteristicRepositoryInterface;
use Zitec\EmagMarketplace\Api\Data\CategoryInterface;
use Zitec\EmagMarketplace\Api\Data\ProductQueueItemInterface;
use Zitec\EmagMarketplace\ApiWrapper\Requests\ProductOffer\CreateRequest;
use Zitec\EmagMarketplace\Exception\ApiResponseErrorException;
use Zitec\EmagMarketplace\Exception\MissingProductDataException;
use Zitec\EmagMarketplace\Model\ApiClient;
use Zitec\EmagMarketplace\Model\Config;
use Zitec\EmagMarketplace\Model\MappingManager;
use Zitec\EmagMarketplace\Model\ProductAttributes;

/**
 * Class Handler
 * @package Zitec\EmagMarketplace\Model\Queue\Product
 */
class Handler
{
    /**
     * @var Manager
     */
    protected $queueManager;

    /**
     * @var Repository
     */
    protected $itemRepository;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var StockStateInterface
     */
    protected $stockState;

    /**
     * @var MappingManager
     */
    protected $mappingManager;

    /**
     * @var Template
     */
    protected $templateProcessor;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CharacteristicMappingRepositoryInterface
     */
    protected $characteristicMappingRepository;

    /**
     * @var Attribute
     */
    protected $eavAttributeResourceModel;

    /**
     * @var ProductResourceModel
     */
    protected $productResourceModel;

    /**
     * @var CharacteristicRepositoryInterface
     */
    protected $characteristicRepository;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var TaxCalculationInterface
     */
    protected $taxCalculation;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Handler constructor.
     *
     * @param Manager $queueManager
     * @param Repository $itemRepository
     * @param ApiClient $apiClient
     * @param ProductRepository $productRepository
     * @param Config $config
     * @param StockStateInterface $stockState
     * @param MappingManager $mappingManager
     * @param FilterProvider $filterProvider
     * @param LoggerInterface $logger
     * @param CharacteristicMappingRepositoryInterface $characteristicMappingRepository
     * @param Attribute $eavAttributeResourceModel
     * @param ProductResourceModel $productResourceModel
     * @param CharacteristicRepositoryInterface $characteristicRepository
     * @param DateTime $dateTime
     * @param StoreManagerInterface $storeManager
     * @param TaxCalculationInterface $taxCalculation
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Manager $queueManager,
        Repository $itemRepository,
        ApiClient $apiClient,
        ProductRepository $productRepository,
        Config $config,
        StockStateInterface $stockState,
        MappingManager $mappingManager,
        FilterProvider $filterProvider,
        LoggerInterface $logger,
        CharacteristicMappingRepositoryInterface $characteristicMappingRepository,
        Attribute $eavAttributeResourceModel,
        ProductResourceModel $productResourceModel,
        CharacteristicRepositoryInterface $characteristicRepository,
        DateTime $dateTime,
        StoreManagerInterface $storeManager,
        TaxCalculationInterface $taxCalculation,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->queueManager = $queueManager;
        $this->itemRepository = $itemRepository;
        $this->apiClient = $apiClient;
        $this->productRepository = $productRepository;
        $this->config = $config;
        $this->stockState = $stockState;
        $this->mappingManager = $mappingManager;
        $this->templateProcessor = $filterProvider->getBlockFilter();
        $this->logger = $logger;
        $this->characteristicMappingRepository = $characteristicMappingRepository;
        $this->eavAttributeResourceModel = $eavAttributeResourceModel;
        $this->productResourceModel = $productResourceModel;
        $this->characteristicRepository = $characteristicRepository;
        $this->dateTime = $dateTime;
        $this->storeManager = $storeManager;
        $this->taxCalculation = $taxCalculation;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function handle(): bool
    {
        $item = $this->queueManager->pop();

        if (!$item) {
            return false;
        }

        try {
            $this->updateItem($item, ProductQueueItemInterface::STATE_IN_PROGRESS);

            if ($item->getAction() === ProductQueueItemInterface::ACTION_DELETE) {
                $productData = [
                    'updatedData' => [
                        'id' => $item->getProductId(),
                        'status' => 0,
                    ],
                ];
            } else {
                $product = $this->getProduct($item);
                $productData = $this->getProductData($product);
            }

            $request = new CreateRequest([$productData['updatedData']]);
            $response = $this->apiClient->sendRequest($request);

            $this->updateItem($item, null, json_encode($response));

            if (!isset($response->isError) || $response->isError) {
                throw new ApiResponseErrorException(implode(';', $response->messages ?? []));
            }

            if (isset($product) && $item->getAction() !== ProductQueueItemInterface::ACTION_DELETE) {
                $product->setData(ProductAttributes::IS_SENT, 1);
                $product->setData(ProductAttributes::SENT_DATA, json_encode($productData['completeData']));
                $this->productResourceModel->saveAttribute($product, ProductAttributes::IS_SENT);
                $this->productResourceModel->saveAttribute($product, ProductAttributes::SENT_DATA);
            }

            $this->updateItem($item, ProductQueueItemInterface::STATE_COMPLETE);
        } catch (\Throwable $exception) {
            $this->updateItem($item, ProductQueueItemInterface::STATE_FAILED, $exception->getMessage());
            $this->logger->critical($exception);
        }

        sleep(3);

        return true;
    }

    /**
     * @param Item $item
     * @param string $state
     * @param null|string $response
     * @throws \Exception
     */
    protected function updateItem(Item $item, ?string $state = null, ?string $response = null)
    {
        if (!$state && !$response) {
            return;
        }

        if ($state) {
            $item->setState($state);
        }

        if ($response) {
            $item->setResponse($response);
        }

        $this->itemRepository->save($item);
    }

    /**
     * @param Product $product
     * @return array
     * @throws MissingProductDataException
     * @throws \Exception
     */
    protected function getProductData(Product $product): array
    {
        $status = (int)($product->getStatus() == Status::STATUS_ENABLED && $product->isInStock() && $product->isSalable());

        $productData = [
            'id' => $product->getId(),
            'status' => $status,
            'name' => $product->getName(),
            'sale_price' => $this->getProductPrice($product, 'sale'),
            'vat_id' => $this->config->getVatRate(),
            'handling_time' => [
                [
                    'warehouse_id' => 1,
                    'value' => 0,
                ],
            ],
            'stock' => [
                [
                    'warehouse_id' => 1,
                    'value' => $this->stockState->getStockQty($product->getId()),
                ],
            ],
        ];

        $mappedCategory = $this->mappingManager->getProductMappedCategory($product);
        if ($status === Status::STATUS_ENABLED && !$mappedCategory) {
            throw new MissingProductDataException(__('None of this product\'s categories are mapped to an eMag category.'));
        }

        if ($mappedCategory->isEanMandatory() && !$product->getData(ProductAttributes::EAN)) {
            throw new MissingProductDataException(__('The EAN is required for the category that this product is mapped to.'));
        }

        $additionalData = $this->getAdditionalData($product, $mappedCategory);

        $completeData = array_merge($productData, $additionalData);

        if ($product->getData(ProductAttributes::IS_SENT) && $sentData = $product->getData(ProductAttributes::SENT_DATA)) {
            $sentData = json_decode($sentData, true);
            $this->filterProductData($productData, $additionalData, $sentData);
        } else {
            $productData = array_merge($productData, $additionalData);
        }

        return [
            'completeData' => $completeData,
            'updatedData' => $productData,
        ];
    }

    /**
     * @param Item $item
     * @return ProductInterface|Product
     * @throws NoSuchEntityException
     */
    protected function getProduct(Item $item)
    {
        $storeId = $this->storeManager->getDefaultStoreView() ? $this->storeManager->getDefaultStoreView()->getId() : Store::DEFAULT_STORE_ID;
        return $this->productRepository->getById($item->getProductId(), false, $storeId);
    }

    /**
     * @param Product $product
     * @param CategoryInterface $mappedCategory
     * @return array
     * @throws \Exception
     */
    protected function getAdditionalData(Product $product, CategoryInterface $mappedCategory): array
    {
        $data = [
            'part_number' => $product->getSku(),
            'url' => $product->getUrlInStore(),
            'recommended_price' => $this->getProductPrice($product, 'regular'),
            'min_sale_price' => $this->getProductPrice($product, 'min'),
            'max_sale_price' => $this->getProductPrice($product, 'max'),
            'warranty' => $product->getData(ProductAttributes::WARRANTY) ?: 0,
            'name' => $product->getName(),
            'images' => [],
            'category_id' => $mappedCategory->getEmagId(),
        ];

        if ($pnk = $product->getData(ProductAttributes::PART_NUMBER_KEY)) {
            $data['part_number_key'] = $pnk;
        }

        $data['description'] = $this->templateProcessor->filter($product->getDescription());
        $data['brand'] = $product->getData(ProductAttributes::BRAND);

        $ean = $product->getData(ProductAttributes::EAN);
        $data['ean'] = $ean ? array_map('trim', explode(',', $ean)) : [];

        $data['characteristics'] = $this->getProductCharacteristics($product);

        $images = $product->getMediaGalleryImages();

        foreach ($images as $image) {
            $data['images'][] = [
                'display_type' => 1,
                'url' => $image->getUrl(),
            ];
        }

        return $data;
    }

    /**
     * @param float $price
     * @return float
     */
    protected function formatPrice(float $price): float
    {
        return (double)number_format($price, 4, '.', '');
    }

    /**
     * @param Product $product
     * @return array
     * @throws NoSuchEntityException
     */
    protected function getProductCharacteristics(Product $product): array
    {
        $categoryMapping = $this->mappingManager->getCategoryMapping($product);
        $characteristicMappings = $this->characteristicMappingRepository->getByMapping($categoryMapping);

        $result = [];

        foreach ($characteristicMappings as $characteristicMapping) {
            $attribute = $this->eavAttributeResourceModel->load($characteristicMapping->getMagentoAttributeId());
            switch ($attribute->getFrontendInput()) {
                case 'select':
                case 'multiselect':
                    $value = $product->getAttributeText($attribute->getAttributeCode());
                    break;
                case 'boolean':
                    $value = (int)$product->getData($attribute->getAttributeCode());
                    break;
                default:
                    $value = $product->getData($attribute->getAttributeCode());
                    break;
            }

            if ($attribute && $value !== null) {
                $characteristic = $this->characteristicRepository->getById($characteristicMapping->getEmagCharacteristicId());
                $result[] = [
                    'id' => $characteristic->getEmagId(),
                    'value' => $value,
                ];
            }
        }

        return $result;
    }

    /**
     * @param array $productData
     * @param array $additionalData
     * @param array $sentData
     */
    protected function filterProductData(array &$productData, array $additionalData, array $sentData)
    {
        // remove mandatory data
        unset(
            $sentData['id'],
            $sentData['status'],
            $sentData['sale_price'],
            $sentData['vat_id'],
            $sentData['handling_time'],
            $sentData['stock']
        );

        foreach ($additionalData as $item => $value) {
            $lastSentDataValue = $sentData[$item] ?? null;

            if (is_array($value)) {
                ksort($value);

                if (is_array($lastSentDataValue)) {
                    ksort($lastSentDataValue);

                    $lastSentDataValue = md5(json_encode($lastSentDataValue));
                }

                if ($lastSentDataValue !== md5(json_encode($value))) {
                    $productData[$item] = $value;
                }
            } else {
                if ($value !== $lastSentDataValue) {
                    $productData[$item] = $value;
                }
            }
        }
    }

    /**
     * @param Product $product
     * @param string $priceType
     * @return bool|string
     */
    protected function getProductPrice(Product $product, string $priceType)
    {
        switch ($priceType) {
            case 'regular':
                $price = $this->getPriceExclTax($product, $priceType);
                break;
            case 'sale':
                $price = $this->getPriceExclTax($product, $priceType);
                break;

            case 'min':
                $price = $this->getProductPrice($product, 'sale') * (1 - $this->config->getMinSalePrice() / 100);
                break;

            case 'max':
                $price = $this->getProductPrice($product, 'sale') * (1 + $this->config->getMaxSalePrice() / 100);
                break;

            default:
                return false;
                break;
        }

        return number_format((double)$price, 4, '.', '');
    }

    /**
     * @param Product $product
     * @param string $priceType
     * @return float|int
     */
    public function getPriceExclTax(Product $product, string $priceType)
    {
        if ($taxAttribute = $product->getCustomAttribute('tax_class_id')) {
            // First get base price (=price excluding tax)
            $productRateId = $taxAttribute->getValue();

            $rate = $this->taxCalculation->getCalculatedRate($productRateId);

            if ($priceType == 'sale') {
                $productPrice = $product->getFinalPrice();
            } else {
                $productPrice = $product->getPrice();
            }

            if ((int)$this->scopeConfig->getValue(
                    'tax/calculation/price_includes_tax',
                    ScopeInterface::SCOPE_STORE) === 1
            ) {
                // Product price in catalog is including tax.
                $priceExcludingTax = $productPrice / (1 + ($rate / 100));
            } else {
                // Product price in catalog is excluding tax.
                $priceExcludingTax = $productPrice;
            }

            return $priceExcludingTax;
        }

        return (float) ($priceType == 'sale' ? $product->getFinalPrice() : $product->getPrice());
    }
}
