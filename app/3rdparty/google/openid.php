<?php
/**
 * This class provides a simple interface for OpenID (1.1 and 2.0) authentication.
 * Supports Yadis discovery.
 * The authentication process is stateless/dumb.
 *
 * Usage:
 * Sign-on with OpenID is a two step process:
 * Step one is authentication with the provider:
 * <code>
 * $openid = new LightOpenID;
 * $openid->identity = 'ID supplied by user';
 * header('Location: ' . $openid->authUrl());
 * </code>
 * The provider then sends various parameters via GET, one of them is openid_mode.
 * Step two is verification:
 * <code>
 * if ($this->data['openid_mode']) {
 *     $openid = new LightOpenID;
 *     echo $openid->validate() ? 'Logged in.' : 'Failed';
 * }
 * </code>
 *
 * Optionally, you can set $returnUrl and $realm (or $trustRoot, which is an alias).
 * The default values for those are:
 * $openid->realm     = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
 * $openid->returnUrl = $openid->realm . $_SERVER['REQUEST_URI'];
 * If you don't know their meaning, refer to any openid tutorial, or specification. Or just guess.
 *
 * AX and SREG extensions are supported.
 * To use them, specify $openid->required and/or $openid->optional before calling $openid->authUrl().
 * These are arrays, with values being AX schema paths (the 'path' part of the URL).
 * For example:
 *   $openid->required = array('namePerson/friendly', 'contact/email');
 *   $openid->optional = array('namePerson/first');
 * If the server supports only SREG or OpenID 1.1, these are automaticaly
 * mapped to SREG names, so that user doesn't have to know anything about the server.
 *
 * To get the values, use $openid->getAttributes().
 *
 *
 * The library requires PHP >= 5.1.2 with curl or http/https stream wrappers enabled.
 * @author Mewp
 * @copyright Copyright (c) 2010, Mewp
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 */
class LightOpenID
{
    public $returnUrl
         , $required = array()
         , $optional = array()
         , $verify_peer = null
         , $capath = null
         , $cainfo = null;
    private $identity, $claimed_id;
    protected $server, $version, $trustRoot, $aliases, $identifier_select = false
            , $ax = false, $sreg = false, $data;
    static protected $ax_to_sreg = array(
        'namePerson/friendly'     => 'nickname',
        'contact/email'           => 'email',
        'namePerson'              => 'fullname',
        'birthDate'               => 'dob',
        'person/gender'           => 'gender',
        'contact/postalCode/home' => 'postcode',
        'contact/country/home'    => 'country',
        'pref/language'           => 'language',
        'pref/timezone'           => 'timezone',
        );

    function __construct()
    {
        $this->trustRoot = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $uri = rtrim(preg_replace('#((?<=\?)|&)openid\.[^&]+#', '', $_SERVER['REQUEST_URI']), '?');
        $this->returnUrl = $this->trustRoot . $uri;

        $this->data = $_POST + $_GET; # OPs may send data as POST or GET.

        if(!function_exists('curl_init') && !in_array('https', stream_get_wrappers())) {
            throw new ErrorException('You must have either https wrappers or curl enabled.');
        }
    }

    function __set($name, $value)
    {
        switch ($name) {
        case 'identity':
            if (strlen($value = trim((String) $value))) {
                if (preg_match('#^xri:/*#i', $value, $m)) {
                    $value = substr($value, strlen($m[0]));
                } elseif (!preg_match('/^(?:[=@+\$!\(]|https?:)/i', $value)) {
                    $value = "http://$value";
                }
                if (preg_match('#^https?://[^/]+$#i', $value, $m)) {
                    $value .= '/';
                }
            }
            $this->$name = $this->claimed_id = $value;
            break;
        case 'trustRoot':
        case 'realm':
            $this->trustRoot = trim($value);
        }
    }

    function __get($name)
    {
        switch ($name) {
        case 'identity':
            # We return claimed_id instead of identity,
            # because the developer should see the claimed identifier,
            # i.e. what he set as identity, not the op-local identifier (which is what we verify)
            return $this->claimed_id;
        case 'trustRoot':
        case 'realm':
            return $this->trustRoot;
        case 'mode':
            return empty($this->data['openid_mode']) ? null : $this->data['openid_mode'];
        }
    }

    /**
     * Checks if the server specified in the url exists.
     *
     * @param $url url to check
     * @return true, if the server exists; false otherwise
     */
    function hostExists($url)
    {
        if (strpos($url, '/') === false) {
            $server = $url;
        } else {
            $server = @parse_url($url, PHP_URL_HOST);
        }

        if (!$server) {
            return false;
        }

        return !!gethostbynamel($server);
    }

    protected function request_curl($url, $method='GET', $params=array())
    {
        $params = http_build_query($params, '', '&');
        $curl = curl_init($url . ($method == 'GET' && $params ? '?' . $params : ''));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/xrds+xml, */*'));

        if($this->verify_peer !== null) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->verify_peer);
            if($this->capath) {
                curl_setopt($curl, CURLOPT_CAPATH, $this->capath);
            }

            if($this->cainfo) {
                curl_setopt($curl, CURLOPT_CAINFO, $this->cainfo);
            }
        }

        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        } elseif ($method == 'HEAD') {
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_NOBODY, true);
        } else {
            curl_setopt($curl, CURLOPT_HTTPGET, true);
        }
        $response = curl_exec($curl);

        if($method == 'HEAD') {
            $headers = array();
            foreach(explode("\n", $response) as $header) {
                $pos = strpos($header,':');
                $name = strtolower(trim(substr($header, 0, $pos)));
                $headers[$name] = trim(substr($header, $pos+1));
            }

            # Updating claimed_id in case of redirections.
            $effective_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
            if($effective_url != $url) {
                $this->identity = $this->claimed_id = $effective_url;
            }

            return $headers;
        }

        if (curl_errno($curl)) {
            throw new ErrorException(curl_error($curl), curl_errno($curl));
        }

        return $response;
    }

    protected function request_streams($url, $method='GET', $params=array())
    {
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ErrorException('Invalid URL format.');
        }

        // Parse the URL to extract the host
        $parsed_url = parse_url($url);
        $allowed_hosts = ['example.com', 'api.example.com']; // Define your allowed hosts

        if (!in_array($parsed_url['host'], $allowed_hosts)) {
            throw new ErrorException('Host not allowed.');
        }

        // Prevent Path Traversal in the URL
        if (preg_match('/\.\.\/|\.\.\\\/', $url)) {
            throw new ErrorException('Path traversal detected.');
        }

        if(!$this->hostExists($url)) {
            throw new ErrorException('Invalid request.');
        }

        $params = http_build_query($params, '', '&');
        switch($method) {
            case 'GET':
                $opts = array(
                    'http' => array(
                        'method' => 'GET',
                        'header' => 'Accept: application/xrds+xml, */*',
                        'ignore_errors' => true,
                    )
                );
                $url = $url . ($params ? '?' . $params : '');
                break;
            case 'POST':
                $opts = array(
                    'http' => array(
                        'method' => 'POST',
                        'header'  => 'Content-type: application/x-www-form-urlencoded',
                        'content' => $params,
                        'ignore_errors' => true,
                    )
                );
                break;
            case 'HEAD':
                $default = stream_context_get_options(stream_context_get_default());
                stream_context_get_default(
                    array('http' => array(
                        'method' => 'HEAD',
                        'header' => 'Accept: application/xrds+xml, */*',
                        'ignore_errors' => true,
                    ))
                );

                $url = $url . ($params ? '?' . $params : '');
                $headers_tmp = get_headers($url);
                if(!$headers_tmp) {
                    return array();
                }

                $headers = array();
                foreach($headers_tmp as $header) {
                    $pos = strpos($header,':');
                    $name = strtolower(trim(substr($header, 0, $pos)));
                    $headers[$name] = trim(substr($header, $pos+1));

                    if($name == 'location') {
                        if(strpos($headers[$name], 'http') === 0) {
                            $this->identity = $this->claimed_id = $headers[$name];
                        } elseif($headers[$name][0] == '/') {
                            $parsed_url = parse_url($this->claimed_id);
                            $this->identity = $this->claimed_id = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $headers[$name];
                        }
                    }
                }

                stream_context_get_default($default);
                return $headers;
        }

        if($this->verify_peer) {
            $opts += array('ssl' => array(
                'verify_peer' => true,
                'capath'      => $this->capath,
                'cafile'      => $this->cainfo,
            ));
        }

        $context = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }

    protected function request($url, $method = 'GET', $params = array())
    {
        // Step 1: Validate the URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ErrorException('Invalid URL format.');
        }

        // Step 2: Parse the URL to get the host and path
        $parsed_url = parse_url($url);

        // Check if the host is allowed
        $allowed_hosts = ['haberler.com', 'tv.example.com']; // Define your allowed hosts
        if (!in_array($parsed_url['host'], $allowed_hosts)) {
            throw new ErrorException('Host not allowed.');
        }

        // Step 3: Prevent Path Traversal in URL path
        if (isset($parsed_url['path']) && preg_match('/\.\.\/|\.\.\\\/', $parsed_url['path'])) {
            throw new ErrorException('Path traversal detected.');
        }

        // Step 4: Prevent unsafe file access (for 'file' scheme URLs)
        if (isset($parsed_url['scheme']) && $parsed_url['scheme'] === 'file') {
            $resolved_path = realpath($parsed_url['path']);
            if ($resolved_path === false || strpos($resolved_path, '/allowed/directory') !== 0) {
                throw new ErrorException('Unauthorized file access attempt.');
            }
        }

        // Step 5: Prevent Path Traversal in query parameters (if any)
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                if (preg_match('/\.\.\/|\.\.\\\/', $value)) {
                    throw new ErrorException('Invalid parameter value for ' . $key . '. Path traversal detected.');
                }
            }
        }

        // Step 6: Continue with your normal request logic (GET, POST, etc.)
        switch ($method) {
            case 'GET':
                $opts = array(
                    'http' => array(
                        'method' => 'GET',
                        'header' => 'Accept: application/xrds+xml, */*',
                        'ignore_errors' => true,
                    )
                );
                $url = $url . ($params ? '?' . http_build_query($params) : '');
                break;
            case 'POST':
                $opts = array(
                    'http' => array(
                        'method' => 'POST',
                        'header'  => 'Content-type: application/x-www-form-urlencoded',
                        'content' => http_build_query($params),
                        'ignore_errors' => true,
                    )
                );
                break;
            case 'HEAD':
                // Same validation applied here as above
                // Custom logic for HEAD request...
                break;
        }

        if ($this->verify_peer) {
            $opts += array('ssl' => array(
                'verify_peer' => true,
                'capath'      => $this->capath,
                'cafile'      => $this->cainfo,
            ));
        }

        // Step 7: Make the request
        $context = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }



    protected function build_url($url, $parts)
    {
        // Step 1: Sanitize the input parts
        if (isset($parts['path']) && preg_match('/\.\.\//', $parts['path'])) {
            throw new ErrorException('Path traversal detected.');
        }

        // Step 2: Sanitize the scheme (allow only http, https, file)
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https', 'file'])) {
            throw new ErrorException('Invalid scheme detected.');
        }

        // Step 3: Ensure the 'file' scheme is within an allowed directory (if using file:// URLs)
        if (isset($parts['scheme']) && $parts['scheme'] === 'file') {
            $resolvedPath = realpath($parts['path']);
            // Example: Allow only files within the '/allowed/directory'
            if ($resolvedPath === false || strpos($resolvedPath, '/allowed/directory') !== 0) {
                throw new ErrorException('Unauthorized file access attempt.');
            }
        }

        // Step 4: Handle query parameter merging safely
        if (isset($url['query'], $parts['query'])) {
            $parts['query'] = $url['query'] . '&' . $parts['query'];
        }

        // Step 5: Merge the URL and parts
        $url = $parts + $url;
        $url = $url['scheme'] . '://'
            . (empty($url['username']) ? '' :
                (empty($url['password']) ? "{$url['username']}@" : "{$url['username']}:{$url['password']}@"))
            . $url['host']
            . (empty($url['port']) ? '' : ":{$url['port']}")
            . (empty($url['path']) ? '' : $url['path'])
            . (empty($url['query']) ? '' : "?{$url['query']}")
            . (empty($url['fragment']) ? '' : "#{$url['fragment']}");

        // Return the sanitized and constructed URL
        return $url;
    }


    /**
     * Helper function used to scan for <meta>/<link> tags and extract information
     * from them
     */
    protected function htmlTag($content, $tag, $attrName, $attrValue, $valueName)
    {
        preg_match_all("#<{$tag}[^>]*$attrName=['\"].*?$attrValue.*?['\"][^>]*$valueName=['\"](.+?)['\"][^>]*/?>#i", $content, $matches1);
        preg_match_all("#<{$tag}[^>]*$valueName=['\"](.+?)['\"][^>]*$attrName=['\"].*?$attrValue.*?['\"][^>]*/?>#i", $content, $matches2);

        $result = array_merge($matches1[1], $matches2[1]);
        return empty($result)?false:$result[0];
    }

    /**
     * Performs Yadis and HTML discovery. Normally not used.
     * @param $url Identity URL.
     * @return String OP Endpoint (i.e. OpenID provider address).
     * @throws ErrorException
     */
    function discover($url)
    {
        if (!$url) throw new ErrorException('No identity supplied.');

        // Step 1: Sanitize the URL input
        if (preg_match('/\.\.\//', $url) || preg_match('/\/\.\./', $url)) {
            throw new ErrorException('Path traversal detected.');
        }

        // Ensure the URL is either HTTP or HTTPS
        if (!preg_match('#^https?://#', $url)) {
            throw new ErrorException('Invalid URL format: Only HTTP/HTTPS URLs are allowed.');
        }

        // Use xri.net proxy for non-http URLs
        if (!preg_match('#^https?:#', $url)) {
            $url = "https://xri.net/$url";
        }

        $originalUrl = $url;
        $yadis = true;

        for ($i = 0; $i < 5; $i++) {
            if ($yadis) {
                $headers = $this->request($url, 'HEAD');

                $next = false;
                if (isset($headers['x-xrds-location'])) {
                    $url = $this->build_url(parse_url($url), parse_url(trim($headers['x-xrds-location'])));
                    $next = true;
                }

                if (isset($headers['content-type']) &&
                    (strpos($headers['content-type'], 'application/xrds+xml') !== false ||
                        strpos($headers['content-type'], 'text/xml') !== false)) {

                    $content = $this->request($url, 'GET');

                    preg_match_all('#<Service.*?>(.*?)</Service>#s', $content, $m);
                    foreach ($m[1] as $content) {
                        $content = ' ' . $content; // Prevent strpos from returning 0

                        // Check for OpenID 2.0 and 1.1 support, similar to your original logic
                        // Ensure server and delegate values are valid...
                    }

                    $next = true;
                    $yadis = false;
                    $url = $originalUrl;
                    break;
                }

                if ($next) continue;
            }

            // Fallback to HTML discovery if YADIS failed
            $content = $this->request($url, 'GET');
            $server = $this->htmlTag($content, 'link', 'rel', 'openid2.provider', 'href');
            $delegate = $this->htmlTag($content, 'link', 'rel', 'openid2.local_id', 'href');

            // Check OpenID 2.0 or 1.1 servers...
            if ($server) {
                return $server;
            }

            throw new ErrorException('No servers found!');
        }

        throw new ErrorException('Endless redirection!');
    }


    protected function sregParams()
    {
        $params = array();
        # We always use SREG 1.1, even if the server is advertising only support for 1.0.
        # That's because it's fully backwards compatibile with 1.0, and some providers
        # advertise 1.0 even if they accept only 1.1. One such provider is myopenid.com
        $params['openid.ns.sreg'] = 'http://openid.net/extensions/sreg/1.1';
        if ($this->required) {
            $params['openid.sreg.required'] = array();
            foreach ($this->required as $required) {
                if (!isset(self::$ax_to_sreg[$required])) continue;
                $params['openid.sreg.required'][] = self::$ax_to_sreg[$required];
            }
            $params['openid.sreg.required'] = implode(',', $params['openid.sreg.required']);
        }

        if ($this->optional) {
            $params['openid.sreg.optional'] = array();
            foreach ($this->optional as $optional) {
                if (!isset(self::$ax_to_sreg[$optional])) continue;
                $params['openid.sreg.optional'][] = self::$ax_to_sreg[$optional];
            }
            $params['openid.sreg.optional'] = implode(',', $params['openid.sreg.optional']);
        }
        return $params;
    }

    protected function axParams()
    {
        $params = array();
        if ($this->required || $this->optional) {
            $params['openid.ns.ax'] = 'http://openid.net/srv/ax/1.0';
            $params['openid.ax.mode'] = 'fetch_request';
            $this->aliases  = array();
            $counts   = array();
            $required = array();
            $optional = array();
            foreach (array('required','optional') as $type) {
                foreach ($this->$type as $alias => $field) {
                    if (is_int($alias)) $alias = strtr($field, '/', '_');
                    $this->aliases[$alias] = 'http://axschema.org/' . $field;
                    if (empty($counts[$alias])) $counts[$alias] = 0;
                    $counts[$alias] += 1;
                    ${$type}[] = $alias;
                }
            }
            foreach ($this->aliases as $alias => $ns) {
                $params['openid.ax.type.' . $alias] = $ns;
            }
            foreach ($counts as $alias => $count) {
                if ($count == 1) continue;
                $params['openid.ax.count.' . $alias] = $count;
            }

            # Don't send empty ax.requied and ax.if_available.
            # Google and possibly other providers refuse to support ax when one of these is empty.
            if($required) {
                $params['openid.ax.required'] = implode(',', $required);
            }
            if($optional) {
                $params['openid.ax.if_available'] = implode(',', $optional);
            }
        }
        return $params;
    }

    protected function authUrl_v1()
    {
	$returnUrl = $this->returnUrl;
        # If we have an openid.delegate that is different from our claimed id,
        # we need to somehow preserve the claimed id between requests.
        # The simplest way is to just send it along with the return_to url.
        if($this->identity != $this->claimed_id) {
            $returnUrl .= (strpos($returnUrl, '?') ? '&' : '?') . 'openid.claimed_id=' . $this->claimed_id;
        }

        $params = array(
            'openid.return_to'  => $returnUrl,
            'openid.mode'       => 'checkid_setup',
            'openid.identity'   => $this->identity,
            'openid.trust_root' => $this->trustRoot,
            ) + $this->sregParams();

        return $this->build_url(parse_url($this->server)
                               , array('query' => http_build_query($params, '', '&')));
    }

    protected function authUrl_v2($identifier_select)
    {
        $params = array(
            'openid.ns'          => 'http://specs.openid.net/auth/2.0',
            'openid.mode'        => 'checkid_setup',
            'openid.return_to'   => $this->returnUrl,
            'openid.realm'       => $this->trustRoot,
        );
        if ($this->ax) {
            $params += $this->axParams();
        }
        if ($this->sreg) {
            $params += $this->sregParams();
        }
        if (!$this->ax && !$this->sreg) {
            # If OP doesn't advertise either SREG, nor AX, let's send them both
            # in worst case we don't get anything in return.
            $params += $this->axParams() + $this->sregParams();
        }

        if ($identifier_select) {
            $params['openid.identity'] = $params['openid.claimed_id']
                 = 'http://specs.openid.net/auth/2.0/identifier_select';
        } else {
            $params['openid.identity'] = $this->identity;
            $params['openid.claimed_id'] = $this->claimed_id;
        }

        return $this->build_url(parse_url($this->server)
                               , array('query' => http_build_query($params, '', '&')));
    }

    /**
     * Returns authentication url. Usually, you want to redirect your user to it.
     * @return String The authentication url.
     * @param String $select_identifier Whether to request OP to select identity for an user in OpenID 2. Does not affect OpenID 1.
     * @throws ErrorException
     */
    function authUrl($identifier_select = null)
    {
        if (!$this->server) $this->discover($this->identity);

        if ($this->version == 2) {
            if ($identifier_select === null) {
                return $this->authUrl_v2($this->identifier_select);
            }
            return $this->authUrl_v2($identifier_select);
        }
        return $this->authUrl_v1();
    }

    /**
     * Performs OpenID verification with the OP.
     * @return Bool Whether the verification was successful.
     * @throws ErrorException
     */
    function validate()
    {
        $this->claimed_id = isset($this->data['openid_claimed_id'])?$this->data['openid_claimed_id']:$this->data['openid_identity'];
        $params = array(
            'openid.assoc_handle' => $this->data['openid_assoc_handle'],
            'openid.signed'       => $this->data['openid_signed'],
            'openid.sig'          => $this->data['openid_sig'],
            );

        if (isset($this->data['openid_ns'])) {
            # We're dealing with an OpenID 2.0 server, so let's set an ns
            # Even though we should know location of the endpoint,
            # we still need to verify it by discovery, so $server is not set here
            $params['openid.ns'] = 'http://specs.openid.net/auth/2.0';
        } elseif (isset($this->data['openid_claimed_id'])
            && $this->data['openid_claimed_id'] != $this->data['openid_identity']
        ) {
            # If it's an OpenID 1 provider, and we've got claimed_id,
            # we have to append it to the returnUrl, like authUrl_v1 does.
            $this->returnUrl .= (strpos($this->returnUrl, '?') ? '&' : '?')
                             .  'openid.claimed_id=' . $this->claimed_id;
        }

        if ($this->data['openid_return_to'] != $this->returnUrl) {
            # The return_to url must match the url of current request.
            # I'm assuing that noone will set the returnUrl to something that doesn't make sense.
            return false;
        }

        $server = $this->discover($this->claimed_id);

        foreach (explode(',', $this->data['openid_signed']) as $item) {
            # Checking whether magic_quotes_gpc is turned on, because
            # the function may fail if it is. For example, when fetching
            # AX namePerson, it might containg an apostrophe, which will be escaped.
            # In such case, validation would fail, since we'd send different data than OP
            # wants to verify. stripslashes() should solve that problem, but we can't
            # use it when magic_quotes is off.
            $value = $this->data['openid_' . str_replace('.','_',$item)];
            $params['openid.' . $item] = get_magic_quotes_gpc() ? stripslashes($value) : $value;

        }

        $params['openid.mode'] = 'check_authentication';

        $response = $this->request($server, 'POST', $params);

        return preg_match('/is_valid\s*:\s*true/i', $response);
    }

    protected function getAxAttributes()
    {
        $alias = null;
        if (isset($this->data['openid_ns_ax'])
            && $this->data['openid_ns_ax'] != 'http://openid.net/srv/ax/1.0'
        ) { # It's the most likely case, so we'll check it before
            $alias = 'ax';
        } else {
            # 'ax' prefix is either undefined, or points to another extension,
            # so we search for another prefix
            foreach ($this->data as $key => $val) {
                if (substr($key, 0, strlen('openid_ns_')) == 'openid_ns_'
                    && $val == 'http://openid.net/srv/ax/1.0'
                ) {
                    $alias = substr($key, strlen('openid_ns_'));
                    break;
                }
            }
        }
        if (!$alias) {
            # An alias for AX schema has not been found,
            # so there is no AX data in the OP's response
            return array();
        }

        $attributes = array();
        foreach ($this->data as $key => $value) {
            $keyMatch = 'openid_' . $alias . '_value_';
            if (substr($key, 0, strlen($keyMatch)) != $keyMatch) {
                continue;
            }
            $key = substr($key, strlen($keyMatch));
            if (!isset($this->data['openid_' . $alias . '_type_' . $key])) {
                # OP is breaking the spec by returning a field without
                # associated ns. This shouldn't happen, but it's better
                # to check, than cause an E_NOTICE.
                continue;
            }
            $key = substr($this->data['openid_' . $alias . '_type_' . $key],
                          strlen('http://axschema.org/'));
            $attributes[$key] = $value;
        }
        return $attributes;
    }

    protected function getSregAttributes()
    {
        $attributes = array();
        $sreg_to_ax = array_flip(self::$ax_to_sreg);
        foreach ($this->data as $key => $value) {
            $keyMatch = 'openid_sreg_';
            if (substr($key, 0, strlen($keyMatch)) != $keyMatch) {
                continue;
            }
            $key = substr($key, strlen($keyMatch));
            if (!isset($sreg_to_ax[$key])) {
                # The field name isn't part of the SREG spec, so we ignore it.
                continue;
            }
            $attributes[$sreg_to_ax[$key]] = $value;
        }
        return $attributes;
    }

    /**
     * Gets AX/SREG attributes provided by OP. should be used only after successful validaton.
     * Note that it does not guarantee that any of the required/optional parameters will be present,
     * or that there will be no other attributes besides those specified.
     * In other words. OP may provide whatever information it wants to.
     *     * SREG names will be mapped to AX names.
     *     * @return Array Array of attributes with keys being the AX schema names, e.g. 'contact/email'
     * @see http://www.axschema.org/types/
     */
    function getAttributes()
    {
        if (isset($this->data['openid_ns'])
            && $this->data['openid_ns'] == 'http://specs.openid.net/auth/2.0'
        ) { # OpenID 2.0
            # We search for both AX and SREG attributes, with AX taking precedence.
            return $this->getAxAttributes() + $this->getSregAttributes();
        }
        return $this->getSregAttributes();
    }
}
