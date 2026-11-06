<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\CharacteristicRepositoryInterface;

/**
 * Class Characteristicsvalues
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Localities
 */
class Characteristicsvalues extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::mapping';

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CharacteristicRepositoryInterface
     */
    protected $characteristicRepository;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * Characteristicsvalues constructor.
     * @param Context $context
     * @param LoggerInterface $logger
     * @param CharacteristicRepositoryInterface $characteristicRepository
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        CharacteristicRepositoryInterface $characteristicRepository,
        JsonFactory $jsonFactory,
        Escaper $escaper
    ) {
        parent::__construct($context);
        $this->escaper = $escaper;

        $this->jsonFactory = $jsonFactory;
        $this->logger = $logger;
        $this->characteristicRepository = $characteristicRepository;
    }

    /**
     * @return $this
     * @throws \InvalidArgumentException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $characteristicId = $this->getRequest()->getParam('characteristic_id', 0);

            if (!$characteristicId) {
                throw new \InvalidArgumentException(__('Missing characteristic Id.'));
            }

            $characteristic = $this->characteristicRepository->getById($characteristicId);

            if (!$characteristic || !$characteristic->getId()) {
                throw new NoSuchEntityException(__('Could not load characteristic by id %1', $characteristicId));
            }

            $results = __('No values found');

            if ($values = $characteristic->getValues()) {
                // Shown as HTML in the admin modal; the values come from eMAG.
                $valuesArray = (array)json_decode($values, true);
                $results = implode('<br />', array_map(
                    fn ($value) => $this->escaper->escapeHtml(is_scalar($value) ? (string)$value : json_encode($value)),
                    $valuesArray
                ));
            }



            return $result->setData([
                'success' => true,
                'message' => $results,
            ]);

        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'message' => $this->escaper->escapeHtml(
                    __('There has been a problem in retrieving characteristic values. %1', $e->getMessage())
                ),
            ]);
        }
    }
}
