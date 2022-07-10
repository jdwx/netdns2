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


use JDWX\DNSQuery\Lookups;
use JDWX\DNSQuery\Resolver;
use JDWX\DNSQuery\RR\MX;


require_once 'Net_DNS2.php';

/**
 * This test uses the Google public DNS servers to perform a resolution test;
 * this should work on *nix and Windows, but will require an internet connection.
 *
 */
class Tests_Net_DNS2_ResolverTest extends PHPUnit\Framework\TestCase
{
    /**
     * function to test the resolver
     *
     * @return void
     * @access public
     *
     * @throws JDWX\DNSQuery\Exception
     */
    public function testResolver() : void {
        $ns = [ '8.8.8.8', '8.8.4.4' ];

        $r = new Resolver([ 'nameservers' => $ns ]);

        $result = $r->query('google.com', 'MX');

        static::assertSame($result->header->qr, Lookups::QR_RESPONSE);
        static::assertSame(count($result->question), 1);
        static::assertNotEmpty( $result->answer );
        static::assertTrue($result->answer[0] instanceof MX);
    }
}
