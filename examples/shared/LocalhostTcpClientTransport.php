<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Examples\Shared;

use Amp\DeferredCancellation;
use Amp\Socket\ConnectContext;
use function Amp\Socket\connect;

use AaronicSubstances\Kabomu\Abstractions\QuasiHttpClientTransport;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;

class LocalhostTcpClientTransport implements QuasiHttpClientTransport {
    private ?QuasiHttpProcessingOptions $defaultSendOptions;

    public function __construct() {

    }

    public function getDefaultSendOptions(): ?QuasiHttpProcessingOptions {
        return $this->defaultSendOptions;
    }

    public function setDefaultSendOptions(?QuasiHttpProcessingOptions $defaultSendOptions) {
        $this->defaultSendOptions = $defaultSendOptions;
    }

    function allocateConnection($remoteEndpoint, ?QuasiHttpProcessingOptions $sendOptions): ?QuasiHttpConnection {
        $port = intval($remoteEndpoint);
        $connectContext = (new ConnectContext)->withTcpNoDelay();
        $deferredCancellation = new DeferredCancellation();
        $socket = connect("[::1]:$port", $connectContext, $deferredCancellation->getCancellation());
        $connection = new SocketConnection($socket, $deferredCancellation,
            $sendOptions, $this->defaultSendOptions);
        return $connection;
    }

    function establishConnection(QuasiHttpConnection $connection) {
    }

    function releaseConnection(QuasiHttpConnection $connection, ?QuasiHttpResponse $response) {
        $connection->release($response);
    }

    function getReadableStream(QuasiHttpConnection $connection) {
        return $connection->getStream();
    }
    
    function getWritableStream(QuasiHttpConnection $connection) {
        return $connection->getStream();
    }
}