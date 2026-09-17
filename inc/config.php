<?php

declare(strict_types=1);

/**
 * Simple .env loader (no Composer dependency), zodat DB_USER/DB_PASSWORD
 * nooit in code terechtkomen. In productie kun je deze waarden ook direct
 * als echte omgevingsvariabelen op de webserver zetten - dan wordt .env
 * genegeerd voor de sleutels die al bestaan.
 */
function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

loadEnvFile(__DIR__ . '/../.env');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

return [
    'db' => [
        // Zie README.md - "Database-login aanmaken" voor het aanmaken van
        // een aparte, read-only SQL-server login voor deze webapp.
        'host'     => env('DB_HOST', 'GEEVE-SQL-2019'),
        'port'     => env('DB_PORT'),
        'name'     => env('DB_NAME', 'Slangkaarten'),
        'user'     => env('DB_USER'),
        'password' => env('DB_PASSWORD'),
    ],
];
