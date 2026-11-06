<?php

namespace Zitec\EmagMarketplace\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Zitec\EmagMarketplace\Api\Data\LocalityInterface;
use Zitec\EmagMarketplace\Api\LocalityRepositoryInterface;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Locality\ReadRequest;
use Zitec\EmagMarketplace\Model\ResourceModel\Locality;
use Zitec\EmagMarketplace\Model\ResourceModel\Locality\Collection;
use Zitec\EmagMarketplace\Model\ResourceModel\Locality\CollectionFactory;

/**
 * Class LocalityRepository
 * @package Zitec\EmagMarketplace\Model
 */
class LocalityRepository implements LocalityRepositoryInterface
{
    /**
     * @var LocalityFactory
     */
    protected $localityFactory;

    /**
     * @var CollectionFactory
     */
    protected $localityCollectionFactory;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @var DateTime
     */
    protected $date;

    /**
     * @var ResourceModel\Locality
     */
    protected $localityResource;

    /**
     * LocalityRepository constructor.
     * @param LocalityFactory $localityFactory
     * @param CollectionFactory $localityCollectionFactory
     * @param ApiClient $apiClient
     * @param DateTime $date
     * @param Locality $localityResource
     */
    public function __construct(
        LocalityFactory $localityFactory,
        CollectionFactory $localityCollectionFactory,
        ApiClient $apiClient,
        DateTime $date,
        Locality $localityResource
    ) {
        $this->localityFactory = $localityFactory;
        $this->localityCollectionFactory = $localityCollectionFactory;
        $this->apiClient = $apiClient;
        $this->date = $date;
        $this->localityResource = $localityResource;
    }

    /**
     * {@inheritdoc}
     */
    public function updateData(array $data): bool
    {
        $items = [];

        foreach ($data as $result) {
            $importedEmagIds[] = $result['emag_id'];
            $items[] = $this->processData($result);
        }

        $this->updateLocalitiesData($items);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function search(array $terms): Collection
    {
        $localityCollection = $this->localityCollectionFactory->create();

        if ($terms) {
            foreach ($terms as $term => $value) {
                $localityCollection->addFieldToFilter($term, ['like' => '%' . $value . '%']);
            }
        }

        return $localityCollection;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function processData(array $data): array
    {
        return [
            'emag_id' => $data['emag_id'],
            'name' => $data['name_latin'],
            'region3' => $data['region3'],
            'region' => $data['region2_latin'],
            'created_at' => $this->date->gmtDate(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchData(): array
    {
        $this->apiClient->setArrayResponse(true);

        $localities = [];
        $i = 1;

        do {
            unset($results);

            $request = new ReadRequest([
                'currentPage' => $i,
                'itemsPerPage' => self::LOCALITIES_PER_PAGE,
            ]);

            $timeStart = microtime(true);
            $results = $this->apiClient->sendRequest($request);

            if (isset($results['results']) && count($results['results'])) {
                $localities = array_merge($localities, $results['results']);
            }

            $i++;

            $timeEnd = microtime(true);
            $time = $timeEnd - $timeStart;
            $sleepTime = 3 - $time;

            if ($sleepTime > 0) {
                sleep($sleepTime);
            }
        } while (isset($results['results']) && count($results['results']));

        unset($results);

        return $localities;
    }

    /**
     * {@inheritdoc}
     */
    public function updateLocalitiesData(array $data): void
    {
        $this->localityResource->updateLocalitiesData($data);
    }

    /**
     * {@inheritDoc}
     */
    public function getByEmagId(int $emagId): Collection
    {
        return $this->localityCollectionFactory->create()
            ->addFieldToFilter(LocalityInterface::EMAG_ID, $emagId);
    }
}
