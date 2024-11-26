<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Examples\Shared;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

class AppLogger {
    private static bool $initialized = false;
    private static ?Logger $logger = null;

    public static function __init__() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        self::$logger = new Logger('my_logger');

        // Now add some handlers
        $stream = new StreamHandler('php://stdout', Level::Debug);

        // configure logging exception stack traces
        $formatter = new LineFormatter(LineFormatter::SIMPLE_FORMAT, LineFormatter::SIMPLE_DATE);
        $formatter->includeStacktraces(true);
        $stream->setFormatter($formatter);

        self::$logger->pushHandler($stream);
    }

    public static function debug(...$args) {
        self::$logger->debug(...$args);
    }

    public static function info(...$args) {
        self::$logger->info(...$args);
    }

    public static function warning(...$args) {
        self::$logger->warning(...$args);
    }

    public static function error(...$args) {
        self::$logger->error(...$args);
    }
}

AppLogger::__init__();