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

    /** @var Logger $logger */
    protected $logger;

    /**
     * DiscourseApi constructor.
     *
     * @param string  $address Discourse instance net address
     * @param string  $apiKey  API key
     * @param Logger $logger  Logger instance
     */
    public function __construct($address, $apiKey, Logger $logger)
    {
        $this->address = $address;
        $this->apiKey = $apiKey;
        $this->logger = $logger;
    }

    /**
     * Performs API request to Discourse instance.
     *
     * @param string $method        API method to request
     * @param array  $data          Data to send in request
     * @param string $requestMethod Request method (get, post)
     * @param string $username      Username to perform request with
     *
     * @return array Decoded and raw response
     * @throws \Exception
     */
    public function request($method, $data = [], $requestMethod = 'post', $username = 'system')
    {
        if (++$this->callCount % 5 === 0) {
            $this->logger->add("...lounge music plays...");
            sleep($this->loungeDelay);
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
        ]);

        if ($requestMethod === 'post'
            || $requestMethod == 'put'
        ) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        $errorNumber = curl_errno($ch);
        $errorMessage = curl_exec($ch);
        curl_close($ch);

        if ($errorNumber) {
            throw new \Exception($errorMessage, $errorNumber);
        }

        $responseDecoded = json_decode($response, true);

        if ($responseDecoded === null) {
            throw new \Exception($response);
        }

        if (!empty($responseDecoded['errors'])) {
            $errorText = $this->getErrors($responseDecoded['errors']);
            throw new \Exception($errorText);
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
            rtrim($this->address, '/'),
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