<?php

namespace WHMCS\Module\Gateway\RTPaddle;

abstract class HTTPClient
{
    private $curl = NULL;
    private $curlOptions = [];
    protected $httpHeader = [];

    public function __construct($verbose = false)
    {
        $this->curl = curl_init();
        $this->setCurlOption(CURLOPT_RETURNTRANSFER, true);
        $this->setCurlOption(CURLOPT_VERBOSE, $verbose);
    }
    public function __destruct()
    {
        curl_close($this->curl);
    }
    protected function setCurlOption($option, $value)
    {
        $this->curlOptions[$option] = $value;
    }
    protected function getCurlOption($option)
    {
        return isset($this->curlOptions[$option]) ? $this->curlOptions[$option] : NULL;
    }
    protected function setHttpHeader($name, $value)
    {
        $this->httpHeader[] = $name . ": " . $value;
    }
    protected function get($url)
    {
        $this->setCurlOption(CURLOPT_URL, $url);
        $this->setCurlOption(CURLOPT_HTTPGET, true);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, "GET");
        return $this->executeRequest();
    }
    protected function post($url, $data = NULL)
    {
        $this->setCurlOption(CURLOPT_URL, $url);
        $this->setCurlOption(CURLOPT_POST, true);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, "POST");
        if ($data) {
            $this->setHttpHeader("Content-Type", "application/json");
            $this->setCurlOption(CURLOPT_POSTFIELDS, json_encode($data));
        }
        return $this->executeRequest();
    }
    protected function put($url, $data = NULL)
    {
        $this->setCurlOption(CURLOPT_URL, $url);
        $this->setCurlOption(CURLOPT_HTTPGET, true);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, "PUT");
        if ($data) {
            $this->setHttpHeader("Content-Type", "application/json");
            $this->setCurlOption(CURLOPT_POSTFIELDS, json_encode($data));
        }
        return $this->executeRequest();
    }
    protected function patch($url, $data = NULL)
    {
        $this->setCurlOption(CURLOPT_URL, $url);
        $this->setCurlOption(CURLOPT_HTTPGET, true);
        $this->setCurlOption(CURLOPT_CUSTOMREQUEST, "PATCH");
        $this->setHttpHeader("Content-Type", "application/json");
        if ($data) {
            $this->setCurlOption(CURLOPT_POSTFIELDS, json_encode($data));
        }
        return $this->executeRequest();
    }

    protected function executeRequest()
    {
        $this->setCurlOption(CURLOPT_HTTPHEADER, array_values($this->httpHeader));
        curl_setopt_array($this->curl, $this->curlOptions);
        $response = curl_exec($this->curl);
        $err = curl_error($this->curl);
        if ($err) {
            throw new \Exception("cURL Error #: {$err}");
        }
        $data = json_decode($response, true);

        if (isset($data) && is_array($data) && isset($data['data'])) {
            return $data['data'];
        }

        if (isset($data) && is_array($data) && isset($data['error'])) {
            throw new \Exception($data['error']['detail']);
        }

        throw new \Exception("Invalid response from API.");
    }

    protected function usdToCents($usdAmount): string
    {
        return round($usdAmount * 100);
    }

    protected function centsToUsd($centsAmount)
    {
        return $centsAmount / 100.0;
    }
}
