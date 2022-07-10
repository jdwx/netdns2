<?php /** @noinspection PhpUnused */


declare( strict_types = 1 );


namespace JDWX\DNSQuery;


use JDWX\DNSQuery\Cache\Cache;
use JDWX\DNSQuery\Cache\FileCache;
use JDWX\DNSQuery\Cache\ShmCache;
use JDWX\DNSQuery\Packet\RequestPacket;
use JDWX\DNSQuery\Packet\ResponsePacket;
use JDWX\DNSQuery\RR\RR;
use JDWX\DNSQuery\RR\SIG;
use JDWX\DNSQuery\RR\TSIG;


/**
 * DNS Library for handling lookups and updates. 
 *
 * Copyright (c) 2020, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 * @package   Net_DNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2020 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      https://netdns2.com/
 * @since     File available since Release 0.6.0
 *
 */

/**
 * This is the base class for the Net_DNS2_Resolver and Net_DNS2_Updater classes.
 *
 */
class Net_DNS2
{
    /*
     * the current version of this library
     */
    public const VERSION = '1.5.3';

    /*
     * the default path to a resolv.conf file
     */
    public const RESOLV_CONF = '/etc/resolv.conf';

    /*
     * override options from the resolv.conf file
     *
     * if this is set, then certain values from the resolv.conf file will override
     * local settings. This is disabled by default to remain backwards compatible.
     *
     */
    public bool $use_resolv_options = false;

    /*
     * use TCP only (true/false)
     */
    public bool $use_tcp = false;

    /*
     * DNS Port to use (53)
     */
    public int $dns_port = 53;

    /*
     * the ip/port for use as a local socket
     */
    public string $local_host = '';
    public int $local_port = 0;

    /*
     * timeout value for socket connections
     */
    public int $timeout = 5;

    /*
     * randomize the name servers list
     */
    public bool $ns_random = false;

    /*
     * default domains
     */
    public string $domain = '';

    /*
     * domain search list - not actually used right now
     */
    public array $search_list = [];

    /*
     * enable cache; either "shared", "file" or "none"
     */
    public string $cache_type = 'none';

    /*
     * file name to use for shared memory segment or file cache
     */
    public string $cache_file = '/tmp/net_dns2.cache';

    /*
     * the max size of the cache file (in bytes)
     */
    public int $cache_size = 50000;

    /*
     * the method to use for storing cache data; either "serialize" or "json"
     *
     * json is faster, but can't remember the class names; everything comes back
     * as a "stdClass Object" but all the data is the same. serialize is
     * slower, but will have all the class info.
     *
     * defaults to 'serialize'
     */
    public string $cache_serializer = 'serialize';

    /*
     * by default, according to RFC 1034
     *
     * CNAME RRs cause special action in DNS software.  When a name server
     * fails to find a desired RR in the resource set associated with the
     * domain name, it checks to see if the resource set consists of a CNAME
     * record with a matching class.  If so, the name server includes the CNAME
     * record in the response and restarts the query at the domain name
     * specified in the data field of the CNAME record.
     *
     * this can cause "unexpected" behaviours, since i'm sure *most* people
     * don't know DNS does this; there may be cases where Net_DNS2 returns a
     * positive response, even though the hostname the user looked up did not
     * actually exist.
     *
     * strict_query_mode means that if the hostname that was looked up isn't
     * actually in the answer section of the response, Net_DNS2 will return an 
     * empty answer section, instead of an answer section that could contain 
     * CNAME records.
     *
     */
    public bool $strict_query_mode = false;

    /*
     * if we should set the recursion desired bit to 1 or 0.
     *
     * by default this is set to true, we want the DNS server to perform a recursive
     * request. If set to false, the RD bit will be set to 0, and the server will 
     * not perform recursion on the request.
     */
    public bool $recurse = true;

    /*
     * request DNSSEC values, by setting the DO flag to 1; this actually makes
     * the resolver add an OPT RR to the additional section, and sets the DO flag
     * in this RR to 1
     *
     */
    public bool $dnssec = false;

    /*
     * set the DNSSEC AD (Authentic Data) bit on/off; the AD bit on the request 
     * side was previously undefined, and resolvers we instructed to always clear 
     * the AD bit when sending a request.
     *
     * RFC6840 section 5.7 defines setting the AD bit in the query as a signal to
     * the server that it wants the value of the AD bit, without needed to request
     * all the DNSSEC data via the DO bit.
     *
     */
    public bool $dnssec_ad_flag = false;

    /*
     * set the DNSSEC CD (Checking Disabled) bit on/off; turning this off means
     * that the DNS resolver will perform its own signature validation so the DNS
     * servers simply pass through all the details.
     *
     */
    public bool $dnssec_cd_flag = false;

    /*
     * the EDNS(0) UDP payload size to use when making DNSSEC requests
     * see RFC 4035 section 4.1 - EDNS Support.
     *
     * there are some different ideas on the suggested size to support; but it seems to
     * be "at least" 1220 bytes, but SHOULD support 4000 bytes.
     *
     * we'll just support 4000
     *
     */
    public int $dnssec_payload_size = 4000;

    /*
     * the last exception that was generated
     */
    public ?Exception $lastException = null;

    /**
     * the list of exceptions by name server
     * @var Exception[]
     */
    public array $lastExceptionList = [];

    /*
     * name server list specified as IPv4 or IPv6 addresses
     */
    public array $nameservers = [];

    /**
     * local sockets
     * @var array<int, array>
     */
    protected array $sock = [ Socket::SOCK_DGRAM => [], Socket::SOCK_STREAM => [] ];

    /*
     * the TSIG or SIG RR object for authentication
     */
    protected TSIG|SIG|null $authSignature = null;

    /*
     * the shared memory segment id for the local cache
     */
    protected Cache|null $cache = null;

    /*
     * internal setting for enabling cache
     */
    protected bool $useCache = false;

    /**
     * Constructor - base constructor for the Resolver and Updater
     *
     * @param ?array<string,mixed> $options array of options or null for none
     *
     * @throws Exception
     * @access public
     *
     */
    public function __construct(array $options = null)
    {
        //
        // load any options that were provided
        //
        if (!empty($options)) {

            foreach ($options as $key => $value) {

                if ($key == 'nameservers') {

                    $this->setServers($value);
                } else {

                    $this->$key = $value;
                }
            }
        }

        //
        // if we're set to use the local shared memory cache, then
        // make sure it's been initialized
        //
        switch($this->cache_type) {
        case 'shared':
            if (extension_loaded('shmop')) {

                $this->cache = new ShmCache();
                $this->useCache = true;
            } else {

                throw new Exception(
                    'shmop library is not available for cache',
                    Lookups::E_CACHE_SHM_UNAVAIL
                );
            }
            break;
        case 'file':

            $this->cache = new FileCache();
            $this->useCache = true;

            break;  
        case 'none':
            $this->useCache = false;
            break;
        default:

            throw new Exception(
                'un-supported cache type: ' . $this->cache_type,
                Lookups::E_CACHE_UNSUPPORTED
            );
        }
    }

    /**
     * autoload call-back function; used to autoload classes
     *
     * @param string $name the name of the class
     *
     * @return void
     * @access public
     *
     */
    public static function autoload( string $name ) : void
    {
        //
        // only autoload our classes
        //
        if (strncmp($name, 'Net_DNS2', 8) == 0) {

            /** @noinspection PhpIncludeInspection */
            include str_replace('_', '/', $name) . '.php';
        }
    }

    /**
     * sets the name servers to be used, specified as IPv4 or IPv6 addresses
     *
     * @param array|string $nameservers either an array of name servers, or a file name
     *                           to parse, assuming it's in the resolv.conf format
     *
     * @return bool
     * @throws Exception
     * @access public
     *
     */
    public function setServers( array|string $nameservers) : bool
    {
        //
        // if it's an array, then use it directly
        //
        // otherwise, see if it's a path to a resolv.conf file and if so, load it
        //
        if (is_array($nameservers)) {

            // collect valid IP addresses in a temporary list
            $ipAddresses = [];

            foreach ($nameservers as $value) {
                if (self::isIPv4($value) || self::isIPv6($value)) {
                    $ipAddresses[] = $value;
                } else {
                    throw new Exception(
                        'invalid nameserver entry: ' . $value,
                        Lookups::E_NS_INVALID_ENTRY
                    );
                }
            }

            // only replace the nameservers list if no exception is thrown
            $this->nameservers = $ipAddresses;

        } else {

            //
            // temporary list of name servers; do it this way rather than just 
            // resetting the local nameservers value, just in case an exception
            // is thrown here; this way we might avoid ending up with an empty 
            // list of nameservers.
            //
            $ns = [];

            //
            // check to see if the file is readable
            //
            if (is_readable($nameservers) === true) {
    
                $data = file_get_contents($nameservers);
                if ($data === false) {
                    throw new Exception(
                        'failed to read contents of file: ' . $nameservers,
                        Lookups::E_NS_INVALID_FILE
                    );
                }

                $lines = explode("\n", $data);

                foreach ($lines as $line) {
                    
                    $line = trim($line);

                    //
                    // ignore empty lines, and lines that are commented out
                    //
                    if ( (strlen($line) == 0) 
                        || ($line[0] == '#') 
                        || ($line[0] == ';')
                    ) {
                        continue;
                    }

                    //
                    // ignore lines with no spaces in them.
                    //
                    if ( ! str_contains( $line, ' ' ) ) {
                        continue;
                    }

                    [$key, $value] = preg_split('/\s+/', $line, 2);

                    $key    = trim(strtolower($key));
                    $value  = trim(strtolower($value));

                    switch($key) {
                    case 'nameserver':

                        //
                        // nameserver can be a IPv4 or IPv6 address
                        //
                        if ( self::isIPv4( $value )
                            || self::isIPv6( $value )
                        ) {

                            $ns[] = $value;
                        } else {

                            throw new Exception(
                                'invalid nameserver entry: ' . $value,
                                Lookups::E_NS_INVALID_ENTRY
                            );
                        }
                        break;

                    case 'domain':
                        $this->domain = $value;
                        break;

                    case 'search':
                        $this->search_list = preg_split('/\s+/', $value);
                        break;

                    case 'options':
                        $this->parseOptions($value);
                        break;

                    }
                }

                //
                // if we don't have a domain, but we have a search list, then
                // take the first entry on the search list as the domain
                //
                if ( (strlen($this->domain) == 0) 
                    && (count($this->search_list) > 0) 
                ) {
                    $this->domain = $this->search_list[0];
                }

            } else {
                throw new Exception(
                    'resolver file file provided is not readable: ' . $nameservers,
                    Lookups::E_NS_INVALID_FILE
                );
            }

            //
            // store the name servers locally
            //
            if (count($ns) > 0) {
                $this->nameservers = $ns;
            }
        }

        //
        // remove any duplicates; not sure if we should bother with this. if people
        // put duplicate name servers, who I am to stop them?
        //
        $this->nameservers = array_unique($this->nameservers);

        //
        // check the name servers
        //
        $this->checkServers();

        return true;
    }

    /**
     * return the internal $sock array
     *
     * @return array<int, array>
     * @access public
     */
    public function getSockets() : array
    {
        return $this->sock;
    }

    /**
     * give users access to close all open sockets on the resolver object; resetting each
     * array, calls the destructor on the Net_DNS2_Socket object, which calls the close()
     * method on each object.
     *
     * @return bool
     * @access public
     *
     */
    public function closeSockets() : bool
    {
        $this->sock[Socket::SOCK_DGRAM]    = [];
        $this->sock[Socket::SOCK_STREAM]   = [];

        return true;
    }

    /**
     * parses the options line from a resolv.conf file; we don't support all the options
     * yet, and using them is optional.
     *
     * @param string $value is the options string from the resolv.conf file.
     *
     * @return void
     * @access private
     *
     */
    private function parseOptions( string $value ) : void {
        //
        // if overrides are disabled (the default), or the options list is empty for some
        // reason, then we don't need to do any of this work.
        //
        if ( ! $this->use_resolv_options || (strlen($value) == 0) ) {

            return;
        }

        $options = preg_split('/\s+/', strtolower($value));

        foreach ($options as $option) {

            //
            // override the timeout value from the resolv.conf file.
            //
            if ( (strncmp($option, 'timeout', 7) == 0) && ( str_contains( $option, ':' ) ) ) {

                $val = (int) explode( ':', $option )[ 1 ];

                if ( ($val > 0) && ($val <= 30) ) {

                    $this->timeout = $val;
                }

            //
            // the rotate option just enabled the ns_random option
            //
            } elseif (strncmp($option, 'rotate', 6) == 0) {

                $this->ns_random = true;
            }
        }

    }

    /**
     * checks the list of name servers to make sure they're set
     *
     * @param array|string|null $default a path to a resolv.conf file or an array of servers.
     *
     * @return bool
     * @throws Exception
     * @access protected
     *
     */
    protected function checkServers( array|string|null $default = null) : bool
    {
        if (empty($this->nameservers)) {

            if (isset($default)) {

                $this->setServers($default);
            } else {

                throw new Exception(
                    'empty name servers list; you must provide a list of name '.
                    'servers, or the path to a resolv.conf file.',
                    Lookups::E_NS_INVALID_ENTRY
                );
            }
        }
    
        return true;
    }


    /**
     * adds a TSIG RR object for authentication
     *
     * @param TSIG|string $key_name the key name to use for the TSIG RR
     * @param string                  $signature the key to sign the request.
     * @param string                  $algorithm the algorithm to use
     *
     * @return bool
     * @access public
     * @throws Exception
     * @since  function available since release 1.1.0
     *
     */
    public function signTSIG(
        TSIG|string $key_name, string $signature = '', string $algorithm = TSIG::HMAC_MD5
    ) : bool {
        //
        // if the TSIG was pre-created and passed in, then we can just use
        // it as provided.
        //
        if ($key_name instanceof TSIG) {

            $this->authSignature = $key_name;

        } else {

            //
            // otherwise create the TSIG RR, but don't add it just yet; TSIG needs 
            // to be added as the last additional entry so we'll add it just
            // before we send.
            //
            $xx = RR::fromString(
                strtolower(trim($key_name)) .
                ' TSIG '. $signature
            );
            assert( $xx instanceof TSIG );
            $this->authSignature = $xx;

            //
            // set the algorithm to use
            //
            $this->authSignature->algorithm = $algorithm;
        }
          
        return true;
    }

    /**
     * adds a SIG RR object for authentication
     *
     * @param SIG|string $filename a signature or the name of a file to load the signature from.
     * 
     * @return bool
     * @throws Exception
     * @access public
     * @since  function available since release 1.1.0
     *
     */
    public function signSIG0( SIG|string $filename ) : bool
    {
        //
        // check for OpenSSL
        //
        if (extension_loaded('openssl') === false) {
            
            throw new Exception(
                'the OpenSSL extension is required to use SIG(0).',
                Lookups::E_OPENSSL_UNAVAIL
            );
        }

        //
        // if the SIG was pre-created, then use it as-is
        //
        if ($filename instanceof SIG) {

            $this->authSignature = $filename;

        } else {
        
            //
            // otherwise, it's filename which needs to be parsed and processed.
            //
            $private = new PrivateKey($filename);

            //
            // create a new SIG object
            //
            $this->authSignature = new SIG();

            //
            // reset some values
            //
            $this->authSignature->name         = $private->signName;
            $this->authSignature->ttl          = 0;
            $this->authSignature->class        = 'ANY';

            //
            // these values are pulled from the private key
            //
            $this->authSignature->algorithm    = $private->algorithm;
            $this->authSignature->keytag       = $private->keytag;
            $this->authSignature->signName     = $private->signName;

            //
            // these values are hard-coded for SIG0
            //
            $this->authSignature->typeCovered  = 'SIG0';
            $this->authSignature->labels       = 0;
            $this->authSignature->origTTL      = 0;

            //
            // generate the dates
            //
            $t = time();

            $this->authSignature->sigInception     = gmdate('YmdHis', $t);
            $this->authSignature->sigExpiration       = gmdate('YmdHis', $t + 500);

            //
            // store the private key in the SIG object for later.
            //
            $this->authSignature->privateKey  = $private;
        }

        //
        // only RSA algorithms are supported for SIG(0)
        //
        switch($this->authSignature->algorithm) {
        case Lookups::DNSSEC_ALGORITHM_RSAMD5:
        case Lookups::DNSSEC_ALGORITHM_RSASHA1:
        case Lookups::DNSSEC_ALGORITHM_RSASHA256:
        case Lookups::DNSSEC_ALGORITHM_RSASHA512:
        case Lookups::DNSSEC_ALGORITHM_DSA:
            break;
        default:
            throw new Exception(
                'only asymmetric algorithms work with SIG(0)!',
                Lookups::E_OPENSSL_INV_ALGO
            );
        }

        return true;
    }

    /**
     * a simple function to determine if the RR type is cacheable
     *
     * @param string $_type the RR type string
     *
     * @return bool returns true/false if the RR type if cacheable
     * @access public
     *
     */
    public function cacheable( string $_type) : bool
    {
        return match ( $_type ) {
            'AXFR', 'OPT' => false,
            default => true,
        };

    }

    /**
     * PHP doesn't support unsigned integers, but many of the RRs return
     * unsigned values (like SOA), so there is the possibility that the
     * value will overrun on 32bit systems, and you'll end up with a 
     * negative value.
     *
     * 64bit systems are not affected, as their PHP_INT_MAX value should
     * be 64bit (ie 9223372036854775807)
     *
     * This function returns a negative integer value, as a string, with
     * the correct unsigned value.
     *
     * @param string $_int the unsigned integer value to check
     *
     * @return string returns the unsigned value as a string.
     * @access public
     *
     */
    public static function expandUint32( string $_int ) : string
    {
        $ii = (int) $_int;
        if ( ($ii < 0) && (PHP_INT_MAX == 2147483647) ) {
            return sprintf('%u', $_int);
        } else {
            return $_int;
        }
    }

    /**
     * returns true/false if the given address is a valid IPv4 address
     *
     * @param string $_address the IPv4 address to check
     *
     * @return bool returns true/false if the address is IPv4 address
     * @access public
     *
     */
    public static function isIPv4( string $_address ) : bool
    {
        return !! filter_var( $_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
    }
    

    /**
     * returns true/false if the given address is a valid IPv6 address
     *
     * @param string $_address the IPv6 address to check
     *
     * @return bool returns true/false if the address is IPv6 address
     * @access public
     *
     */
    public static function isIPv6( string $_address ) : bool
    {
        return !! filter_var($_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }


    /**
     * formats the given IPv6 address as a fully expanded IPv6 address
     *
     * @param string $_address the IPv6 address to expand
     *
     * @return string the fully expanded IPv6 address
     * @access public
     *
     */
    public static function expandIPv6( string $_address ) : string
    {
        $hex = unpack('H*hex', inet_pton($_address));
    
        return substr(preg_replace('/([A-f\d]{4})/', "$1:", $hex['hex']), 0, -1);
    }

    /**
     * sends a standard Net_DNS2_Packet_Request packet
     *
     * @param RequestPacket $request a Net_DNS2_Packet_Request object
     * @param bool            $use_tcp true/false if the function should
     *                                 use TCP for the request
     *
     * @return ResponsePacket
     * @throws Exception
     * @access protected
     *
     */
    protected function sendPacket(RequestPacket $request, bool $use_tcp) : ResponsePacket
    {
        //
        // get the data from the packet
        //
        $data = $request->get();
        if (strlen($data) < Lookups::DNS_HEADER_SIZE) {

            throw new Exception(
                'invalid or empty packet for sending!',
                Lookups::E_PACKET_INVALID,
                null,
                $request
            );
        }

        reset($this->nameservers);
        
        //
        // randomize the name server list if it's asked for
        //
        if ( $this->ns_random ) {

            shuffle($this->nameservers);
        }

        //
        // loop so we can handle server errors
        //

        while (1) {

            //
            // grab the next DNS server
            //
            $ns = current($this->nameservers);
            next($this->nameservers);

            if ($ns === false) {

                if ( ! is_null( $this->lastException ) ) {

                    throw $this->lastException;
                } else {

                    throw new Exception(
                        'every name server provided has failed',
                        Lookups::E_NS_FAILED
                    );
                }
            }

            //
            // if the use TCP flag (force TCP) is set, or the packet is bigger than our 
            // max allowed UDP size, which is either 512, or if this is DNSSEC request,
            // then whatever the configured dnssec_payload_size is.
            //
            $max_udp_size = Lookups::DNS_MAX_UDP_SIZE;
            if ( $this->dnssec )
            {
                $max_udp_size = $this->dnssec_payload_size;
            }

            if ( $use_tcp || (strlen($data) > $max_udp_size) ) {

                try
                {
                    $response = $this->sendTCPRequest($ns, $data, $request->question[0]->qtype == 'AXFR' );

                } catch(Exception $e) {

                    $this->lastException = $e;
                    $this->lastExceptionList[$ns] = $e;

                    continue;
                }

            //
            // otherwise, send it using UDP
            //
            } else {

                try
                {
                    $response = $this->sendUDPRequest($ns, $data);

                    //
                    // check the packet header for a truncated bit; if it was truncated,
                    // then re-send the request as TCP.
                    //
                    if ($response->header->tc == 1) {

                        $response = $this->sendTCPRequest($ns, $data);
                    }

                } catch(Exception $e) {

                    $this->lastException = $e;
                    $this->lastExceptionList[$ns] = $e;

                    continue;
                }
            }

            //
            // make sure header id's match between the request and response
            //
            if ($request->header->id != $response->header->id) {

                $this->lastException = new Exception(

                    'invalid header: the request and response id do not match.',
                    Lookups::E_HEADER_INVALID,
                    null,
                    $request,
                    $response
                );

                $this->lastExceptionList[$ns] = $this->lastException;
                continue;
            }

            //
            // make sure the response is actually a response
            // 
            // 0 = query, 1 = response
            //
            if ($response->header->qr != Lookups::QR_RESPONSE) {
            
                $this->lastException = new Exception(

                    'invalid header: the response provided is not a response packet.',
                    Lookups::E_HEADER_INVALID,
                    null,
                    $request,
                    $response
                );

                $this->lastExceptionList[$ns] = $this->lastException;
                continue;
            }

            //
            // make sure the response code in the header is ok
            //
            if ($response->header->rcode != Lookups::RCODE_NOERROR) {
            
                $this->lastException = new Exception(
                
                    'DNS request failed: ' . 
                    Lookups::$result_code_messages[$response->header->rcode],
                    $response->header->rcode,
                    null,
                    $request,
                    $response
                );

                $this->lastExceptionList[$ns] = $this->lastException;
                continue;
            }

            break;
        }

        return $response;
    }

    /**
     * cleans up a failed socket and throws the given exception
     *
     * @param int    $_proto the protocol of the socket
     * @param string $_ns    the name server to use for the request
     *
     * @throws Exception
     * @access private
     *
     */
    private function generateError( int $_proto, string $_ns ) : void
    {
        if ( ! isset( $this->sock[ $_proto ][ $_ns ] ) )
        {
            throw new Exception('invalid socket referenced', Lookups::E_NS_INVALID_SOCKET);
        }
        
        //
        // grab the last error message off the socket
        //
        $last_error = $this->sock[$_proto][$_ns]->last_error;
        
        //
        // remove it from the socket cache; this will call the destructor, which calls close() on the socket
        //
        unset($this->sock[$_proto][$_ns]);

        //
        // throw the error provided
        //
        throw new Exception($last_error, Lookups::E_NS_SOCKET_FAILED );
    }

    /**
     * sends a DNS request using TCP
     *
     * @param string  $_ns   the name server to use for the request
     * @param string  $_data the raw DNS packet data
     * @param bool $_axfr if this is a zone transfer request
     *
     * @return ResponsePacket the response object
     * @throws Exception
     * @access private
     *
     */
    private function sendTCPRequest( string $_ns, string $_data, bool $_axfr = false ) : ResponsePacket
    {
        //
        // grab the start time
        //
        $start_time = microtime(true);

        //
        // see if we already have an open socket from a previous request; if so, try to use
        // that instead of opening a new one.
        //
        if ( (!isset($this->sock[Socket::SOCK_STREAM][$_ns]))
            || (!($this->sock[Socket::SOCK_STREAM][$_ns] instanceof Socket))
        ) {

            //
            // create the socket object
            //
            $this->sock[Socket::SOCK_STREAM][$_ns] = new Socket(
                Socket::SOCK_STREAM, $_ns, $this->dns_port, $this->timeout
            );

            //
            // if a local IP address / port is set, then add it
            //
            if (strlen($this->local_host) > 0) {

                $this->sock[Socket::SOCK_STREAM][$_ns]->bindAddress(
                    $this->local_host, $this->local_port
                );
            }

            //
            // open the socket
            //
            if ($this->sock[Socket::SOCK_STREAM][$_ns]->open() === false) {

                $this->generateError( Socket::SOCK_STREAM, $_ns );
            }
        }

        //
        // write the data to the socket; if it fails, continue on
        // the while loop
        //
        if ($this->sock[Socket::SOCK_STREAM][$_ns]->write($_data) === false) {

            $this->generateError( Socket::SOCK_STREAM, $_ns );
        }

        //
        // read the content, using select to wait for a response
        //
        $size = 0;
        $response = null;

        //
        // handle zone transfer requests differently than other requests.
        //
        if ( $_axfr ) {

            $soa_count = 0;

            while (1) {

                //
                // read the data off the socket
                //
                $result = $this->sock[Socket::SOCK_STREAM][$_ns]->read($size,
                    $this->dnssec ? $this->dnssec_payload_size : Lookups::DNS_MAX_UDP_SIZE);

                if ( ($result === false) || ($size < Lookups::DNS_HEADER_SIZE) ) {

                    //
                    // if we get an error, then keeping this socket around for a future request, could cause
                    // an error- for example, https://github.com/mikepultz/netdns2/issues/61
                    //
                    // in this case, the connection was timing out, which once it did finally respond, left
                    // data on the socket, which could be captured on a subsequent request.
                    //
                    // since there's no way to "reset" a socket, the only thing we can do it close it.
                    //
                    $this->generateError( Socket::SOCK_STREAM, $_ns );
                }

                //
                // parse the first chunk as a packet
                //
                $chunk = new ResponsePacket($result, $size);

                //
                // if this is the first packet, then clone it directly, then
                // go through it to see if there are two SOA records
                // (indicating that it's the only packet)
                //
                if ( is_null( $response ) ) {

                    $response = clone $chunk;

                    //
                    // look for a failed response; if the zone transfer
                    // failed, then we don't need to do anything else at this
                    // point, and we should just break out.                 
                    //
                    if ($response->header->rcode != Lookups::RCODE_NOERROR) {
                        break;
                    }

                    //   
                    // go through each answer
                    //
                    foreach ($response->answer as $rr) {

                        //
                        // count the SOA records
                        //
                        if ($rr->type == 'SOA') {
                            $soa_count++;
                        }
                    }

                } else {

                    //
                    // go through all these answers, and look for SOA records
                    //
                    foreach ($chunk->answer as $rr) {

                        //
                        // count the number of SOA records we find
                        //
                        if ($rr->type == 'SOA') {
                            $soa_count++;           
                        }

                        //
                        // add the records to a single response object
                        //
                        $response->answer[] = $rr;                  
                    }

                }
                //
                // if we have 2 or more SOA records, then we're done;
                // otherwise continue out so we read the rest of the
                // packets off the socket
                //
                if ($soa_count >= 2) {
                    break;
                }

            }

        //
        // everything other than a AXFR
        //
        } else {

            $result = $this->sock[Socket::SOCK_STREAM][$_ns]->read($size,
                $this->dnssec ? $this->dnssec_payload_size : Lookups::DNS_MAX_UDP_SIZE);

            if ( ($result === false) || ($size < Lookups::DNS_HEADER_SIZE) ) {

                $this->generateError( Socket::SOCK_STREAM, $_ns );
            }

            //
            // create the packet object
            //
            $response = new ResponsePacket($result, $size);
        }

        //
        // store the query time
        //
        $response->response_time = microtime(true) - $start_time;

        //
        // add the name server that the response came from to the response object,
        // and the socket type that was used.
        //
        $response->answer_from = $_ns;
        $response->answer_socket_type = Socket::SOCK_STREAM;

        //
        // return the Net_DNS2_Packet_Response object
        //
        return $response;
    }

    /**
     * sends a DNS request using UDP
     *
     * @param string  $_ns   the name server to use for the request
     * @param string  $_data the raw DNS packet data
     *
     * @return ResponsePacket the response object
     * @throws Exception
     * @access private
     *
     */
    private function sendUDPRequest( string $_ns, string $_data ) : ResponsePacket
    {
        //
        // grab the start time
        //
        $start_time = microtime(true);

        //
        // see if we already have an open socket from a previous request; if so, try to use
        // that instead of opening a new one.
        //
        if ( (!isset($this->sock[Socket::SOCK_DGRAM][$_ns]))
            || (!($this->sock[Socket::SOCK_DGRAM][$_ns] instanceof Socket))
        ) {

            //
            // create the socket object
            //
            $this->sock[Socket::SOCK_DGRAM][$_ns] = new Socket(
                Socket::SOCK_DGRAM, $_ns, $this->dns_port, $this->timeout
            );

            //
            // if a local IP address / port is set, then add it
            //
            if (strlen($this->local_host) > 0) {

                $this->sock[Socket::SOCK_DGRAM][$_ns]->bindAddress(
                    $this->local_host, $this->local_port
                );
            }

            //
            // open the socket
            //
            if ($this->sock[Socket::SOCK_DGRAM][$_ns]->open() === false) {

                $this->generateError( Socket::SOCK_DGRAM, $_ns );
            }
        }

        //
        // write the data to the socket
        //
        if ($this->sock[Socket::SOCK_DGRAM][$_ns]->write($_data) === false) {

            $this->generateError( Socket::SOCK_DGRAM, $_ns );
        }

        //
        // read the content, using select to wait for a response
        //
        $size = 0;

        $result = $this->sock[Socket::SOCK_DGRAM][$_ns]->read($size,
            $this->dnssec ? $this->dnssec_payload_size : Lookups::DNS_MAX_UDP_SIZE);

        if (( $result === false) || ($size < Lookups::DNS_HEADER_SIZE)) {

            $this->generateError( Socket::SOCK_DGRAM, $_ns );
        }

        //
        // create the packet object
        //
        $response = new ResponsePacket($result, $size);

        //
        // store the query time
        //
        $response->response_time = microtime(true) - $start_time;

        //
        // add the name server that the response came from to the response object,
        // and the socket type that was used.
        //
        $response->answer_from = $_ns;
        $response->answer_socket_type = Socket::SOCK_DGRAM;

        //
        // return the Net_DNS2_Packet_Response object
        //
        return $response;
    }
}

