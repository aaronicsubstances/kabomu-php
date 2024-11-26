<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Examples\Shared;

use Amp\Socket\ServerSocket;
use Amp\Socket\Socket;
use function Amp\Socket\listen;
use function Amp\async;
use function Amp\delay;

use AaronicSubstances\Kabomu\Abstractions\QuasiHttpServerTransport;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\StandardQuasiHttpServer;

class LocalhostTcpServerTransport implements QuasiHttpServerTransport {
    private readonly int $port;

    private ?QuasiHttpProcessingOptions $defaultProcessingOptions = null;
    private ?StandardQuasiHttpServer $quasiHttpServer = null;

    private ?ServerSocket $server = null;

    public function __construct(int $port) {
        $this->port = $port;
    }

    public function start() {
        $this->server = listen("tcp://[::1]:$this->port");
        async(function () {
            $this->acceptConnections();
        });
    }

    public function stop() {
        $this->server?->close();
        delay(1.0);
    }

    private function acceptConnections() {
        try {
            while ($client = $this->server->accept()) {
                async(function () use($client) {
                    $this->receiveConnection($client);
                });
            }
        }
        catch (\Throwable $e) {
            AppLogger::warning("connection accept error", [ 'exception'=> $e ]);
        }
    }

    private function receiveConnection(Socket $socket) {
        try {
            $connection = new SocketConnection($socket, null,
                $this->defaultProcessingOptions, null);
            $this->quasiHttpServer->acceptConnection($connection);
        }
        catch (\Throwable $e) {
            AppLogger::warning("connection processing error", [ 'exception'=> $e ]);
        }
    }
    
    public function getQuasiHttpServer(): ?StandardQuasiHttpServer {
        return $this->quasiHttpServer;
    }

    public function setQuasiHttpServer(?StandardQuasiHttpServer $quasiHttpServer) {
        $this->quasiHttpServer = $quasiHttpServer;
    }

    public function getDefaultProcessingOptions(): ?QuasiHttpProcessingOptions {
        return $this->defaultProcessingOptions;
    }

    public function setDefaultProcessingOptions(?QuasiHttpProcessingOptions $defaultProcessingOptions) {
        $this->defaultProcessingOptions = $defaultProcessingOptions;
    }

    function releaseConnection(QuasiHttpConnection $connection) {
        $connection->release(null);
    }

    function getReadableStream(QuasiHttpConnection $connection) {
        return $connection->getStream();
    }
    
    function getWritableStream(QuasiHttpConnection $connection) {
        return $connection->getStream();
    }
}