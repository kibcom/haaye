<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $configPath): void
    {
        $files = ['app', 'database', 'session', 'auth', 'mail'];

        foreach ($files as $file) {
            $full = $configPath . DIRECTORY_SEPARATOR . $file . '.php';
            if (is_file($full)) {
                self::$items[$file] = require $full;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
