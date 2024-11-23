<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use AaronicSubstances\Kabomu\Abstractions\CustomTimeoutScheduler;
use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpRequest;
use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpResponse;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpRequest;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;
use AaronicSubstances\Kabomu\Exceptions\ExpectationViolationException;
use AaronicSubstances\Kabomu\Exceptions\MissingDependencyException;
use AaronicSubstances\Kabomu\Exceptions\QuasiHttpException;
use AaronicSubstances\Kabomu\Tlv\TlvUtils;

class ProtocolUtilsInternal {

    public static function runTimeoutScheduler(
            CustomTimeoutScheduler $timeoutScheduler, bool $forClient,
            \Closure $proc): ?QuasiHttpResponse {
        $timeoutMsg = $forClient ? "send timeout" : "receive timeout";
        $result = $timeoutScheduler->runUnderTimeout($proc);
        if ($result) {
            $error = $result->getError();
            if ($error) {
                throw $error;
            }
            if ($result->isTimeout()) {
                throw new QuasiHttpException($timeoutMsg,
                    QuasiHttpException::REASON_CODE_TIMEOUT);
            }
        }
        $response = $result?->getResponse();
        if ($forClient && !$response)
        {
            throw new QuasiHttpException(
                "no response from timeout scheduler");
        }
        return $response;
    }

    public static function validateHttpHeaderSection(bool $isResponse,
            array $csv) {
        $csvCount = count($csv);
        if (!$csvCount) {
            throw new ExpectationViolationException(
                "expected csv to contain at least the special header");
        }
        $specialHeader = $csv[0];
        $specialHeaderCount = count($specialHeader);
        if ($specialHeaderCount !== 4) {
            throw new ExpectationViolationException(
                "expected special header to have 4 values " .
                "instead of $specialHeaderCount");
        }
        for ($i = 0; $i < $specialHeaderCount; $i++) {
            $item = $specialHeader[$i];
            if (!self::containsOnlyPrintableAsciiChars($item, $isResponse && $i === 2)) {
                throw new QuasiHttpException(
                    "quasi http " .
                    ($isResponse ? "status" : "request") .
                    " line field contains spaces, newlines or " .
                    "non-printable ASCII characters: " .
                    $item,
                    QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
            }
        }
        for ($i = 1; $i < $csvCount; $i++) {
            $row = $csv[$i];
            $rowCount = count($row);
            if ($rowCount < 2) {
                throw new ExpectationViolationException(
                    "expected row to have at least 2 values " .
                    "instead of $rowCount");
            }
            $headerName = $row[0];
            if (preg_match('/^\s*[+-]?[0-9]/', $headerName)) {
                throw new QuasiHttpException(
                    "quasi http header name cannot start with a number: $headerName",
                    QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
            }
            if (!self::containsOnlyHeaderNameChars($headerName)) {
                throw new QuasiHttpException(
                    "quasi http header name contains characters " .
                    "other than hyphen and English alphabets: $headerName",
                    QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
            }
            for ($j = 1; $j < $rowCount; $j++) {
                $headerValue = $row[$j];
                if (!self::containsOnlyPrintableAsciiChars($headerValue, true)) {
                    throw new QuasiHttpException(
                        "quasi http header value contains newlines or " .
                        "non-printable ASCII characters: $headerValue",
                        QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
                }
            }
        }
    }

    public static function containsOnlyHeaderNameChars(string $v): bool {
        $vLen = strlen($v);
        for ($i = 0; $i < $vLen; $i++) {
            $c = ord($v[$i]);
            if ($c >= 0x30 && $c <= 0x39) {
                // digits
            }
            else if ($c >= 0x41 && $c <= 0x5a) {
                // upper case
            }
            else if ($c >= 0x61 && $c <= 0x7a) {
                // lower case
            }
            else if ($c === 0x2d) {
                // hyphen
            }
            else {
                return false;
            }
        }
        return true;
    }

    public static function containsOnlyPrintableAsciiChars(string $v,
            bool $allowSpace): bool {
        $vLen = strlen($v);
        for ($i = 0; $i < $vLen; $i++) {
            $c = ord($v[$i]);
            if ($c < 0x20 || $c > 0x7e) {
                return false;
            }
            if (!$allowSpace && $c === 0x20) {
                return false;
            }
        }
        return true;
    }

    public static function encodeQuasiHttpHeaders(bool $isResponse,
            ?array $reqOrStatusLine, ?array $remainingHeaders): string {
        if (!$reqOrStatusLine) {
            throw new \InvalidArgumentException("reqOrStatusLine is null");
        }
        $csv = [];
        $specialHeader = array();
        foreach ($reqOrStatusLine as $v) {
            $specialHeader[] = "$v";
        }
        $csv[] = $specialHeader;
        if ($remainingHeaders) {
            foreach ($remainingHeaders as $key => $value) {
                if ($key === null || $key === "") {
                    throw new QuasiHttpException(
                        "quasi http header name cannot be empty",
                        QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
                }
                if (!$value) {
                    continue;
                }
                $headerRow = array();
                $headerRow[] = $key;
                foreach ($value as $v) {
                    if ($v === null || $v === "") {
                        throw new QuasiHttpException(
                            "quasi http header value cannot be empty",
                            QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
                    }
                    $headerRow[] = $v;
                }
                $csv[] = $headerRow;
            }
        }

        self::validateHttpHeaderSection($isResponse, $csv);

        $serialized = MiscUtilsInternal::stringToBytes(
            CsvUtils::serialize($csv));

        return $serialized;
    }

    public static function decodeQuasiHttpHeaders(bool $isResponse,
            string $buffer, array &$headersReceiver): array {
        try {
            $csv = CsvUtils::deserialize(MiscUtilsInternal::bytesToString(
                $buffer));
        }
        catch (\Throwable $e) {
            throw new QuasiHttpException(
                "invalid quasi http headers",
                QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION,
                $e);
        }
        $csvCount = count($csv);
        if (!$csvCount) {
            throw new QuasiHttpException(
                "invalid quasi http headers",
                QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
        }
        $specialHeader = $csv[0];
        if (count($specialHeader) < 4) {
            throw new QuasiHttpException(
                "invalid quasi http " .
                ($isResponse ? "status" : "request") .
                " line",
                QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
        }

        // merge headers with the same normalized name in different rows.
        for ($i = 1; $i < $csvCount; $i++) {
            $headerRow = $csv[$i];
            $headerRowCount = count($headerRow);
            if ($headerRowCount < 2) {
                continue;
            }
            $headerName = strtolower($headerRow[0]);
            if (!array_key_exists($headerName, $headersReceiver)) {
                $headersReceiver[$headerName] = array();
            }
            $headerValues = &$headersReceiver[$headerName];
            for ($j = 1; $j < $headerRowCount; $j++) {
                $headerValues[] = $headerRow[$j];
            }
        }

        return $specialHeader;
    }

    public static function writeQuasiHttpHeaders(
            bool $isResponse,
            $dest,
            array $reqOrStatusLine,
            ?array $remainingHeaders,
            ?int $maxHeadersSize) {
        $encodedHeaders = self::encodeQuasiHttpHeaders($isResponse,
            $reqOrStatusLine, $remainingHeaders);
        if (!$maxHeaderSize || $maxHeadersSize < 0) {
            $maxHeadersSize = QuasiHttpUtils::DEFAULT_MAX_HEADERS_SIZE;
        }

        // finally check that byte count of csv doesn't exceed limit.
        $encodedHeadersLen = strlen($encodedHeaders);
        if ($encodedHeadersLen > $maxHeadersSize) {
            throw new QuasiHttpException("quasi http headers exceed " .
                "max size ($encodedHeadersLen > $maxHeadersSize)",
                QuasiHttpException::REASON_CODE_MESSAGE_LENGTH_LIMIT_EXCEEDED);
        }
        $tagAndLen = TlvUtils::encodeTagAndLength(
            TlvUtils::TAG_FOR_QUASI_HTTP_HEADERS, $encodedHeadersLen);
        $dest->write($tagAndLen);
        $dest->write($encodedHeaders);
    }

    public static function readQuasiHttpHeaders(
            bool $isResponse,
            $src,
            array &$srcLeftOver,
            array &$headersReceiver,
            ?int $maxHeadersSize): array {
        $tagOrLen = IOUtilsInternal::readBytesAtLeast($src, $srcLeftOver, 8);
        $tag = TlvUtils::decodeTag($tagOrLen, 0);
        if ($tag !== TlvUtils::TAG_FOR_QUASI_HTTP_HEADERS) {
            throw new QuasiHttpException(
                "unexpected quasi http headers tag: $tag",
                QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
        }
        $headersSize = TlvUtils::decodeLength($tagOrLen, 4);
        if (!$maxHeadersSize || $maxHeadersSize < 0) {
            $maxHeadersSize = QuasiHttpUtils::DEFAULT_MAX_HEADERS_SIZE;
        }
        if ($headersSize > $maxHeadersSize) {
            throw new QuasiHttpException("quasi http headers exceed " .
                "max size ($headersSize > $maxHeadersSize)",
                QuasiHttpException::REASON_CODE_MESSAGE_LENGTH_LIMIT_EXCEEDED);
        }
        $encodedHeaders = IOUtilsInternal::readBytesAtLeast($src, $srcLeftOver, $headersSize);
        return self::decodeQuasiHttpHeaders($isResponse, $encodedHeaders, $headersReceiver);
    }

    public static function writeEntityToTransport(bool $isResponse,
            QuasiHttpRequest|QuasiHttpResponse $entity,
            $writableStream,
            QuasiHttpConnection $connection) {
        if (!$writableStream) {
            throw new MissingDependencyException(
                "no writable stream found for transport");
        }
        if ($isResponse) {
            $response = $entity;
            $headers = $response->getHeaders();
            $body = $response->getBody();
            $contentLength = $response->getContentLength();
            $reqOrStatusLine = [
                $response->getHttpVersion(),
                $response->getStatusCode(),
                $response->getHttpStatusMessage(),
                $contentLength
            ];
        }
        else {
            $request = $entity;
            $headers = $request->getHeaders();
            $body = $request->getBody();
            $contentLength = $request->getContentLength();
            $reqOrStatusLine = [
                $request->getHttpMethod(),
                $request->getTarget(),
                $request->getHttpVersion(),
                $contentLength
            ];
        }
        // treat content lengths totally separate from body.
        // This caters for the HEAD method
        // which can be used to return a content length without a body
        // to download.
        $maxHeadersSize = $connection->getProcessingOptions()?->getMaxHeadersSize();
        self::writeQuasiHttpHeaders($isResponse, $writableStream,
            $reqOrStatusLine, $headers, $maxHeadersSize);
        if (!$body) {
            // don't proceed, even if content length is not zero.
            return;
        }
        if ($contentLength > 0) {
            // don't enforce positive content lengths when writing out
            // quasi http bodies
            IOUtilsInternal::copy($body, $writableStream);
        }
        else {
            // proceed, even if content length is 0.
            $encodedBody = TlvUtils::createTlvEncodingReadableStream(
                $body, TlvUtils::TAG_FOR_QUASI_HTTP_BODY_CHUNK);
            IOUtilsInternal::copy($encodedBody, $writableStream);
        }
    }

    public static function readEntityFromTransport(
            bool $isResponse, $readableStream,
            QuasiHttpConnection $connection): QuasiHttpRequest|QuasiHttpResponse {
        if ($readableStream) {
            throw new MissingDependencyException(
                "no readable stream found for transport");
        }

        $headersReceiver = [];
        $maxHeadersSize = $connection.getProcessingOptions()?->getMaxHeadersSize();
        
        $srcLeftOver = [];
        $reqOrStatusLine = self::readQuasiHttpHeaders(
            $isResponse,
            $readableStream,
            $srcLeftOver,
            $headersReceiver,
            $maxHeadersSize);

        try {
            $contentLength = MiscUtilsInternal::parseInt48(
                $reqOrStatusLine[3]);
        }
        catch (\Throwable $e) {
            throw new QuasiHttpException(
                "invalid quasi http " .
                ($isResponse ? "response" : "request") +
                " content length",
                QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION,
                $e);
        }
        $body = null;
        if ($contentLength) {
            if ($contentLength > 0) {
                $body = TlvUtils::createContentLengthEnforcingStream(
                    $readableStream, $contentLength, $srcLeftOver);
            }
            else {
                $body = TlvUtils::createTlvDecodingReadableStream($readableStream,
                    TlvUtils::TAG_FOR_QUASI_HTTP_BODY_CHUNK,
                    TlvUtils::TAG_FOR_QUASI_HTTP_BODY_CHUNK_EXT, $srcLeftOver);
            }
        }
        else {
            $unshift = implode($srcLeftOver);
            if ($unshift) {
                $readableStream.unread($unshift);
            }
        }
    
        if ($isResponse) {
            $response = new DefaultQuasiHttpResponse();
            $response->setHttpVersion($reqOrStatusLine[0]);
            try {
                $response->setStatusCode(MiscUtilsInternal::parseInt32(
                    $reqOrStatusLine[1]));
            }
            catch (\Throwable $e) {
                throw new QuasiHttpException(
                    "invalid quasi http response status code",
                    QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION,
                    $e);
            }
            $response::setHttpStatusMessage($reqOrStatusLine[2]);
            $response::setContentLength($contentLength);
            $response::setHeaders($headersReceiver);
            if ($body) {
                $bodySizeLimit = $connection->getProcessingOptions()?->getMaxResponseBodySize();
                if (!$bodySizeLimit || $bodySizeLimit > 0) {
                    $body = TlvUtils::createMaxLengthEnforcingStream($body,
                        $bodySizeLimit);
                }
                // can't implement response buffering, because of
                // the HEAD method, with which a content length may
                // be given but without a body to download.
            }
            $response->setBody($body);
            return $response;
        }
        else {
            $request = new DefaultQuasiHttpRequest();
            $request->setEnvironment($connection->getEnvironment());
            $request->setHttpMethod($reqOrStatusLine[0]);
            $request->setTarget($reqOrStatusLine[1]);
            $request->setHttpVersion($reqOrStatusLine[2]);
            $request->setContentLength($contentLength);
            $request->setHeaders($headersReceiver);
            $request->setBody($body);
            return $request;
        }
    }
}