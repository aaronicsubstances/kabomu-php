<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Examples\Shared;

use Amp\ByteStream\ReadableBuffer;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpResponse;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpRequest;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;
use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\QuasiHttpUtils;

class FileReceiver {
    private readonly mixed $remoteEndpoint;
    private readonly string $downloadDirPath;

    public function __construct($remoteEndpoint, string $downloadDirPath) {
        $this->remoteEndpoint = $remoteEndpoint;
        $this->downloadDirPath = $downloadDirPath;
    }

    public function processRequest(QuasiHttpRequest $request): QuasiHttpResponse {
        $fileName = $request->getHeaders()["f"][0];
        $fileName = MiscUtilsInternal::bytesToString(
            base64_decode($fileName, true));
        $fileName = basename($fileName);

        $transferError = null;
        try {
            // ensure directory exists.
            // just in case remote endpoint contains invalid file path characters...
            $pathForRemoteEndpoint = preg_replace('/\W/', '_', "" . $this->remoteEndpoint);
            $directory = $this->downloadDirPath . DIRECTORY_SEPARATOR . $pathForRemoteEndpoint;
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true)) {
                    throw new \Exception("Could not create directory at $directory");
                }
            }
            $p = $directory . DIRECTORY_SEPARATOR . $fileName;
            $fileStream = \Amp\File\openFile($p, 'w');
            try {
                AppLogger::debug("Starting receipt of file $fileName from $this->remoteEndpoint...");
                \Amp\ByteStream\pipe($request->getBody(), $fileStream);
            }
            finally {
                $fileStream->close();
            }
        }
        catch (\Throwable $e) {
            $transferError = $e;
        }

        $response = new DefaultQuasiHttpResponse();
        $responseBody = null;
        if (!$transferError) {
            AppLogger::info("File $fileName received successfully");
            $response->setStatusCode(QuasiHttpUtils::STATUS_CODE_OK);
            if (array_key_exists("echo-body", $request->getHeaders())) {
                $responseBody = implode(",", $request->getHeaders());
            }
        }
        else {
            AppLogger::error("File $fileName received with error", [ 'exception'=> $transferError ]);
            $response->setStatusCode(QuasiHttpUtils::STATUS_CODE_SERVER_ERROR);
            $responseBody = $transferError->getMessage();
        }
        if ($responseBody) {
            $responseBytes = MiscUtilsInternal::stringToBytes($responseBody);
            $response->setBody(new ReadableBuffer($responseBytes));
            $response->setContentLength(-1);
            if (rand(0, 1)) {
                $response->setContentLength(strlen($responseBytes));
            }
        }
        return $response;
    }
}