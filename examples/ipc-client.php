<?php

if (php_sapi_name() !== 'cli') {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

// uncomment this to test with local source files of kabomu
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\StandardQuasiHttpClient;

use AaronicSubstances\Kabomu\Examples\Shared\AppLogger;
use AaronicSubstances\Kabomu\Examples\Shared\FileSender;
use AaronicSubstances\Kabomu\Examples\Shared\UnixDomainClientTransport;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$serverIpcPath = $_ENV['IPC_PATH'] ?? "logs/34dc4fb1-71e0-4682-a64f-52d2635df2f5.sock";

$uploadDirPath = $_ENV['UPLOAD_DIR'] ?? "logs/client";

$defaultSendOptions = new DefaultQuasiHttpProcessingOptions();
$defaultSendOptions->setTimeoutMillis(5_000);

$transport = new UnixDomainClientTransport();
$transport->setDefaultSendOptions($defaultSendOptions);

$instance = new StandardQuasiHttpClient();
$instance->setTransport($transport);

try {
    AppLogger::info("Connecting Ipc.FileClient to $serverIpcPath...");

    FileSender::startTransferringFiles($instance, $serverIpcPath, $uploadDirPath);
}
catch (\Throwable $e) {
    AppLogger::error("Fatal error encountered", [ 'exception'=> $e ]);
}
