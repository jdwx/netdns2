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
 * SOA Resource Record - RFC1035 section 3.3.13
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                     mName                     /
 *    /                                               /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                     rName                     /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    SERIAL                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    REFRESH                    |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                     RETRY                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    EXPIRE                     |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    MINIMUM                    |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
class SOA extends RR
{
    /*
     * The master DNS server
     */
    public string $mName;

    /*
     * mailbox of the responsible person
     */
    public string $rName;

    /*
     * serial number
      */
    public int $serial;

    /*
      * refresh time
      */
    public int $refresh;

    /*
      * retry interval
     */
    public int $retry;

    /*
     * expire time
      */
    public int $expire;

    /*
     * minimum TTL for any RR in this zone
      */
    public int $minimum;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString() : string {
        return $this->cleanString($this->mName) . '. ' .
            $this->cleanString($this->rName) . '. ' .
            $this->serial . ' ' . $this->refresh . ' ' . $this->retry . ' ' . 
            $this->expire . ' ' . $this->minimum;
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
    protected function rrFromString(array $rdata) : bool {
        $this->mName    = $this->cleanString($rdata[0]);
        $this->rName    = $this->cleanString($rdata[1]);

        $this->serial   = (int) $rdata[2];
        $this->refresh  = (int) $rdata[3];
        $this->retry    = (int) $rdata[4];
        $this->expire   = (int) $rdata[5];
        $this->minimum  = (int) $rdata[6];

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
            // parse the 
            //
            $offset = $packet->offset;

            $this->mName = $packet->expandEx( $offset );
            $this->rName = $packet->expandEx( $offset, true);

            //
            // get the SOA values
            //
            /** @noinspection SpellCheckingInspection */
            $x = unpack(
                '@' . $offset . '/Nserial/Nrefresh/Nretry/Nexpire/Nminimum/', 
                $packet->rdata
            );

            $this->serial   = $x[ 'serial' ];
            $this->refresh  = $x[ 'refresh' ];
            $this->retry    = $x[ 'retry' ];
            $this->expire   = $x[ 'expire' ];
            $this->minimum  = $x[ 'minimum' ];

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
        if (strlen($this->mName) > 0) {
    
            $data = $packet->compress($this->mName, $packet->offset);
            $data .= $packet->compress($this->rName, $packet->offset);

            $data .= pack(
                'N5', $this->serial, $this->refresh, $this->retry, 
                $this->expire, $this->minimum
            );

            $packet->offset += 20;

            return $data;
        }

        return null;
    }
}
