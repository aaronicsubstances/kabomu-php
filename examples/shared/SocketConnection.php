<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Examples\Shared;

use Amp\DeferredCancellation;
use Amp\Socket\Socket;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\DefaultTimeoutResult;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;
use AaronicSubstances\Kabomu\QuasiHttpUtils;

class SocketConnection implements QuasiHttpConnection {
    private readonly Socket $socket;
    private readonly ?DeferredCancellation $connectCancellation;
    private readonly QuasiHttpProcessingOptions $processingOptions;
    private readonly ?\Closure $timeoutScheduler;
    private readonly ?DeferredCancellation $timeoutCancellation;

    private ?array $environment = null;

    public function __construct(Socket $socket,
            ?DeferredCancellation $connectCancellation,
            ?QuasiHttpProcessingOptions $processingOptions,
            ?QuasiHttpProcessingOptions $fallbackProcessingOptions) {
        $this->socket = $socket;
        $this->connectCancellation = $connectCancellation;

        $effectiveProcessingOptions = QuasiHttpUtils::mergeProcessingOptions(
            $processingOptions, $fallbackProcessingOptions);
        if (!$effectiveProcessingOptions) {
            $effectiveProcessingOptions = new DefaultQuasiHttpProcessingOptions();
        }
        $this->processingOptions = $effectiveProcessingOptions;

        $timeoutMillis = $effectiveProcessingOptions->getTimeoutMillis();
        if ($timeoutMillis <= 0) {
            $this->timeoutScheduler = null;
            $this->timeoutCancellation = null;
        }
        else {
            $this->timeoutCancellation = new DeferredCancellation;
            $this->timeoutScheduler = function($proc) use($timeoutMillis) {
                $task1 = \Amp\async(function() use($timeoutMillis) {
                    try {
                        \Amp\delay($timeoutMillis / 1_000, true, $this->timeoutCancellation->getCancellation());
                        $timeoutResult = new DefaultTimeoutResult(true, null, null);
                    }
                    catch (CancelledException $ignore) {
                        $timeoutResult = new DefaultTimeoutResult(false, null, $ignore);
                    }
                    return $timeoutResult;
                });
                $task2 = \Amp\async(function() use($proc) {
                    try {
                        $res = $proc();
                        $runResult = new DefaultTimeoutResult(false, $res, null);
                    }
                    catch (\Throwable $e) {
                        $runResult = new DefaultTimeoutResult(false, null, $e);
                    }
                    return $runResult;
                });
                $res = \Amp\Future\awaitFirst([$task1, $task2]);
                $this->timeoutCancellation->cancel();
                return $res;
            };
        }
    }

    public function release(?QuasiHttpResponse $response) {
        $this->connectCancellation?->cancel();
        $this->timeoutCancellation?->cancel();
        if ($response?->getBody()) {
            return;
        }
        $this->socket->close();
    }

    public function getStream() {
        return $this->socket;
    }

    function getProcessingOptions(): ?QuasiHttpProcessingOptions {
        return $this->processingOptions;
    }

    function getTimeoutScheduler(): ?\Closure {
        return $this->timeoutScheduler;
    }

    function getEnvironment(): ?array {
        return $this->environment;
    }

    function setEnvironment(?array $environment) {
        $this->environment = $environment;
    }
}