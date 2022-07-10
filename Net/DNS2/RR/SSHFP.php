<?php /** @noinspection PhpUnused */


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
 * @since     File available since Release 0.6.0
 *
 */

/**
 * SSHFP Resource Record - RFC4255 section 3.1
 *
 *       0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 *      +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *      |   algorithm   |    fp type    |                               /
 *      +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+                               /
 *      /                                                               /
 *      /                          fingerprint                          /
 *      /                                                               /
 *      +-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
 *
 */
class Net_DNS2_RR_SSHFP extends Net_DNS2_RR
{
    /*
     * the algorithm used
     */
    public int $algorithm;

    /*
     * The fingerprint type
     */
    public int $fp_type;

    /*
     * the fingerprint data
     */
    public string $fingerprint;

    /*
     * Algorithms
     */
    public const SSHFP_ALGORITHM_RES = 0;
    public const SSHFP_ALGORITHM_RSA = 1;
    public const SSHFP_ALGORITHM_DSS = 2;
    public const SSHFP_ALGORITHM_ECDSA = 3;
    public const SSHFP_ALGORITHM_ED25519 = 4;

    /*
     * Fingerprint Types
     */
    public const SSHFP_FPTYPE_RES = 0;
    public const SSHFP_FPTYPE_SHA1 = 1;
    public const SSHFP_FPTYPE_SHA256 = 2;


    /**
     * method to return the rdata portion of the packet as a string
     *
     * @return  string
     * @access  protected
     *
     */
    protected function rrToString() : string
    {
        return $this->algorithm . ' ' . $this->fp_type . ' ' . $this->fingerprint;
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
        //
        // "The use of mnemonics instead of numbers is not allowed."
        // 
        // RFC4255 section 3.2
        //
        $algorithm      = (int) array_shift($rdata);
        $fp_type        = (int) array_shift($rdata);
        $fingerprint    = strtolower(implode('', $rdata));

        //
        // There are only two algorithms defined
        //
        if ( ($algorithm != self::SSHFP_ALGORITHM_RSA) 
            && ($algorithm != self::SSHFP_ALGORITHM_DSS) 
            && ($algorithm != self::SSHFP_ALGORITHM_ECDSA) 
            && ($algorithm != self::SSHFP_ALGORITHM_ED25519)
        ) {
            return false;
        }

        //
        // there are only two fingerprints defined
        //
        if ( ($fp_type != self::SSHFP_FPTYPE_SHA1)
            && ($fp_type != self::SSHFP_FPTYPE_SHA256) 
        ) {
            return false;
        }

        $this->algorithm    = $algorithm;
        $this->fp_type      = $fp_type;
        $this->fingerprint  = $fingerprint;

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
            // unpack the algorithm and fingerprint type
            //
            $x = unpack('Calgorithm/Cfp_type', $this->rdata);

            $this->algorithm    = $x['algorithm'];
            $this->fp_type      = $x['fp_type'];

            //
            // There are only three algorithms defined
            //
            if ( ($this->algorithm != self::SSHFP_ALGORITHM_RSA) 
                && ($this->algorithm != self::SSHFP_ALGORITHM_DSS)
                && ($this->algorithm != self::SSHFP_ALGORITHM_ECDSA)
                && ($this->algorithm != self::SSHFP_ALGORITHM_ED25519)
            ) {
                return false;
            }

            //
            // there are only two fingerprints defined
            //
            if ( ($this->fp_type != self::SSHFP_FPTYPE_SHA1)
                && ($this->fp_type != self::SSHFP_FPTYPE_SHA256)
            ) {
                return false;
            }
            
            //
            // parse the fingerprint; this assumes SHA-1
            //
            $fp = unpack('H*a', substr($this->rdata, 2));
            $this->fingerprint = strtolower($fp['a']);

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
        if (strlen($this->fingerprint) > 0) {

            $data = pack(
                'CCH*', $this->algorithm, $this->fp_type, $this->fingerprint
            );

            $packet->offset += strlen($data);

            return $data;
        }

        return null;
    }
}
