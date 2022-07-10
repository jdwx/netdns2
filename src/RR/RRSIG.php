<?php


declare( strict_types = 1 );


namespace JDWX\DNSQuery\RR;


use JDWX\DNSQuery\Exception;
use JDWX\DNSQuery\Lookups;
use JDWX\DNSQuery\Net_DNS2;
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
 * This file contains code based off the Net::DNS::SEC Perl module by Olaf M. Kolkman
 *
 * This is the copyright notice from the PERL Net::DNS::SEC module:
 *
 * Copyright (c) 2001 - 2005  RIPE NCC.  Author Olaf M. Kolkman 
 * Copyright (c) 2007 - 2008  NLnet Labs.  Author Olaf M. Kolkman 
 * <olaf@net-dns.org>
 *
 * All Rights Reserved
 *
 * Permission to use, copy, modify, and distribute this software and its
 * documentation for any purpose and without fee is hereby granted,
 * provided that the above copyright notice appear in all copies and that
 * both that copyright notice and this permission notice appear in
 * supporting documentation, and that the name of the author not be
 * used in advertising or publicity pertaining to distribution of the
 * software without specific, written prior permission.
 *
 * THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE, INCLUDING
 * ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS; IN NO EVENT SHALL
 * AUTHOR BE LIABLE FOR ANY SPECIAL, INDIRECT OR CONSEQUENTIAL DAMAGES OR ANY
 * DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN
 * AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 *
 */

/**
 * RRSIG Resource Record - RFC4034 section 3.1
 *
 *    0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |        Type Covered           |  Algorithm    |     Labels    |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |                         Original TTL                          |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |                      Signature Expiration                     |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |                      Signature Inception                      |
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   |            Key Tag            |                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+         Signer's Name         /
 *   /                                                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *   /                                                               /
 *   /                            Signature                          /
 *   /                                                               /
 *   +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
class RRSIG extends RR
{
    /*
     * the RR type covered by this signature
     */
    public string $typeCovered;

    /*
     * the algorithm used for the signature
     */
    public string $algorithm;
    
    /*
     * the number of labels in the name
     */
    public string $labels;

    /*
     * the original TTL
     */
    public string $origTTL;

    /*
     * the signature expiration
     */
    public string $sigExpiration;

    /*
     * the inception of the signature
    */
    public string $sigInception;

    /*
     * the keytag used
     */
    public string $keytag;

    /*
     * the signer's name
     */
    public string $signName;

    /*
     * the signature
     */
    public string $signature;

    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString() : string {
        return $this->typeCovered . ' ' . $this->algorithm . ' ' .
            $this->labels . ' ' . $this->origTTL . ' ' .
            $this->sigExpiration . ' ' . $this->sigInception . ' ' .
            $this->keytag . ' ' . $this->cleanString($this->signName) . '. ' .
            $this->signature;
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
        $this->typeCovered  = strtoupper(array_shift($rdata));
        $this->algorithm    = array_shift($rdata);
        $this->labels       = array_shift($rdata);
        $this->origTTL      = array_shift($rdata);
        $this->sigExpiration       = array_shift($rdata);
        $this->sigInception     = array_shift($rdata);
        $this->keytag       = array_shift($rdata);
        $this->signName     = $this->cleanString(array_shift($rdata));

        foreach ($rdata as $line) {

            $this->signature .= $line;
        }

        $this->signature = trim($this->signature);

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
            // unpack 
            //
            /** @noinspection SpellCheckingInspection */
            $x = unpack(
                'ntc/Calgorithm/Clabels/Norigttl/Nsigexp/Nsigincep/nkeytag', 
                $this->rdata
            );

            $this->typeCovered  = Lookups::$rr_types_by_id[$x['tc']];
            $this->algorithm    = $x['algorithm'];
            $this->labels       = $x['labels'];
            $this->origTTL      = Net_DNS2::expandUint32($x['origttl']);

            //
            // the dates are in GM time
            //
            $this->sigExpiration       = gmdate('YmdHis', $x['sigexp']);
            $this->sigInception     = gmdate('YmdHis', $x['sigincep']);

            //
            // get the keytag
            //
            $this->keytag       = $x['keytag'];

            //
            // get teh signers name and signature
            //
            $offset             = $packet->offset + 18;
            $sigoffset          = $offset;

            $this->signName     = strtolower( $packet->expandEx( $sigoffset ) );
            $this->signature    = base64_encode(
                substr($this->rdata, 18 + ($sigoffset - $offset))
            );

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
        if (strlen($this->signature) > 0) {

            //
            // parse the values out of the dates
            //
            preg_match(
                '/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $this->sigExpiration, $e
            );
            preg_match(
                '/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $this->sigInception, $i
            );

            //
            // pack the value
            //
            /** @noinspection SpellCheckingInspection */
            $data = pack(
                'nCCNNNn', 
                Lookups::$rr_types_by_name[$this->typeCovered],
                $this->algorithm,
                $this->labels,
                $this->origTTL,
                gmmktime( (int) $e[4], (int) $e[5], (int) $e[6], (int) $e[2], (int) $e[3], (int) $e[1]),
                gmmktime( (int) $i[4], (int) $i[5], (int) $i[6], (int) $i[2], (int) $i[3], (int) $i[1]),
                $this->keytag
            );

            //
            // the signer name is special; it's not allowed to be compressed 
            // (see section 3.1.7)
            //
            $names = explode('.', strtolower($this->signName));
            foreach ($names as $name) {
    
                $data .= chr(strlen($name));
                $data .= $name;
            }
            $data .= "\0";

            //
            // add the signature
            //
            $data .= base64_decode($this->signature);

            $packet->offset += strlen($data);

            return $data;
        }
        
        return null;
    }
}
