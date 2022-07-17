<?php


declare( strict_types = 1 );


namespace JDWX\DNSQuery\tests;


use JDWX\DNSQuery\Exception;
use JDWX\DNSQuery\Lookups;
use JDWX\DNSQuery\Network\UDPTransport;
use JDWX\DNSQuery\Packet\RequestPacket;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionObject;


/** Test the UDPTransport class. */
class UDPTransportTest extends TestCase {


    /**
     * @throws Exception
     */
    public function testUDPTransport() {
        $req = new RequestPacket( "google.com", "MX" );
        $udp = new UDPTransport( "1.1.1.1" );
        $udp->sendRequest( $req );
        $rsp = $udp->receiveResponse();
        static::assertSame( $req->header->id, $rsp->header->id );
        ResolverTest::googleMXResponseCheck( $rsp );
    }


    /**
     * @throws Exception
     */
    public function testUDPTransportReuse() {

        $req = new RequestPacket( 'google.com', 'A' );
        $udp = new UDPTransport( '1.1.1.1' );
        $udp->sendRequest( $req );
        $rsp = $udp->receiveResponse();
        static::assertSame( $req->header->id, $rsp->header->id );

        $req2 = new RequestPacket( 'google.com', 'MX' );
        $udp->sendRequest( $req2 );
        $rsp2 = $udp->receiveResponse();
        static::assertSame( $req2->header->id, $rsp2->header->id );
        ResolverTest::googleMXResponseCheck( $rsp2 );

    }


    /**
     * @throws \Exception
     */
    public function testUDPTransportSpecificPort() {
        $port = random_int( 2048, 65535 );
        $req = new RequestPacket( 'google.com', 'MX' );
        $udp = new UDPTransport( '1.1.1.1', i_localHost: '0.0.0.0', i_localPort: $port );
        $udp->sendRequest( $req );
        $rsp = $udp->receiveResponse();
        ResolverTest::googleMXResponseCheck( $rsp );
    }



    /** @suppress PhanNoopNew */
    public function testUDPTransportSocketOpenError() {
        $this->expectException( Exception::class );
        new UDPTransport( 'foo.bar.baz' );
    }


    /**
     * @throws Exception
     * @suppress PhanTypeMismatchArgument Because of mock object
     */
    public function testUDPTransportSocketWriteError() {
        $req = $this->getMockBuilder( RequestPacket::class )
            ->onlyMethods([ 'get' ])
            ->setConstructorArgs( [ 'google.com', 'MX' ] )
            ->getMock();
        // $req = new RequestPacket( 'google.com', 'MX' );
        $udp = new UDPTransport( '1.1.1.1' );
        $this->expectException( Exception::class );
        $udp->sendRequest( $req );
    }


    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testUDPTransportSocketReadError() {
        $req = new RequestPacket( "google.com", "MX" );
        $udp = new UDPTransport( "1.1.1.1" );
        $udp->sendRequest( $req );

        ## Hack in and shut down socket reads.
        $refUDP = new ReflectionObject( $udp );
        $refSocket = $refUDP->getProperty( 'socket' );
        $socket = $refSocket->getValue( $udp );
        $refSocket = new ReflectionObject( $socket );
        $refSock = $refSocket->getProperty( 'sock' );
        $sock = $refSock->getValue( $socket );
        stream_socket_shutdown( $sock, STREAM_SHUT_RD );

        $this->expectException( Exception::class );
        $udp->receiveResponse();

    }


    /**
     * @throws \Exception
     */
    public function testUDPTransportSocketReadTooShort() {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $port = random_int( 2048, 65535 );
        socket_bind( $socket, '127.0.0.1', $port );

        $req = new RequestPacket( 'google.com', 'MX' );
        $udp = new UDPTransport( '127.0.0.1', $port );
        $udp->sendRequest( $req );
        socket_recvfrom( $socket, $buf, Lookups::DNS_MAX_UDP_SIZE, 0, $clientIP, $clientPort );
        socket_sendto($socket, "Nope!", 5, 0, $clientIP, $clientPort );
        socket_close( $socket );

        $this->expectException( Exception::class );
        $udp->receiveResponse();
    }


}

