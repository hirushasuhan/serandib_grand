<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $logDir = STORAGE_PATH . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context) : '';
        $formatted = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        @file_put_contents($logFile, $formatted, FILE_APPEND);
    }

    public static function error(Throwable|string $error, array $context = []): void
    {
        if ($error instanceof Throwable) {
            $msg = $error->getMessage() . " in " . $error->getFile() . ":" . $error->getLine();
            $context['trace'] = $error->getTraceAsString();
            self::log('ERROR', $msg, $context);
        } else {
            self::log('ERROR', $error, $context);
        }
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }
}
