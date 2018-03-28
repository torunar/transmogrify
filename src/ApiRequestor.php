<?php

namespace Transmogrify;

class ApiRequestor
{
    /** @var string $address */
    protected $address;

    /** @var string $apiKey */
    protected $apiKey;

    /** @var int $loungeDelay */
    protected $loungeDelay = 15;

    /** @var int $callCount */
    protected $callCount = 0;

    /** @var string $defaultUsername */
    protected $defaultUsername = 'system';

    /** @var int $batchSize */
    protected $batchSize = 10;

    /** @var int $requestTimeout */
    protected $requestTimeout = 30;

    /**
     * DiscourseApi constructor.
     *
     * @param string $address Discourse instance net address
     * @param string $apiKey  API key
     */
    public function __construct($address, $apiKey)
    {
        $this->address = $address;
        $this->apiKey = $apiKey;
    }

    /**
     * Performs API request to Discourse instance.
     *
     * @param string      $method        API method to request
     * @param array       $data          Data to send in request
     * @param string      $requestMethod Request method (get, post)
     * @param string|null $username      Username to perform request with.
     *                                   null to use default username
     *
     * @return array Decoded and raw response
     * @throws \Transmogrify\ApiException
     */
    public function request($method, $data = [], $requestMethod = 'post', $username = null)
    {
        if (++$this->callCount % $this->batchSize === 0) {
            sleep($this->loungeDelay);
        }

        if ($username === null) {
            $username = $this->defaultUsername;
        }

        $url = $this->getUrl($method, $username);

        if ($requestMethod === 'get' && $data) {
            $url .= '&' . http_build_query($data);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_SAFE_UPLOAD    => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($requestMethod),
            CURLOPT_TIMEOUT        => $this->requestTimeout,
        ]);

        if ($requestMethod === 'post'
            || $requestMethod == 'put'
        ) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $errorNumber = curl_errno($ch);
        $errorMessage = curl_error($ch);
        curl_close($ch);

        $debugData = [
            'method' => $requestMethod,
            'url'    => $url,
            'data'   => $data,
        ];

        if ($errorNumber) {
            $exception = new ApiException($errorMessage, $errorNumber);
            $exception->setData($debugData);
            throw $exception;
        }

        $responseDecoded = json_decode($response, true);

        if ($responseDecoded === null) {
            $exception = new ApiException($response);
            $exception->setData($debugData);
            throw $exception;
        }

        if (!empty($responseDecoded['errors'])) {
            $errorText = $this->getErrors($responseDecoded['errors']);
            $exception = new ApiException($errorText);
            $exception->setData($debugData);
            throw $exception;
        }

        return array($responseDecoded, $response);
    }

    /**
     * Gets API method URL.
     *
     * @param string $method   API method to request
     * @param string $username Username to perform request with
     *
     * @return string
     */
    protected function getUrl($method, $username)
    {
        return sprintf(
            '%s/%s.json?api_username=%s&api_key=%s',
            $this->address,
            $method,
            $username,
            $this->apiKey
        );
    }

    /**
     * Parses response for errors.
     *
     * @param array $errors
     *
     * @return string
     */
    protected function getErrors($errors)
    {
        $errorText = '';

        foreach ($errors as $fieldName => $fieldError) {
            $errorText = $fieldError;
            if (is_array($fieldError)) {
                $errorText = '';
                foreach ($fieldError as $errorMessage) {
                    $errorText .= sprintf("%s: %s\n", $fieldName, $errorMessage);
                }
            }
        }

        return $errorText;
    }
}
