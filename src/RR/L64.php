<?php


declare( strict_types = 1 );


namespace JDWX\DNSQuery\RR;


use JDWX\DNSQuery\Packet\Packet;


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
 * @since     File available since Release 1.3.1
 *
 */

/**
 * L64 Resource Record - RFC6742 section 2.3
 *
 *   0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |          Preference           |                               |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+                               +
 *  |                          Locator64                            |
 *  +                               +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *  |                               |
 *  +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
class L64 extends RR
{
    /*
     * The preference
     */
    public int $preference;

    /*
     * The locator64 field
     */
    public string $locator64;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString() : string
    {
        return $this->preference . ' ' . $this->locator64;
    }

    /**
     * parses the rdata portion from a standard DNS config line
     *
     * @param string[] $rdata a string split line of values for the rdata
     *
     * @return bool
     * @access protected
     *
     */
    protected function rrFromString(array $rdata) : bool
    {
        $this->preference = (int) array_shift( $rdata );
        $this->locator64  = array_shift($rdata);

        return true;
    }

    /**
     * parses the rdata of the Net_DNS2_Packet object
     *
     * @param Packet $packet a Net_DNS2_Packet packet to parse the RR from
     *
     * @return bool
     * @access protected
     * 
     */
    protected function rrSet( Packet $packet) : bool
    {
        if ($this->rdLength > 0) {
 
            //
            // unpack the values
            //
            /** @noinspection SpellCheckingInspection */
            $x = unpack('npreference/n4locator', $this->rdata);
   
            $this->preference = $x['preference'];
 
            //
            // build the locator64
            //
            $this->locator64 = dechex($x['locator1']) . ':' . 
                dechex($x['locator2']) . ':' .
                dechex($x['locator3']) . ':' . 
                dechex($x['locator4']);
      
            return true;
        }
    
        return false;
    }

    /**
     * returns the rdata portion of the DNS packet
     * 
     * @param Packet $packet a Net_DNS2_Packet packet to use for
     *                                 compressed names
     *
     * @return ?string                   either returns a binary packed
     *                                 string or null on failure
     * @access protected
     * 
     */
    protected function rrGet( Packet $packet) : ?string
    {
        if (strlen($this->locator64) > 0) {
      
            //
            // break out the locator64
            //
            $n = explode(':', $this->locator64);
    
            //
            // pack the data
            //
            return pack(
                'n5', $this->preference, hexdec($n[0]), hexdec($n[1]),
                hexdec($n[2]), hexdec($n[3])
            );
        }

        return null;
    }
}
