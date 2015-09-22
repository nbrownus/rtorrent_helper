<?php
defineEnv('RTORRENT_RPC_TIME_OUT');
defineEnv('RTORRENT_SCGI_HOST');
defineEnv('RTORRENT_SCGI_PORT');
defineEnv('PYTHON_PATH');
defineEnv('SICKBEARD_SRC_DIR');
defineEnv('SICKBEARD_PICKUP_DIR');
defineEnv('SICKBEARD_API_URL');
defineEnv('COUCHPOTATO_SRC_DIR');
defineEnv('COUCHPOTATO_DEST_DIR');
defineEnv('COUCHPOTATO_API_URL');

if (!isset($argv[1])) {
    throw new Exception('No torrent hash provided');
}

Rename::single($argv[1]);

class Rename {

    /**
     * List of file types to handle
     *
     * @var array
     */
    private static $m_fileTypes = array('mkv', 'avi', 'mp4', 'mpg', 'mov');

    /**
     * Handles moving a single torrent by it's hash
     * The destination is dictated by the label in rtorrent
     *
     * @param String $torrentHash The rtorrent hash/id to process
     */
    public static function single($torrentHash) {
        $torrentName = cXMLRPC::call('d.get_name', $torrentHash);
        $torrentPath = cXMLRPC::call('d.get_base_path', $torrentHash);
        $multiFile = cXMLRPC::call('d.is_multi_file', $torrentHash);
        $label = cXMLRPC::call('d.get_custom1', $torrentHash);

        if ($label == 'SickBeard') {
            self::_handleSickbeard($torrentName, $torrentPath, $multiFile);

        } elseif ($label == 'CouchPotato' || $label == 'Movies') {
            self::_handleCouchPotato($torrentName, $torrentPath, $multiFile);
        } else {
            //throw new Exception('Unknown torrent label ' . $label);
        }
    }

    private static function _handleCouchPotato($torrentName, $torrentPath, $multiFile) {
        $movedBaseDir = COUCHPOTATO_SRC_DIR . '/';
        if (substr($torrentName, -4, 1) == '.') {
            $torrentName = substr($torrentName, 0, -4);
        }

        $hardLinkDestination = COUCHPOTATO_DEST_DIR . '/' . $torrentName . '/';
        $hardLinkSources = array();

        _log('Hard linking files to "' . $hardLinkDestination . '"');

        if ($multiFile) {
            $output = null;
            _exec('find ' . escapeshellarg($movedBaseDir . basename($torrentPath)) . ' -name "*"', $output);

            foreach ($output as $line) {
                if ($line != '' && stripos($line, 'sample') === false) {
                    $ext = strtolower(substr($line, -3));
                    if (in_array($ext, self::$m_fileTypes) === true) {
                        $hardLinkSources[] = $line;
                    } else {
                        _log('        Ignoring file (ext type): ' . $line);
                    }
                } else {
                    _log('        Ignoring file (blank or sample): ' . $line);
                }
            }

        } else {
            $hardLinkSources[] = $movedBaseDir . basename($torrentPath);
        }

        _exec('mkdir -p ' . escapeshellarg($hardLinkDestination));
        foreach ($hardLinkSources as $hardLinkSource) {
            _exec('ln ' . escapeshellarg($hardLinkSource) . ' ' . escapeshellarg($hardLinkDestination));
        }

        _exec('chmod -R u=rwX,g=rwX,o=rX ' . escapeshellarg($hardLinkDestination));
        _exec('ls -l ' . escapeshellarg($hardLinkDestination));
        _log('');

        _log('Notifying CouchPotato');
        _exec('curl ' . escapeshellarg(COUCHPOTATO_API_URL . '/renamer.scan/?async=1'));
    }

    private static function _handleSickbeard($torrentName, $torrentPath, $multiFile) {
        $movedBaseDir = SICKBEARD_SRC_DIR . '/';
        $hardLinkSources = array();


        if ($multiFile) {
            $hardLinkDestination = SICKBEARD_PICKUP_DIR . '/' . $torrentName . '/';
            _log('Hard linking files to "' . $hardLinkDestination . '"');

            $output = null;
            _exec('find ' . escapeshellarg($movedBaseDir . basename($torrentPath)) . ' -name "*"', $output);

            foreach ($output as $line) {
                if ($line != '' && stripos($line, 'sample') === false) {
                    $ext = strtolower(substr($line, -3));
                    if (in_array($ext, self::$m_fileTypes) === true) {
                        $hardLinkSources[] = $line;
                    } else {
                        _log('        Ignoring file (ext type): ' . $line);
                    }
                } else {
                    _log('        Ignoring file (blank or sample): ' . $line);
                }
            }

        } else {
            $hardLinkDestination = SICKBEARD_PICKUP_DIR . '/';
            _log('Hard linking files to "' . $hardLinkDestination . '"');

            $hardLinkSources[] = $movedBaseDir . basename($torrentPath);
        }

        _exec('mkdir -p ' . escapeshellarg($hardLinkDestination));
        foreach ($hardLinkSources as $hardLinkSource) {
            _exec('ln ' . escapeshellarg($hardLinkSource) . ' ' . escapeshellarg($hardLinkDestination));
       	}

		_exec('chmod -R u=rwX,g=rwX,o=rX ' . escapeshellarg($hardLinkDestination));
        _exec('ls -l ' . escapeshellarg($hardLinkDestination));

        _log('Notifying SickBeard');
        _exec('curl ' . escapeshellarg(SICKBEARD_API_URL . '?cmd=postprocess'));

        _log('');

        _exec('find ' . SICKBEARD_PICKUP_DIR . ' -mindepth 1 -type d -empty -delete');
    }
}

class cXMLRPC {
    public static function call($command, $args) {
        if (!is_array($args)) {
            $args = array($args);
        }

        //_log('    xmlrpc: ' . $command . '(' . implode(', ', $args) . ')', 1);

        $xml = self::buildCall($command, $args);
        $response = self::send($xml);
        $response = self::parseResponse($response);

        return $response;
    }

    private static function buildCall($command, $params) {
        $content = '<?xml version="1.0" encoding="UTF-8"?>';
           $content .= '<methodCall><methodName>' . $command . '</methodName><params>';

        foreach ($params as $param) {
            $type = self::paramType($param);
            $content .= '<param><value><' . $type . '>' . $param . '</' . $type . '></value></param>';
        }

        $content .= '</params></methodCall>';
        return $content;
    }

    private static function send($xml) {
        $result = false;
        $contentLength = strlen($xml);
        $socket = fsockopen(RTORRENT_SCGI_HOST, RTORRENT_SCGI_PORT, $errorNumber, $errorString, RTORRENT_RPC_TIME_OUT);

        if ($socket) {
            $header = 'CONTENT_LENGTH' . "\x0" . $contentLength . "\x0" . 'SCGI' . "\x0" . "1\x0";
            $payload = strlen($header) . ':' . $header . ',' . $xml;

            fwrite($socket, $payload, strlen($payload));

            $result = '';
            while ($data = fread($socket, 4096)) {
                $result .= $data;
            }

            fclose($socket);
        }

        return $result;
    }

    private static function parseResponse($rawResponse) {
        $return = array();

        preg_match_all('/<value>(<string>|<i.>)(.*)(<\/string>|<\/i.>)<\/value>/Us', $rawResponse, $found);

        if (is_array($found) && count($found) >= 2) {
            foreach($found[2] as $key => $string) {
                $string = urldecode(html_entity_decode($string, ENT_COMPAT, 'UTF-8'));
                if (substr($string, 0, 10) == 'VRS24mrker') {
                    $string = substr($string, 10);
                }

                $return[$key] = $string;
            }
        }

        if (strpos($rawResponse, '<fault>') !== false) {
            throw new Exception('Bad XMLRPC response: ' . print_r($return, true));
        }

        if (empty($return)) {
            $return = null;
        } elseif (count($return) == 1) {
            $return = $return[0];
        }

        return $return;
    }

    private static function paramType($param) {
        if (is_int($param)) {
            $type = 'i4';
        } elseif (is_double($param)) {
            $type = 'i8';
        } else {
            $type = 'string';
        }

        return $type;
    }
}

/**
 * Performs curl calls for the Direct API, other projects may use this class directly
 */
class cCurl {

    /** The last tried URL */
    private static $m_lastUrl;

    /** Holds a curl object, to be used for keep alives one day */
    private static $m_curlObj;

    /** Holds user defined curl options */
    private static $m_curlOptions = array(
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 5,
    );

    public static function request($urls, $post = null, $curlOptions = null) {
        self::$m_curlObj = curl_init();

        if (!is_array($curlOptions)) {
            $curlOptions = self::$m_curlOptions;
        } else {
            $curlOptions = array_merge(self::$m_curlOptions, $curlOptions);
        }

        $curlOptions[CURLOPT_HEADER] = false;
        $curlOptions[CURLOPT_RETURNTRANSFER] = true;

        if ($post) {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = $post;
        }

        self::_setOpts($curlOptions);

        if (!is_array($urls)) {
            $urls = array($urls);
        }

        //Setup to attempt retries
        $curlResult = false;
        foreach ($urls as $url) {
            _log('   curl: ' . $url);
            curl_setopt(self::$m_curlObj, CURLOPT_URL, $url);
            $curlResult = curl_exec(self::$m_curlObj);

            //Exit out of retry in case we are good
            if ($curlResult !== false) {
                break;
            }
        }

        self::$m_lastUrl = $url;

        curl_close(self::$m_curlObj);
        _log('        < ' . $curlResult);
        return $curlResult;
    }

    public static function setCurlOptions($options) {
        self::$m_curlOptions = array_merge(self::$m_curlOptions, $options);
        return true;
    }

    public static function removeCurlOptions($options) {
        self::$m_curlOptions = array_diff_key(self::$m_curlOptions, $options);
        return true;
    }

    /**
     * Returns the current user specified set of curl options
     *
     * @return array The array of user specified curl options
     */
    public static function getCurlOptions() {
        return self::$m_curlOptions;
    }

    /**
     * Return the last requests url
     *
     * @return string A url
     */
    public static function getLastUrl() {
        return self::$m_lastUrl;
    }

    /**
     * Gets debug info for a curl request
     *
     * @param array $curlOptions The options used for the request
     *
     * @return string A nicely formatted string with debug info
     */
    private static function _getDebugInfo($curlOptions) {
        //@ignoreDiscouragedFunctions
        $curlInfo = curl_getinfo(self::$m_curlObj);
        return "\n" . 'Curl Info: ' . print_r($curlInfo, true) . "\n" .
        	'Curl Options: ' . var_export($curlOptions, true);
    }

    /**
     * Sets curl options in a way that works for mulptiple php versions
     *
     * @param array &$curlOptions An array of curl options
     *
     * @return void
     */
    private static function _setOpts(&$curlOptions) {
        if (!function_exists('curl_setopt_array')) {
            foreach ($curlOptions as $optionName => $value) {
                curl_setopt(self::$m_curlObj, $optionName, $value);
            }

        } else {
            curl_setopt_array(self::$m_curlObj, $curlOptions);
        }
    }

}

/**
 * Logs a message to stdout
 *
 * @param String $msg The message to log
 */
function _log($msg) {;
    echo $msg . "\n";
}

/**
 * Helper for logging a call to `exec`
 *
 * @param String $cmd The command to execute
 *
 * @param array $output The output captured from stdout/stderr of the cmd
 */
function _exec($cmd, &$output = array()) {
    $return = 0;

    _log('    $ ' . $cmd);
    exec($cmd . ' 2>&1', $output, $return);

    foreach ($output as $line) {
        _log('        < ' . $line);
    }

    _log('        ! ' . (string) $return);
}

/**
 * Defines a constant from an environment variable, throw an exception if it is not defined
 *
 * @param String $var The environment var to define a constant for
 *
 * @throws Exception If the environment var was not set
 */
function defineEnv($var) {
    if (!($value = getenv($var))) {
        throw new Exception($var . ' is not defined');
    }

    define($var, $value);
}
