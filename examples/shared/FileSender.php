<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Examples\Shared;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpRequest;
use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\QuasiHttpUtils;
use AaronicSubstances\Kabomu\StandardQuasiHttpClient;

class FileSender {

    public static function startTransferringFiles(
            StandardQuasiHttpClient $instance, $serverEndpoint,
            string $uploadDirPath) {
        $directory = realpath($uploadDirPath);
        $count = 0;
        $bytesTransferred = 0;
        $startTime = hrtime();
        $listOfFiles = \Amp\File\listFiles($directory);
        foreach ($listOfFiles as $f) {
            $f = $directory . DIRECTORY_SEPARATOR . $f;
            if (!\Amp\File\isFile($f)) {
                continue;
            }
            AppLogger::debug("Transferring $f");
            self::transferFile($instance, $serverEndpoint, $f);
            AppLogger::info("Successfully transferred $f");
            $bytesTransferred += \Amp\File\getSize($f);
            $count++;
        }
        $endTime = hrtime();
        $timeTaken = $endTime[0] - $startTime[0] + ($endTime[1] - $startTime[1]) / 1e9;
        $timeTaken = round($timeTaken, 2);
        $megaBytesTransferred = round($bytesTransferred / (1024.0 * 1024.0), 2);
        $rate = round($megaBytesTransferred / $timeTaken, 2);
        AppLogger::info("Successfully transferred $bytesTransferred bytes ($megaBytesTransferred MB) " .
            "worth of data in $count files in $timeTaken seconds = $rate MB/s");
    }

    private static function transferFile(StandardQuasiHttpClient $instance, $serverEndpoint, string $f) {
        $request = new DefaultQuasiHttpRequest();
        $requestHeaders = [];
        $encodedName = base64_encode(MiscUtilsInternal::stringToBytes(basename($f)));
        $requestHeaders["f"] = [ $encodedName ];
        $echoBodyOn = rand(0, 1);
        if ($echoBodyOn) {
            $encodedName = base64_encode(MiscUtilsInternal::stringToBytes($f));
            $requestHeaders["echo-body"] = [ $encodedName ];
        }
        $request->setHeaders($requestHeaders);

        // add body.
        $fileStream = \Amp\File\openFile($f, 'r');
        $request->setBody($fileStream);
        $request->setContentLength(-1);
        if (rand(0, 1)) {
            $request->setContentLength(\Amp\File\getSize($f));
        }

        // determine options
        $sendOptions = null;
        if (rand(0, 1)) {
            $sendOptions = new DefaultQuasiHttpProcessingOptions();
            $sendOptions->setMaxResponseBodySize(-1);
        }

        $res = null;
        try {
            if (rand(0, 1)) {
                $res = $instance->send($serverEndpoint, $request, $sendOptions);
            }
            else {
                $res = $instance->send2($serverEndpoint, fn($env) => $request, $sendOptions);
            }
            if ($res->getStatusCode() === QuasiHttpUtils::STATUS_CODE_OK) {
                if ($echoBodyOn) {
                    $actualResBody = \Amp\ByteStream\buffer($res->getBody());
                    $actualResBody = MiscUtilsInternal::bytesToString(
                        base64_decode($actualResBody, true));
                    if ($actualResBody !== $f) {
                        throw new \Exception("expected echo body to be $actualResBody but got $f");
                    }
                }
                AppLogger::info("File $f sent successfully");
            }
            else {
                $responseMsg = "";
                if ($res->getBody()) {
                    try {
                        $responseMsg = MiscUtilsInternal::bytesToString(
                            \Amp\ByteStream\buffer($res->getBody()));
                    }
                    catch (\Exception $ignore) {}
                }
                throw new \Exception("status code indicates error: " . $res->getStatusCode() . "\n$responseMsg");
            }
        }
        catch (\Throwable $e) {
            AppLogger::warning("File $f sent with error: " . $e->getMessage());
            throw $e;
        }
        finally {
            $fileStream->close();
            $res?->release();
        }
    }
}