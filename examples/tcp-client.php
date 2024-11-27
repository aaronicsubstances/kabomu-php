<?php

if (php_sapi_name() !== 'cli') {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

// uncomment this to test with local source files of kabomu
//require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\StandardQuasiHttpClient;

use AaronicSubstances\Kabomu\Examples\Shared\AppLogger;
use AaronicSubstances\Kabomu\Examples\Shared\FileSender;
use AaronicSubstances\Kabomu\Examples\Shared\LocalhostTcpClientTransport;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$serverPort = array_key_exists('TCP_PORT', $_ENV) ?
    intval($_ENV['TCP_PORT']) :
    5001;

$uploadDirPath = $_ENV['UPLOAD_DIR'] ?? "logs/client";

$defaultSendOptions = new DefaultQuasiHttpProcessingOptions();
$defaultSendOptions->setTimeoutMillis(5_000);

$transport = new LocalhostTcpClientTransport();
$transport->setDefaultSendOptions($defaultSendOptions);

$instance = new StandardQuasiHttpClient();
$instance->setTransport($transport);

try {
    AppLogger::info("Connecting Tcp.FileClient to $serverPort...");

    FileSender::startTransferringFiles($instance, $serverPort, $uploadDirPath);
}
catch (\Throwable $e) {
    AppLogger::error("Fatal error encountered", [ 'exception'=> $e ]);
}
