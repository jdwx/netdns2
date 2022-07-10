<?php


declare( strict_types = 1 );


namespace JDWX\DNSQuery\RR;


use JDWX\DNSQuery\Exception;
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
 * @since     File available since Release 0.6.0
 *
 */

/**
 * SRV Resource Record - RFC2782
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                   PRIORITY                    |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    WEIGHT                     |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                     PORT                      |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                    TARGET                     /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
class SRV extends RR
{
    /*
     * The priority of this target host.
     */
    public int $priority;

    /*
     * a relative weight for entries with the same priority
     */
    public int $weight;

    /*
      * The port on this target host of this service.
     */
    public int $port;

    /*
      * The domain name of the target host
     */
    public string $target;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString() : string {
        return $this->priority . ' ' . $this->weight . ' ' . 
            $this->port . ' ' . $this->cleanString($this->target) . '.';
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
        $this->priority = (int) $rdata[0];
        $this->weight   = (int) $rdata[1];
        $this->port     = (int) $rdata[2];

        $this->target   = $this->cleanString($rdata[3]);
        
        return true;
    }


    /**
     * parses the rdata of the Net_DNS2_Packet object
     *
     * @param Packet &$packet a Net_DNS2_Packet packet to parse the RR from
     *
     * @return bool
     * @access protected
     *
     * @throws Exception
     */
    protected function rrSet( Packet $packet) : bool {
        if ($this->rdLength > 0) {
            
            //
            // unpack the priority, weight and port
            //
            /** @noinspection SpellCheckingInspection */
            $x = unpack('npriority/nweight/nport', $this->rdata);

            $this->priority = $x['priority'];
            $this->weight   = $x['weight'];
            $this->port     = $x['port'];

            $offset         = $packet->offset + 6;
            $this->target   = $packet->expandEx( $offset );

            return true;
        }
        
        return false;
    }

    /**
     * returns the rdata portion of the DNS packet
     *
     * @param Packet &$packet a Net_DNS2_Packet packet use for
     *                                 compressed names
     *
     * @return null|string                   either returns a binary packed
     *                                 string or null on failure
     * @access protected
     *
     */
    protected function rrGet( Packet $packet) : ?string {
        if (strlen($this->target) > 0) {

            $data = pack('nnn', $this->priority, $this->weight, $this->port);
            $packet->offset += 6;

            $data .= $packet->compress($this->target, $packet->offset);

            return $data;
        }

        return null;
    }
}
