<?php

namespace App;

use volkerschulz\CliLogger as CliLogger;

class Logger
{

    public static function info(string|array|object $message): void
    {
        if (is_string($message))
            CliLogger::notice(self::cliTrim($message));
        else
            var_dump($message);
    }

    public static function success(string|array|object $message): void
    {
        if (is_string($message))
            CliLogger::success(self::cliTrim($message));
        else
            var_dump($message);
    }

    public static function warning(string|array|object $message): void
    {
        if (is_string($message))
            CliLogger::warning(self::cliTrim($message));
        else
            var_dump($message);
    }

    public static function error(string|array|object $message): void
    {
        if (is_string($message))
            CliLogger::error(self::cliTrim($message));
        else
            var_dump($message);
    }

    private static function cliTrim(string $message): string
    {
        return date('Y-m-d H:i:s') . " \t $message" . PHP_EOL;
    }
}
