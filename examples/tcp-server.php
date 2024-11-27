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
use AaronicSubstances\Kabomu\Examples\Shared\LocalhostTcpServerTransport;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$port = array_key_exists('TCP_PORT', $_ENV) ?
    intval($_ENV['TCP_PORT']) :
    5001;

$downloadDirPath = $_ENV['SAVE_DIR'] ?? "logs/server";

$application = new FileReceiver($port, $downloadDirPath);

$instance = new StandardQuasiHttpServer();
$instance->setApplication($application->processRequest(...));

$defaultProcessingOptions = new DefaultQuasiHttpProcessingOptions();
$defaultProcessingOptions->setTimeoutMillis(5_000);

$transport = new LocalhostTcpServerTransport($port);
$transport->setQuasiHttpServer($instance);
$transport->setDefaultProcessingOptions($defaultProcessingOptions);

$instance->setTransport($transport);

try {
    $transport->start();
    AppLogger::info("Started Tcp.FileServer at $port");

    print "Press ENTER to exit" . PHP_EOL;
    $readableStream = new Amp\ByteStream\ReadableResourceStream(STDIN);
    $readableStream->read();
}
catch (\Throwable $e) {
    AppLogger::error("Fatal error encountered", [ 'exception'=> $e ]);
}
finally {
    AppLogger::debug("Stopping Tcp.FileServer...");
    $transport->stop();
}
