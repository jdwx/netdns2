<?php
declare( strict_types = 1 );

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
 * NS Resource Record - RFC1035 section 3.3.11
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                   NSDNAME                     /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
class Net_DNS2_RR_NS extends Net_DNS2_RR
{
    /*
     * the hostname of the DNS server
     */
    public string $nsdname;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString() : string {
        return $this->cleanString($this->nsdname) . '.';
    }

    /**
     * parses the rdata portion from a standard DNS config line
     *
     * @param array $rdata a string split line of values for the rdata
     *
     * @return bool
     * @access protected
     *
     */
    protected function rrFromString(array $rdata) : bool {
        $this->nsdname = $this->cleanString(array_shift($rdata));
        return true;
    }

    /**
     * parses the rdata of the Net_DNS2_Packet object
     *
     * @param Net_DNS2_Packet &$packet a Net_DNS2_Packet packet to parse the RR from
     *
     * @return bool
     * @access protected
     *
     */
    protected function rrSet(Net_DNS2_Packet $packet) : bool {
        if ($this->rdlength > 0) {

            $offset = $packet->offset;
            $this->nsdname = Net_DNS2_Packet::expand($packet, $offset);

            return true;
        }

        return false;
    }

    /**
     * returns the rdata portion of the DNS packet
     *
     * @param Net_DNS2_Packet &$packet a Net_DNS2_Packet packet use for
     *                                 compressed names
     *
     * @return null|string                   either returns a binary packed
     *                                 string or null on failure
     * @access protected
     *
     */
    protected function rrGet(Net_DNS2_Packet $packet) : ?string {
        if (strlen($this->nsdname) > 0) {

            return $packet->compress($this->nsdname, $packet->offset);
        }
        
        return null;
    }
}
