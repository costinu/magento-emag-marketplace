<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Localities;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Throwable;
use Zitec\EmagMarketplace\Api\LocalityRepositoryInterface;

/**
 * Class Import
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Localities
 */
class Import extends Action
{
    const ADMIN_RESOURCE = 'Zitec_EmagMarketplace::localities';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CountRequest
     */
    protected $countRequest;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var array
     */
    protected $localities;

    /**
     * @var LocalityRepositoryInterface
     */
    protected $localityRepository;

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * Import constructor.
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param LocalityRepositoryInterface $localityRepository
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        LocalityRepositoryInterface $localityRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);

        $this->logger             = $logger;
        $this->localityRepository = $localityRepository;
        $this->jsonFactory        = $jsonFactory;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $this->localities = $this->localityRepository->fetchData();
            
            $this->localityRepository->updateData($this->localities);
            return $result->setData(['success' => true, 'message' => __('Localities imported successfully.')]);
        } catch (Throwable $exception) {
            $this->logger->critical($exception);
            return $result->setData([
                'success' => false,
                'message' => __('Error ocurred importing localities.' . PHP_EOL . $exception->getMessage()),
            ]);
        }
    }
}
