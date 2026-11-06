<?php

namespace Zitec\EmagMarketplace\Block\Adminhtml\Mapping\Edit\Form;

use Magento\Backend\Block\Template;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\CategoryFactory as MagentoCategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\TreeFactory;
use Magento\Framework\Data\Tree\Node;
use Magento\Framework\Data\Tree\Node\Collection as NodeCollection;
use Zitec\EmagMarketplace\Api\CategoryMappingRepositoryInterface;
use Zitec\EmagMarketplace\Api\CategoryRepositoryInterface as EmagCategoryRepository;
use Zitec\EmagMarketplace\Api\Data\CategoryInterface;
use Zitec\EmagMarketplace\Model\ResourceModel\Category\Collection;

/**
 * Class Category
 * @package Zitec\EmagMarketplace\Block\Adminhtml\Mapping\Edit\Form
 */
class Category extends Template
{
    /**
     * @var EmagCategoryRepository
     */
    protected $emagCategoryRepository;

    /**
     * @var MagentoCategoryFactory
     */
    protected $magentoCategoryFactory;

    /**
     * @var CategoryMappingRepositoryInterface
     */
    protected $categoryMappingRepository;

    /**
     * @var TreeFactory
     */
    protected $treeFactory;

    /**
     * Category constructor.
     * @param Template\Context $context
     * @param EmagCategoryRepository $emagCategoryRepository
     * @param MagentoCategoryFactory $categoryFactory
     * @param CategoryMappingRepositoryInterface $categoryMappingRepository
     * @param TreeFactory $treeFactory
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        EmagCategoryRepository $emagCategoryRepository,
        MagentoCategoryFactory $categoryFactory,
        CategoryMappingRepositoryInterface $categoryMappingRepository,
        TreeFactory $treeFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->emagCategoryRepository = $emagCategoryRepository;
        $this->magentoCategoryFactory = $categoryFactory;
        $this->categoryMappingRepository = $categoryMappingRepository;
        $this->treeFactory = $treeFactory;
    }

    /**
     * @return CategoryInterface[]|Collection
     */
    public function getEmagCategories()
    {
        return $this->emagCategoryRepository->getAll();
    }

    /**
     * @return CategoryModel[]
     */
    public function getMagentoCategories(): array
    {
        $options = [];

        try {
            $mappedCategoriesIds = $this->categoryMappingRepository->getAll()->getColumnValues('magento_category_id');

            /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categories */
            $rootCategoryId = $this->_storeManager->getStore()->getRootCategoryId();

            /* @var $tree \Magento\Catalog\Model\ResourceModel\Category\Tree */
            $tree = $this->treeFactory->create();
            $categories = $tree->loadNode($rootCategoryId)->loadChildren(0)->getChildren();
            $tree->addCollectionData(null, true, $rootCategoryId);

            /** @var Node $node */
            foreach ($categories as $node) {
                $this->addOption($node, $mappedCategoriesIds, $options);

                $this->getChildren($node->getChildren(), $options, $mappedCategoriesIds);
            }
        } catch (\Throwable $exception) {
            $this->_logger->critical($exception);
        }

        return $options;
    }

    /**
     * @param NodeCollection|Node[]|NodeCollection $nodes
     * @param array $options
     * @param array $mappedCategoriesIds
     */
    protected function getChildren($nodes, array &$options, array $mappedCategoriesIds)
    {
        /** @var Node $node */
        foreach ($nodes as $node) {
            $this->addOption($node, $mappedCategoriesIds, $options, true);

            $this->getChildren($node->getChildren(), $options, $mappedCategoriesIds);
        }
    }

    /**
     * @param Node $node
     * @param array $mappedCategoriesIds
     * @param array $options
     * @param bool $withPrefix
     */
    protected function addOption(
        Node $node,
        array $mappedCategoriesIds,
        array &$options,
        bool $withPrefix = false
    ) {
        $repeater = "\u{00A0}\u{00A0}"; // non-breaking spaces (the label is escaped in the template)

        $options[] = [
            'label' => ($withPrefix ? str_repeat($repeater, $node->getLevel() * 1) : null) . $node->getName(),
            'value' => $node->getId(),
            'disabled' => in_array($node->getId(), $mappedCategoriesIds),
        ];
    }

}
