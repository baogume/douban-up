<?php

/**
 * A basic CURL wrapper
 *
 * See the README for documentation/examples or http://php.net/curl for more information about the libcurl extension for PHP
 *
 * @package curl
 * @author Sean Huber <shuber@huberry.com>
 **/
require_once 'curl_response.php';
class Curl {

    /**
     * The file to read and write cookies to for requests
     *
     * @var string
     **/
    public $cookie_file;

    /**
     * Determines whether or not requests should follow redirects
     *
     * @var boolean
     **/
    public $follow_redirects = false;

    /**
     * An associative array of headers to send along with requests
     *
     * @var array
     **/
    public $headers = array();

    /**
     * An associative array of CURLOPT options to send along with requests
     *
     * @var array
     **/
    public $options = array();

    /**
     * The referer header to send along with requests
     *
     * @var string
     **/
    public $referer;

    /**
     * The user agent to send along with requests
     *
     * @var string
     **/
    public $user_agent;

    /**
     * Stores an error string for the last request if one occurred
     *
     * @var string
     * @access protected
     **/
    protected $error = '';

    /**
     * Stores resource handle for the current CURL request
     *
     * @var resource
     * @access protected
     **/
    protected $request;

    /**
     * Initializes a Curl object
     *
     * Sets the $cookie_file to "curl_cookie.txt" in the current directory
     * Also sets the $user_agent to $_SERVER['HTTP_USER_AGENT'] if it exists, 'Curl/PHP '.PHP_VERSION.' (http://github.com/shuber/curl)' otherwise
     **/
    function __construct($cookie_file='') {
        if ($cookie_file!=''){
            $this->cookie_file = $cookie_file ;
        }else{
            $this->cookie_file = dirname(__FILE__).DIRECTORY_SEPARATOR.'curl_cookie.txt';
            $this->cookie_file = '';
        }
        $this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0';
    }

    /**
     * Makes an HTTP DELETE request to the specified $url with an optional array or string of $vars
     *
     * Returns a Curl_Response object if the request was successful, false otherwise
     *
     * @param string $url
     * @param array|string $vars
     * @return Curl_Response object
     **/
    function delete($url, $vars = array()) {
        return $this->request('DELETE', $url, $vars);
    }

    /**
     * Returns the error string of the current request if one occurred
     *
     * @return string
     **/
    function error() {
        return $this->error;
    }

    /**
     * Makes an HTTP GET request to the specified $url with an optional array or string of $vars
     *
     * Returns a Curl_Response object if the request was successful, false otherwise
     *
     * @param string $url
     * @param array|string $vars
     * @return Curl_Response
     **/
    function get($url, $vars = array()) {
        if (!empty($vars)) {
            $url .= (stripos($url, '?') !== false) ? '&' : '?';
            $url .= (is_string($vars)) ? $vars : http_build_query($vars, '', '&');
        }
        return $this->request('GET', $url);
    }

    /**
     * Makes an HTTP HEAD request to the specified $url with an optional array or string of $vars
     *
     * Returns a Curl_Response object if the request was successful, false otherwise
     *
     * @param string $url
     * @param array|string $vars
     * @return Curl_Response
     **/
    function head($url, $vars = array()) {
        return $this->request('HEAD', $url, $vars);
    }

    /**
     * Makes an HTTP POST request to the specified $url with an optional array or string of $vars
     *
     * @param string $url
     * @param array|string $vars
     * @return Curl_Response|boolean
     **/
    function post($url, $vars = array()) {
        return $this->request('POST', $url, $vars);
    }
    function upload($url, $vars = array()) {
        return $this->request('POST', $url, $vars, TRUE);
    }

    /**
     * Makes an HTTP PUT request to the specified $url with an optional array or string of $vars
     *
     * Returns a Curl_Response object if the request was successful, false otherwise
     *
     * @param string $url
     * @param array|string $vars
     * @return Curl_Response|boolean
     **/
    function put($url, $vars = array()) {
        return $this->request('PUT', $url, $vars);
    }

    /**
     * Makes an HTTP request of the specified $method to a $url with an optional array or string of $vars
     *
     * Returns a Curl_Response object if the request was successful, false otherwise
     *
     * @param string $method
     * @param string $url
     * @param array|string $vars
     * @return Curl_Response|boolean
     **/
    function request($method, $url, $vars = array(), $upload=FALSE) {
        $this->error = '';
        $this->request = curl_init();
        //新增&& $method != 'POST'否则不支持上传文件
        //if (is_array($vars) /*&& $method != 'POST'*/) {
        if (is_array($vars) && !$upload) {
            $vars = http_build_query($vars, '', '&');
        }

        $this->set_request_method($method);
        $this->set_request_options($url, $vars);
        $this->set_request_headers();

        $response = curl_exec($this->request);

        if ($response) {
            $response = new Curl_Response($response);
        } else {
            $this->error = curl_errno($this->request).'-'.curl_error($this->request);
            print_r($this->error);
        }

        curl_close($this->request);
        return $response;
    }

    /**
     * Formats and adds custom headers to the current request
     *
     * @return void
     * @access protected
     **/
    protected function set_request_headers() {
        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key.': '.$value;
        }
        //$headers[] = 'Accept-Encoding: gzip, deflate';
        // $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        // $headers[] = 'Accept-Language: zh-cn,zh;q=0.8,en-us;q=0.5,en;q=0.3';
        // $headers[] = 'Connection: keep-alive';
        // $headers[] = 'Cache-Control: max-age=0';
        $headers[] = 'Expect:';
        curl_setopt($this->request, CURLOPT_HTTPHEADER, $headers);
        //curl_setopt($this->request, CURLOPT_HTTPHEADER, array('Expect:'));
    }

    /**
     * Set the associated CURL options for a request method
     *
     * @param string $method
     * @return void
     * @access protected
     **/
    protected function set_request_method($method) {
        switch (strtoupper($method)) {
        case 'HEAD':
            curl_setopt($this->request, CURLOPT_NOBODY, true);
            break;
        case 'GET':
            curl_setopt($this->request, CURLOPT_HTTPGET, true);
            break;
        case 'POST':
            curl_setopt($this->request, CURLOPT_POST, true);
            break;
        default:
            curl_setopt($this->request, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    /**
     * Sets the CURLOPT options for the current request
     *
     * @param string $url
     * @param string $vars
     * @return void
     * @access protected
     **/
    protected function set_request_options($url, $vars) {
        // curl_setopt($this->request, CURLOPT_URL, $this->hosttoip($url));
        curl_setopt($this->request, CURLOPT_URL, $url);
        if (!empty($vars)) curl_setopt($this->request, CURLOPT_POSTFIELDS, $vars);

        # Set some default CURL options
        curl_setopt($this->request, CURLOPT_HEADER, true);
        curl_setopt($this->request, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($this->request, CURLOPT_TIMEOUT, 15);
        curl_setopt($this->request, CURLOPT_ENCODING, "gzip"); //自动转换

        curl_setopt($this->request, CURLOPT_DNS_CACHE_TIMEOUT, 3600);
        curl_setopt($this->request, CURLOPT_FORBID_REUSE, FALSE);
        //curl_setopt($this->request, CURLOPT_VERBOSE, TRUE);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->request, CURLOPT_USERAGENT, $this->user_agent);
        if ($this->follow_redirects) curl_setopt($this->request, CURLOPT_FOLLOWLOCATION, true);
        if ($this->referer) curl_setopt($this->request, CURLOPT_REFERER, $this->referer);

        # Set any custom CURL options
        foreach ($this->options as $option => $value) {
            //curl_setopt($this->request, constant('CURLOPT_'.str_replace('CURLOPT_', '', strtoupper($option))), $value);
            if ($option == CURLOPT_POSTFIELDS) continue;
            curl_setopt($this->request, $option, $value);
        }
        if ($this->cookie_file) {
            // $cookies = sprintf('__utmb=95841923.29.10.1358165626; dyweb=269921210.29.10.1358165626');
            //$cookies = 'KWD=%e7%99%be%e5%ba%a6';
            curl_setopt($this->request, CURLOPT_COOKIEFILE, $this->cookie_file);
            curl_setopt($this->request, CURLOPT_COOKIEJAR, $this->cookie_file);
            //curl_setopt($this->request, CURLOPT_COOKIE, $cookies);
        }
    }
    function hosttoip($url) {
        if(stripos($url, 'http') !== 0) {
            return $url;
        }
        $host = parse_url($url);

        if(empty($host['host'])) {
            return $url;
        }

        // $time = microtime(true);
        $ip = gethostbyname($host['host']);
        // log_message('warn', sprintf('解析url：%s host：%s 耗时：%s 结果：%s', $url, $host, microtime(true)-$time, $ip));

        if($ip) {
            $this->headers['Host'] = $host['host'];

            // return str_replace($host['host'], $ip, $url);
            // log_message('warn', $url);
            $url = preg_replace(sprintf('#^%s://%s/#is', $host['scheme'], $host['host']), sprintf('%s://%s/', $host['scheme'], $ip), $url);
            // log_message('warn', $url);
            return $url;
        } else {

            return $url;
        }
    }

}
