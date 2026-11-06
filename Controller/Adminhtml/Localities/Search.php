<?php

namespace Zitec\EmagMarketplace\Controller\Adminhtml\Localities;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Escaper;
use Psr\Log\LoggerInterface;
use Zitec\EmagMarketplace\Api\LocalityRepositoryInterface;

/**
 * Class Search
 * @package Zitec\EmagMarketplace\Controller\Adminhtml\Localities
 */
class Search extends Action
{
    /**
     * @var LocalityRepositoryInterface
     */
    protected $localityRepository;

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $allowedTerms = [
        'name'
    ];

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * Search constructor.
     *
     * @param Context $context
     * @param LoggerInterface $logger
     * @param LocalityRepositoryInterface $localityRepository
     * @param JsonFactory $jsonFactory
     * @param Escaper $escaper
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        LocalityRepositoryInterface $localityRepository,
        JsonFactory $jsonFactory,
        Escaper $escaper
    ) {
        parent::__construct($context);

        $this->localityRepository = $localityRepository;
        $this->jsonFactory = $jsonFactory;
        $this->logger = $logger;
        $this->escaper = $escaper;
    }

    /** 
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();

        $terms = [];
        foreach ($params as $key => $param) {
            if (in_array($key, $this->allowedTerms)) {
                $terms[$key] = $this->escaper->escapeXssInUrl($param);
            }
        }

        $result = $this->jsonFactory->create();

        try {
            $localities = $this->localityRepository->search($terms);
            $optionArray = [];
            if ($localities) {
                foreach ($localities as $locality) {
                    $item = [];
                    $item['id'] = $locality->getEmagId();
                    $item['text'] = $this->escaper->escapeHtml($locality->getName()) .
                        ' (' . $this->escaper->escapeHtml($locality->getRegion3()) . ', ' . $this->escaper->escapeHtml($locality->getRegion()) . ')';
                    $optionArray[] = $item;
                }
            }

            return $result->setData(['success' => true, 'message' => $optionArray]);
        } catch (\Throwable $exception) {
            $this->logger->critical($exception);

            return $result->setData([
                'success' => false,
                'message' => __('Error ocurred searching localities.' . PHP_EOL . $exception->getMessage())
            ]);
        }
    }
}
