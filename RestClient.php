<?php

/**
 * This library provides rest client functions.
 *
 * @author Shaun Burdick <github@shaunburdick.com>
 * @see    http://github.com/shaunburdick/rest-client
 */
class RestClient
{
    const REST_GET      = 'GET';
    const REST_POST     = 'POST';
    const REST_PUT      = 'PUT';
    const REST_DELETE   = 'DELETE';

    /** @var integer connection handle */
    protected $conn;

    /** @var array currently set options */
    protected $opts = array();

    /** @var array currently set headers */
    protected $headers = array();

    /** @var mixed the body of the last call */
    protected $lastBody;

    /** @var array a list of cookies */
    protected $cookieJar = array();

    /** @var boolean Automatically execute the rest call */
    protected $autoExecute = true;

    /**
     * Construct Client.
     *
     * @return null
     * @throws Exception when it cannot create cURL connection
     */
    public function __construct()
    {
        $this->conn = curl_init();

        if ($this->conn === false) {
            throw new Exception('Unable to create cURL connection');
        }

        // Set Default Options
        $this->setRawOptionArray(
            array(
                // include headers in output
                CURLINFO_HEADER_OUT     => true,

                // returns the output instead of status
                CURLOPT_RETURNTRANSFER  => true
            )
        );
    }

    /**
     * Destruct class.
     *
     * @return null
     */
    public function __destruct()
    {
        if ($this->conn) {
            curl_close($this->conn);
        }
    }

    /**
     * Return connection handle.
     *
     * @return object The connection handle
     */
    public function getHandle()
    {
        return $this->conn;
    }

    /**
     * Set the auto execute value.
     *
     * @param boolean $exec True to auto execute, false to not
     * @return RestClient
     */
    public function autoExecute($exec)
    {
        $this->autoExecute = (boolean) $exec;

        return $this;
    }

    /**
     * Set the timeout value.
     *
     * @param integer $timeout The timeout in seconds
     * @return boolean True on success, False on failure
     */
    public function setTimeout($timeout)
    {
        return $this->setRawOption(CURLOPT_TIMEOUT, $timeout);
    }

    /**
     * Set username and password for basic auth.
     *
     * @param string $user Username
     * @param string $pass Password
     * @return boolean True on success, False on failure
     */
    public function basicAuth($user, $pass)
    {
        return $this->setRawOption(CURLOPT_USERPWD, "{$user}:{$pass}");
    }

    /**
     * Get URL if set.
     *
     * @return string The URL of the call or empty string
     */
    public function getUrl()
    {
        return isset($this->opts[CURLOPT_URL]) ? $this->opts[CURLOPT_URL] : '';
    }

    /**
     * Set Headers.
     *
     * @param string[] $headers   an array of headers
     * @param boolean  $overwrite replace headers or add to existing headers
     * @return boolean True on success, False on failure
     */
    public function setHeaders($headers, $overwrite = true)
    {
        if (is_array($headers)) {
            if ($overwrite) {
                $this->headers = $headers;
                return $this->setRawOption(CURLOPT_HTTPHEADER, $headers);
            } else {
                $result = true;
                foreach ($headers as $header) {
                    $result &= $this->addHeader($header);
                }

                return $result;
            }
        }

        return false;
    }

    /**
     * Add a single header.
     *
     * @param string $header a header
     * @return boolean True on success, False on failure
     */
    public function addHeader($header)
    {
        if (is_string($header)) {
            if (!in_array($header, $this->headers)) {
                $this->headers[] = $header;
                return $this->setRawOption(CURLOPT_HTTPHEADER, $this->headers);
            } else {
                // Header is already there
                return true;
            }
        }

        return false;
    }

    /**
     * Get any set headers.
     *
     * @return string[]
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Sets a raw option.
     *
     * @param string $opt The name of the option
     * @param mixed  $val The value for the option
     * @return boolean True on success, False on failure
     */
    public function setRawOption($opt, $val)
    {
        if ($this->conn) {
            if (curl_setopt($this->conn, $opt, $val)) {
                $this->opts[$opt] = $val;
                return true;
            }
        }

        return false;
    }

    /**
     * Sets an array of options.
     *
     * @param mixed[] $opts An array of options in a key => value pairing
     * @return boolean True on success, False on failure
     */
    public function setRawOptionArray($opts)
    {
        if ($this->conn) {
            if (curl_setopt_array($this->conn, $opts)) {
                $this->opts = $opts;
            }
        }

        return false;
    }

    /**
     * Get any set options.
     *
     * @return mixed[]
     */
    public function getOpts()
    {
        return $this->opts;
    }

    /**
     * Get cookies.
     *
     * @return string[] an array of cookies by name
     */
    public function getCookies()
    {
        return $this->cookieJar;
    }

    /**
     * Adds/Updates a cookie.
     *
     * @param string $name  the name of the cookie
     * @param string $value the value of the cookie
     * @return null
     */
    public function setCookie($name, $value)
    {
        $this->cookieJar[$name] = $value;
    }

    /**
     * Removes a cookie.
     *
     * @param string $name the name of the cookie
     * @return boolean True if cookie removed or false if cookie doesn't exist
     */
    public function deleteCookie($name)
    {
        if (isset($this->cookieJar[$name])) {
            unset($this->cookieJar[$name]);
            return true;
        }

        return false;
    }

    /**
     * Encodes cookies into string.
     *
     * @return string the encoded cookie string
     */
    public function encodeCookies()
    {
        $retVal = '';

        if (!empty($this->cookieJar)) {
            foreach ($this->cookieJar as $name => $val) {
                $name = $this->encodeString($name);
                $val = $this->encodeString($val);
                $retVal .= "{$name}={$val}; ";
            }
            $retVal = rtrim($retVal, "; ");

            return $retVal;
        }

        return $retVal;
    }

    /**
     * Make a GET Call.
     *
     * @param string $path   The url path
     * @param mixed  $params Any get params to add
     * @param boolean $decode json decode the response
     * @return array|boolean array of headers and results or false on failure
     */
    public function get($path, $params = null, $decode = false)
    {
        return $this->http(self::REST_GET, $path, $params, $decode);
    }

    /**
     * Make a POST Call.
     *
     * @param string $path   The url path
     * @param mixed  $params Any post params to add
     * @param boolean $decode json decode the response
     * @return array|boolean array of headers and results or false on failure
     */
    public function post($path, $params, $decode = false)
    {
        return $this->http(self::REST_POST, $path, $params, $decode);
    }

    /**
     * Make a PUT Call.
     *
     * @param string $path   The url path
     * @param mixed  $params Any put params to add
     * @param boolean $decode json decode the response
     * @return array|boolean array of headers and results or false on failure
     */
    public function put($path, $params, $decode = false)
    {
        return $this->http(self::REST_PUT, $path, $params, $decode);
    }

    /**
     * Make a DELETE Call.
     *
     * @param string $path   The url path
     * @param mixed  $params Any delete params to add
     * @param boolean $decode json decode the response
     * @return array|boolean array of headers and results or false on failure
     */
    public function delete($path, $params = null, $decode = false)
    {
        return $this->http(self::REST_DELETE, $path, $params, $decode);
    }

    /**
     * Make a REST Call.
     *
     * @param string  $action see REST_ constants
     * @param string  $path   url path
     * @param mixed   $body   the request body, if GET this will get encoded and added to the $path
     * @param boolean $decode json decode the response
     * @return array|boolean array of headers and results or True if autoexec is off, false on failure
     */
    public function http($action, $path, $body = null, $decode = false)
    {
        $retVal = false;

        if (is_array($body) || is_object($body)) {
            $body = http_build_query($body);
        }

        // Add any cookies
        $encCookies = $this->encodeCookies();
        if (!empty($encCookies)) {
            $this->setRawOption(CURLOPT_COOKIE, $encCookies);
        }

        $this->lastBody = $body;

        if ($this->setRawOption(CURLOPT_URL, $path)) {
            switch($action) {
                case self::REST_DELETE:
                    $this->setRawOption(CURLOPT_CUSTOMREQUEST, "DELETE");
                    //No Break
                case self::REST_GET:
                    if (!empty($body)) {
                        if (is_array($body) || is_object($body)) {
                            $body = http_build_query($body);
                        }

                        // Add GET parameters
                        if (strpos('?', $path) === false) {
                            $path .= '?';
                        }

                        $path .= $body;
                    }

                    $this->setRawOption(CURLOPT_URL, $path);
                    $retVal = ($this->autoExecute) ? $this->exec($decode) : true;
                    break;
                case self::REST_PUT:
                    $this->setRawOption(CURLOPT_CUSTOMREQUEST, "PUT");
                    //No Break
                case self::REST_POST:
                    $this->setRawOption(CURLOPT_POST, true);
                    $this->setRawOption(CURLOPT_POSTFIELDS, $body);
                    $retVal = ($this->autoExecute) ? $this->exec($decode) : true;
                    break;
            }
        }

        return $retVal;
    }

    /**
     * Execute and return results.
     *
     * @param boolean $decode Flag to json_decode the response
     * @return array ('info' => array(), 'response' => array()|string)
     */
    public function exec($decode = false)
    {
        $retVal = array('info' => array(), 'response' => array());

        $resp = curl_exec($this->conn);
        $info = curl_getinfo($this->conn);

        // Helpful in debugging
        $info['body'] = $this->lastBody;

        $retVal['info'] = $info;

        if ($info['http_code'] < 400) {
            if ($info['http_code'] == 204) {
                $retVal['response'] = '';
            } elseif ($resp) {
                $retVal['response'] = ($decode) ? json_decode($resp) : $resp;
            } else {
                error_log("{$info['url']} returned an error code: {$info['http_code']}!\n\n".print_r($info, true));
            }
        } elseif ($info['http_code'] >= 400) {
            error_log("{$info['url']} returned an error code: {$info['http_code']}!\n\n".print_r($info, true));
        }

        return $retVal;
    }

    /**
     * Encode a string.
     *
     * @param string $str the string to encode
     * @return string the encoded string or false on error
     */
    public function encodeString($str)
    {
        return rawurlencode($str);
    }

    /**
     * Creates a File response body for PUT/POST.
     *
     * @param string $filePath The file path
     * @param string $fileName Overwrite the name of the file on disk
     * @return array|boolean An array from RestClient::enodeFile or false
     */
    public function encodeFileFromPath($filePath, $fileName = false)
    {
        $retVal = false;

        if (file_exists($filePath)) {
            if (!$fileName) {
                $fileName = basename($filePath);
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($filePath, FILEINFO_MIME_TYPE);
            $fContents = file_get_contents($filePath);

            return $this->encodeFile($fileName, $mimeType, $fContents);
        }

        return $retVal;
    }

    /**
     * Creates a File response body for PUT/POST.
     *
     * @param string $name     The name to call it
     * @param string $type     The mime type
     * @param string $contents The content of the file
     * @return array (contentType => string, body => string)
     */
    public function encodeFile($name, $type, $contents)
    {
        $retVal = array();

        $boundary = "----------------------------".substr(md5(rand(0,32000)), 0, 12);
        $retVal['contentType'] = "multipart/form-data; boundary=$boundary";

        $retVal['body'] = <<<FILEDATA
--$boundary\r
Content-disposition: form-data; name="file"; filename="$name"\r
Content-Type: $type\r
\r
$contents\r
--{$boundary}--
FILEDATA;

        return $retVal;
    }
}
