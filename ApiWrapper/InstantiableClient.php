<?php

namespace Zitec\EmagMarketplace\ApiWrapper;

use Zitec\EmagMarketplace\ApiWrapper\AlertManager\AlertManagerInterface;
use Zitec\EmagMarketplace\ApiWrapper\AlertManager\NullManager;
use Zitec\EmagMarketplace\ApiWrapper\Exceptions\FailedRequestException;
use Zitec\EmagMarketplace\ApiWrapper\Logger\FileLogger;
use Zitec\EmagMarketplace\ApiWrapper\Logger\LoggerInterface;
use Zitec\EmagMarketplace\ApiWrapper\Requests\AbstractRequest;
use Zitec\EmagMarketplace\ApiWrapper\Requests\Awb\ReadRequest;

/**
 * Class InstantiableClient
 * @package Zitec\EmagMarketplace\ApiWrapper
 */
class InstantiableClient
{
    /**
     * Marketplace API URL
     *
     * @var string
     */
    private $apiUrl;

    /**
     * Marketplace Username
     *
     * @var string
     */
    private $apiUsername;

    /**
     * Marketplace Password
     *
     * @var string
     */
    private $apiPassword;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AlertManagerInterface
     */
    private $alertManager;

    /**
     * @var array
     */
    private $debugInfo = [
        'site'              => null,
        'platform'          => null,
        'version'           => 0,
        'extension_version' => 0,
    ];

    /**
     * @var bool
     */
    private $arrayResponse = false;

    /**
     * Client constructor.
     *
     * @param string $apiUrl
     * @param string $apiUsername
     * @param string $apiPassword
     * @param LoggerInterface|null $logger
     * @param AlertManagerInterface|null $alertManager
     */
    public function __construct(
        $apiUrl,
        $apiUsername,
        $apiPassword,
        ?LoggerInterface $logger = null,
        ?AlertManagerInterface $alertManager = null
    ) {
        $this->apiUrl      = $apiUrl;
        $this->apiUsername = $apiUsername;
        $this->apiPassword = $apiPassword;

        $this->logger       = $logger ?: new FileLogger();
        $this->alertManager = $alertManager ?: new NullManager();
    }

    /**
     * @param AbstractRequest $request
     *
     * @return array|object
     * @throws \InvalidArgumentException
     * @throws FailedRequestException
     * @throws Exceptions\MissingEndpointException
     */
    public function sendRequest(AbstractRequest $request)
    {
        $responses = $this->sendMultiRequest([$request]);

        return array_shift($responses);
    }

    /**
     * @noinspection MoreThanThreeArgumentsInspection
     *
     * @param string $site
     * @param string $platform
     * @param int $version
     * @param int $extensionVersion
     *
     * @return $this
     */
    public function setDebugInfo($site, $platform, $version, $extensionVersion)
    {
        $this->debugInfo = [
            'site'              => $site,
            'platform'          => $platform,
            'version'           => $version,
            'extension_version' => $extensionVersion,
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getDebugInfo()
    {
        return $this->debugInfo;
    }

    /**
     * @param bool $arrayResponse
     */
    public function setArrayResponse($arrayResponse)
    {
        $this->arrayResponse = (bool)$arrayResponse;
    }

    /**
     * @return bool
     */
    public function isArrayResponse()
    {
        return $this->arrayResponse;
    }

    /**
     * @param array $requests
     * @return array|object|string
     * @throws \Exception
     */
    public function sendMultiRequest(array $requests)
    {
        $mh       = curl_multi_init();
        $channels = [];

        foreach ($requests as $key => $request) {
            if (!$request instanceof AbstractRequest) {
                curl_multi_close($mh);
                throw new \InvalidArgumentException(sprintf('The request #%d is invalid!', $key));
            }

            $hash = base64_encode($this->apiUsername . ':' . $this->apiPassword);

            $curlRequest = $this->makeCurlRequest($request, $hash);
            curl_multi_add_handle($mh, $curlRequest);
            $channels[] = $curlRequest;
        }

        $startTime = microtime(true);

        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        $executionTime = microtime(true) - $startTime;

        $responses = [];
        foreach ($channels as $key => $channel) {
            $response = curl_multi_getcontent($channel);

            /** @var AbstractRequest $request */
            $request = $requests[$key];

            try {
                $this->logRequest($request, $channel, $response, $executionTime);
            } catch (\Exception $exception) {
                curl_multi_remove_handle($mh, $channel);
                throw  $exception;
            }

            curl_multi_remove_handle($mh, $channel);

            if ($request->skipJsonDecode()) {
                $responses[] = $response;
                continue;
            }

            $response = json_decode($response, $this->isArrayResponse());

            $this->sendEmagErrorAlert($request, $response);

            $responses[] = $response;
        }

        curl_multi_close($mh);

        return $responses;

    }

    /**
     * @param AbstractRequest $request
     * @param string $hash
     *
     * @return resource
     * @throws Exceptions\MissingEndpointException
     */
    protected function makeCurlRequest(AbstractRequest $request, $hash)
    {
        $data = $this->getRequestData($request);
        $url  = $this->getRequestUrl($request);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $hash]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        return $ch;
    }

    /**
     * @param AbstractRequest $request
     *
     * @return array
     */
    protected function getRequestData(AbstractRequest $request)
    {
        $data               = $request->getRequestData(false);
        $data['debug_info'] = $this->getDebugInfo();

        return $data;
    }

    /**
     * @noinspection MoreThanThreeArgumentsInspection
     *
     * @param AbstractRequest $request
     * @param resource $ch
     * @param string $response
     * @param string $executionTime
     *
     * @throws FailedRequestException
     * @throws Exceptions\MissingEndpointException
     */
    protected function logRequest(AbstractRequest $request, $ch, $response, $executionTime)
    {
        if (!$request->skipLog()) {
            $this->logger->log(
                $this->getRequestUrl($request),
                $this->getRequestData($request),
                $response,
                $executionTime,
                curl_error($ch),
                curl_errno($ch)
            );
        }

        if ($response === false) {
            $this->alertManager->alert('curl_error', curl_error($ch), $request);

            throw new FailedRequestException(curl_error($ch), curl_errno($ch));
        }
    }

    /**
     * @param AbstractRequest $request
     * @param mixed $response
     */
    protected function sendEmagErrorAlert(AbstractRequest $request, $response)
    {
        $message = $response;
        $send    = false;

        if ($this->isArrayResponse()) {
            if ((!is_array($response) ||
                 !isset($response['isError']) ||
                 $response['isError']) &&
                isset($response['messages']) &&
                is_array($response['messages'])
            ) {
                $message = implode(', ', $response['messages']);
                $send    = true;
            }
        } else {
            if ((!is_object($response) || !property_exists($response, 'isError') || $response->isError)
                && isset($response->messages) && is_array($response->messages)
            ) {
                $message = implode(', ', $response->messages);
                $send    = true;
            }
        }

        if ($send) {
            $this->alertManager->alert('emag_error', $message, $request);
        }
    }

    /**
     * @param AbstractRequest $request
     *
     * @return string
     * @throws Exceptions\MissingEndpointException
     */
    protected function getRequestUrl(AbstractRequest $request)
    {
        $baseUrl  = $this->apiUrl;
        $endpoint = $request->getEndpoint();
        if ($request instanceof ReadRequest) {
            $baseUrl = str_replace('/api-3', '', $baseUrl);
        }

        return $baseUrl . '/' . $endpoint;
    }
}
