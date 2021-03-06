<?php

namespace Curl;

use Exception;

/**
 * Curl wrapper
 * Class Curl
 * @package Curl
 *
 * @author Arslan Iftikhar <arslaniftikhar16@gmail.com>
 */
class Curl
{
    const VERSION = 1.0;

    /**
     * Custom headers for the current request
     * @var array
     */
    public $headers = [];

    /**
     * An associative array of CURLOPT options to send along with requests
     * @var array
     */
    public $options = [];

    /**
     * Holds the error of the last request
     * @var string
     */
    protected $error = '';

    /**
     * Stores resource handle for the current CURL request
     * @var resource
     */
    protected $request;

    /**
     * Response body
     * @var
     */
    public $body;

    /**
     * Response header
     * @var array
     */
    public $response_header = [];

    /**
     * User agent for the current request
     * @var string
     */
    public $user_agent;

    /**
     * User agent for the current request
     * @var string
     */
    public $response_info = [];


    /**
     * Curl constructor.
     */
    public function __construct()
    {
        $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'arslaniftikhar/curl ' . PHP_VERSION . ' (https://github.com/arslaniftikhar/curl)';
    }

    /**
     * Makes an HTTP GET request to the specified $url with an optional array or string of $vars
     *
     * Returns a CurlResponse object if the request was successful, false otherwise
     *
     * @param string $url
     * @param array $payload
     * @return CurlResponse
     * @throws Exception
     */
    function get($url, $payload = [])
    {
        if (!empty($payload)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= (is_string($payload)) ? $payload : http_build_query($payload, '', '&');
        }
        return $this->request('GET', $url);
    }

    /**
     * Makes an HTTP POST request to the specified $url with an optional array or string of $vars
     *
     * @param string $url
     * @param array $payload
     * @return CurlResponse|boolean
     * @throws Exception
     */
    function post($url, $payload = [])
    {
        return $this->request('POST', $url, $payload);
    }

    /**
     * Makes an HTTP PUT request to the specified $url with an optional array or string of $vars
     *
     * Returns a CurlResponse object if the request was successful, false otherwise
     *
     * @param string $url
     * @param array $payload
     * @return CurlResponse|boolean
     * @throws Exception
     */
    function put($url, $payload = [])
    {
        return $this->request('PUT', $url, $payload);
    }

    /**
     * Makes an HTTP request of the specified $method to a $url with an optional array or string of $payload
     * Returns a CurlResponse object if the request was successful, false otherwise
     *
     * @param $method
     * @param $url
     * @param array $payload
     * @return bool|CurlResponse|string
     * @throws Exception
     */
    public function request($method, $url, $payload = [])
    {
        $this->error = '';
        $this->request = curl_init();

        if (is_array($payload))
            $payload = http_build_query($payload, '', '&');
        $this->setMethod($method);
        $this->setOptions($url, $payload);
        $this->setHeader();
        $response = curl_exec($this->request);

        $curl_info = curl_getinfo($this->request);
        $errno = curl_errno($this->request);
        $error = curl_error($this->request);
        curl_close($this->request);

        if ($errno) {
            $this->error = "$errno - $error";
            throw new Exception($this->error);
        }
        if ($response) {
            $response = new CurlResponse($response, $curl_info);
            $this->body = $response->body;
            $this->response_header = $response->headers;
        }
        return $response;
    }

    /**
     * Set the custom headers to the current request.
     */
    protected function setHeader()
    {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);

    }

    /**
     * Set the method to the current request.
     * @param string $method
     * @return void
     */
    protected function setMethod($method)
    {
        switch (strtoupper($method)) {
            case 'HEAD':
                curl_setopt($this->request, CURLOPT_NOBODY, true);
                break;
            case 'GET':
                curl_setopt($this->request, CURLOPT_HTTPGET, true);
                break;
            default:
                curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    /**
     * Set the associated CURL options for the request method
     * @param $url
     * @param $payload
     */
    protected function setOptions($url, $payload)
    {
        curl_setopt($this->request, CURLOPT_URL, $url);
        if (!empty($payload)) curl_setopt($this->request, CURLOPT_POSTFIELDS, $payload);

        curl_setopt($this->request, CURLOPT_HEADER, true);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->request, CURLOPT_USERAGENT, $this->user_agent);
        curl_setopt($this->request, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($this->request, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($this->request, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($this->request, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($this->request, CURLOPT_TIMEOUT, 30);

        // Set any custom CURL options
        foreach ($this->options as $option => $value) {
            curl_setopt($this->request, constant('CURLOPT_' . str_replace('CURLOPT_', '', strtoupper($option))), $value);
        }
    }
}

/**
 * Parses the response from a Curl request into an object containing
 * the response body and an associative array of headers
 *
 * @package curl
 * @author Arslan Iftikhar <arslaniftikhar16@gmail.com>
 **/
class CurlResponse
{

    /**
     * The body of the response without the headers block
     *
     * @var string
     **/
    public $body = '';

    /**
     * An associative array containing the response's headers
     *
     * @var array
     **/
    public $headers = [];

    /**
     * Curl response info
     *
     * @var array
     **/
    private $curlInfo = [];

    /**
     * Accepts the result of a curl request as a string
     *
     * <code>
     * $response = new CurlResponse(curl_exec($curl_handle));
     * echo $response->body;
     * echo $response->headers['Status'];
     * </code>
     *
     * @param string $response
     * @param array $curlInfo
     */
    function __construct($response, $curlInfo = [])
    {
        $headerSize = $curlInfo["header_size"];
        $headers_string = substr($response, 0, $headerSize);

        # Set body from the response body
        $this->body = substr($response, $headerSize);

        $headers = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($headers_string));

        $lastHeaderLine = array_pop($headers);

        $headerLines = preg_split("/\r\n|\n|\r/", $lastHeaderLine);

        $versionAndStatus = explode(' ', trim(array_shift($headerLines)), 3);

        $this->headers['Http-Version'] = $versionAndStatus[0];
        $this->headers['Status-Code'] = $versionAndStatus[1];
        $this->headers['Status'] = $versionAndStatus[0] . ' ' . $versionAndStatus[1];

        # Convert headers into an associative array
        foreach ($headerLines as $header) {
            preg_match('#(.*?)\:\s(.*)#', $header, $matches);
            $this->headers[$matches[1]] = $matches[2];
        }
    }

    /**
     * Returns the response body
     *
     * <code>
     * $curl = new Curl;
     * $response = $curl->get('google.com');
     * echo $response;  # => echo $response->body;
     * </code>
     *
     * @return string
     **/
    function __toString()
    {
        return $this->body;
    }

}