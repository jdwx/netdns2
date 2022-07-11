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
 * @since     File available since Release 1.0.0
 *
 */


namespace JDWX\DNSQuery\tests;


use JDWX\DNSQuery\Exception;
use JDWX\DNSQuery\Resolver;
use JDWX\DNSQuery\RR\OPT;
use PHPUnit\Framework\TestCase;


/**
 * Test class to test the DNSSEC logic
 *
 */
class LegacyDNSSECTest extends TestCase {
    /**
     * function to test the TSIG logic
     *
     * @return void
     * @access public
     *
     * @throws Exception
     */
    public function testDNSSEC() : void {
        $ns = [ '8.8.8.8', '8.8.4.4' ];

        $r = ( new Resolver( $ns ) )->setDNSSEC();

        $result = $r->query( 'org', 'SOA' );

        static::assertTrue( ( $result->header->ad == 1 ) );
        $add = $result->additional[ 0 ];
        assert( $add instanceof OPT );
        static::assertInstanceOf( OPT::class, $add );
        static::assertTrue( ( $add->do == 1 ) );
    }
}
