<?php


declare(strict_types=1);


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
 * @since     File available since Release 1.4.1
 *
 */

/**
 * CSYNC Resource Record - RFC 7477 seciond 2.1.1
 *
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                  SOA Serial                   |
 *    |                                               |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    |                    Flags                      |
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *    /                 Type Bit Map                  /
 *    +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+
 *
 */
class Net_DNS2_RR_CSYNC extends Net_DNS2_RR
{
    /*
     * serial number
     */
    public string $serial;

    /*
     * flags
     */
    public string $flags;

    /*
     * array of RR type names
     */
    public array $type_bit_maps = [];

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString() : string
    {
        $out = $this->serial . ' ' . $this->flags;

        //
        // show the RR's
        //
        foreach ($this->type_bit_maps as $rr) {

            $out .= ' ' . strtoupper($rr);
        }

        return $out;
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
        $this->serial   = array_shift($rdata);
        $this->flags    = array_shift($rdata);

        $this->type_bit_maps = $rdata;

        return true;
    }

    /**
     * parses the rdata of the Net_DNS2_Packet object
     *
     * @param Net_DNS2_Packet $packet a Net_DNS2_Packet packet to parse the RR from
     *
     * @return bool
     * @access protected
     *
     */
    protected function rrSet(Net_DNS2_Packet $packet) : bool
    {
        if ($this->rdlength > 0) {

            //
            // unpack the serial and flags values
            //
            $x = unpack('@' . $packet->offset . '/Nserial/nflags', $packet->rdata);

            $this->serial   = Net_DNS2::expandUint32($x['serial']);
            $this->flags    = $x['flags'];

            //
            // parse out the RR bitmap                 
            //
            $this->type_bit_maps = Net_DNS2_BitMap::bitMapToArray(
                substr($this->rdata, 6)
            );

            return true;
        }

        return false;
    }

    /**
     * returns the rdata portion of the DNS packet
     *
     * @param Net_DNS2_Packet $packet a Net_DNS2_Packet packet to use for
     *                                 compressed names
     *
     * @return ?string                   either returns a binary packed
     *                                 string or null on failure
     * @access protected
     *
     */
    protected function rrGet(Net_DNS2_Packet $packet) : ?string
    {
        //
        // pack the serial and flags values
        //
        $data = pack('Nn', $this->serial, $this->flags);

        //
        // convert the array of RR names to a type bitmap
        //
        $data .= Net_DNS2_BitMap::arrayToBitMap($this->type_bit_maps);

        //
        // advance the offset
        //
        $packet->offset += strlen($data);

        return $data;
    }
}
