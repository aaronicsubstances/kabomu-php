<?php

if (php_sapi_name() !== 'cli') {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

// uncomment this to test with local source files of kabomu
//require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\StandardQuasiHttpServer;

use AaronicSubstances\Kabomu\Examples\Shared\AppLogger;
use AaronicSubstances\Kabomu\Examples\Shared\FileReceiver;
use AaronicSubstances\Kabomu\Examples\Shared\UnixDomainServerTransport;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$ipcPath = $_ENV['IPC_PATH'] ?? "logs/34dc4fb1-71e0-4682-a64f-52d2635df2f5.sock";

$downloadDirPath = $_ENV['SAVE_DIR'] ?? "logs/server";

$application = new FileReceiver($ipcPath, $downloadDirPath);

$instance = new StandardQuasiHttpServer();
$instance->setApplication($application->processRequest(...));

$defaultProcessingOptions = new DefaultQuasiHttpProcessingOptions();
$defaultProcessingOptions->setTimeoutMillis(5_000);

$transport = new UnixDomainServerTransport($ipcPath);
$transport->setQuasiHttpServer($instance);
$transport->setDefaultProcessingOptions($defaultProcessingOptions);

$instance->setTransport($transport);

try {
    $transport->start();
    AppLogger::info("Started Ipc.FileServer at $ipcPath");

    print "Press ENTER to exit" . PHP_EOL;
    $readableStream = new Amp\ByteStream\ReadableResourceStream(STDIN);
    $readableStream->read();
}
catch (\Throwable $e) {
    AppLogger::error("Fatal error encountered", [ 'exception'=> $e ]);
}
finally {
    AppLogger::debug("Stopping Ipc.FileServer...");
    $transport->stop();
}
