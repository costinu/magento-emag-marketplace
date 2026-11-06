<?php

namespace Zitec\EmagMarketplace\Observer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\CharacteristicMappingRepositoryInterface;
use Zitec\EmagMarketplace\Api\CharacteristicRepositoryInterface;
use Zitec\EmagMarketplace\Model\AttributeText;
use Zitec\EmagMarketplace\Model\Json;
use Zitec\EmagMarketplace\Model\MappingManager;
use Zitec\EmagMarketplace\Model\ProductAttributes;

/**
 * Class VerifyProductAllowedValues
 * @package Zitec\EmagMarketplace\Observer
 */
class VerifyProductAllowedValues implements ObserverInterface
{
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
     * @var MappingManager
     */
    protected $mappingManager;

    /**
     * @var CharacteristicMappingRepositoryInterface
     */
    protected $characteristicMappingRepository;

    /**
     * @var Attribute
     */
    protected $eavAttributeResourceModel;

    /**
     * @var CharacteristicRepositoryInterface
     */
    protected $characteristicRepository;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var AttributeText
     */
    protected $attributeText;

    /**
     * VerifyProductAllowedValues constructor.
     * @param ManagerInterface $messageManager
     * @param State $state
     * @param LoggerInterface $logger
     * @param MappingManager $mappingManager
     * @param CharacteristicMappingRepositoryInterface $characteristicMappingRepository
     * @param Attribute $eavAttributeResourceModel
     * @param CharacteristicRepositoryInterface $characteristicRepository
     * @param Registry $registry
     * @param AttributeText $attributeText
     */
    public function __construct(
        ManagerInterface $messageManager,
        State $state,
        LoggerInterface $logger,
        MappingManager $mappingManager,
        CharacteristicMappingRepositoryInterface $characteristicMappingRepository,
        Attribute $eavAttributeResourceModel,
        CharacteristicRepositoryInterface $characteristicRepository,
        Registry $registry,
        AttributeText $attributeText
    ) {
        $this->attributeText = $attributeText;
        $this->messageManager = $messageManager;
        $this->state = $state;
        $this->logger = $logger;
        $this->mappingManager = $mappingManager;
        $this->characteristicMappingRepository = $characteristicMappingRepository;
        $this->eavAttributeResourceModel = $eavAttributeResourceModel;
        $this->characteristicRepository = $characteristicRepository;
        $this->registry = $registry;
    }

    /**
     * @param Observer $observer
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        try {
            $areaCode = $this->state->getAreaCode();
        } catch (LocalizedException $exception) {
            $areaCode = null;
        }

        if ($this->registry->registry('IMPORTING_STATUS')) {
            return;
        }

        try {
            $product = $observer->getProduct();
        } catch (\Throwable $exception) {
            $this->logger->critical($exception);

            return;
        }

        if (!$product || empty($product)) {
            return;
        }

        if (!$product->getData(ProductAttributes::IS_VISIBLE)) {
            return;
        }

        $categoryMapping = $this->mappingManager->getCategoryMapping($product);

        if (!$categoryMapping) {
            $this->messageManager->addErrorMessage(__('Product is not assigned to an eMAG mapped Category.'));
            $this->messageManager->addErrorMessage(__('It can not be published in eMAG Marketplace.'));
            $this->messageManager->addWarningMessage(__('The product has not been set as "Visible in eMAG Marketplace".'));
            throw new LocalizedException(__('Please correct this issue and set "Visible in eMAG Marketplace" to "Yes" and save the product again in order to publish this product in eMAG Marketplace.'));
        }

        $characteristicMappings = $this->characteristicMappingRepository->getByMapping($categoryMapping);

        foreach ($characteristicMappings as $characteristicMapping) {
            $attribute = $this->eavAttributeResourceModel->load($characteristicMapping->getMagentoAttributeId());
            switch ($attribute->getFrontendInput()) {
                case 'select':
                case 'multiselect':
                    $value = $this->attributeText->get($product, $attribute->getAttributeCode());
                    break;
                case 'boolean':
                    $value = (int)$product->getData($attribute->getAttributeCode());
                    break;
                default:
                    $value = $product->getData($attribute->getAttributeCode());
                    break;
            }

            $characteristic = $this->characteristicRepository->getById($characteristicMapping->getEmagCharacteristicId());

            if ($attribute && $value !== null) {

                $values = $characteristic->getValues();

                if (!$values || $characteristic->getAllowNewValue()) {
                    continue;
                }

                $characteristicValues = [];

                if ($values) {
                    $characteristicValues = Json::json_decode($values);
                }

                if (!in_array($value, $characteristicValues)) {
                    if ($product->getData(ProductAttributes::IS_VISIBLE) !== $product->getOrigData(ProductAttributes::IS_VISIBLE)) {
                        $this->messageManager->addWarningMessage(__('It can not be published in eMAG Marketplace.'));
                        $this->messageManager->addWarningMessage(__('The product has not been set as "Visible in eMAG Marketplace".'));
                        $this->messageManager->addErrorMessage(__('Please correct this issue and set "Visible in eMAG Marketplace" to "Yes" and save the product again in order to publish this product in eMAG Marketplace.'));
                    }

                    $this->messageManager->addWarningMessage(__('Allowed values:'));
                    $this->messageManager->addWarningMessage(implode(', ', $characteristicValues));

                    throw new LocalizedException(
                        __(
                            'Value is not allowed for characteristic %1, mapped to %2 attribute.',
                            str_replace(':', '', $characteristic->getName()),
                            $attribute->getDefaultFrontendLabel()
                        )
                    );
                }
            } elseif ($characteristic->isMandatory()) {
                throw new LocalizedException(
                    __(
                        'Characteristic %1 is mandatory. Please verify characteristic mapping.',
                        $characteristic->getName()
                    )
                );
            }
        }
    }
}
