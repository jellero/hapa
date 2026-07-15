<?php

declare(strict_types=1);

use RuntimeException;

$env = static function (string $name, ?string $default = null): string {
    $value = $_ENV[$name] ?? getenv($name);

    if ($value === false || $value === null || $value === '') {
        if ($default === null) {
            throw new RuntimeException(sprintf('Variabile ambiente obbligatoria assente: %s', $name));
        }

        return $default;
    }

    return (string) $value;
};

$secret = static function (string $name, ?string $default = null) use ($env): string {
    $file = $env($name . '_FILE', '');

    if ($file !== '') {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException(sprintf('Secret file non leggibile per %s.', $name));
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Impossibile leggere il secret file per %s.', $name));
        }

        $value = rtrim($contents, "\r\n");
        if ($value !== '') {
            return $value;
        }

        if ($default === null) {
            throw new RuntimeException(sprintf('Secret file vuoto per %s.', $name));
        }

        return $default;
    }

    return $env($name, $default);
};

$selectedEnvironment = strtolower($env('APP_ENV', 'development'));
if (!in_array($selectedEnvironment, ['development', 'testing', 'production'], true)) {
    throw new RuntimeException(sprintf('Ambiente Phinx non valido: %s', $selectedEnvironment));
}

$password = $secret('DB_PASSWORD', '');
if ($selectedEnvironment === 'production' && strlen($password) < 16) {
    throw new RuntimeException('DB_PASSWORD deve essere configurata esplicitamente in produzione.');
}

$connection = static fn (string $defaultDatabase): array => [
    'adapter' => 'pgsql',
    'host' => $env('DB_HOST', 'postgres'),
    'name' => $env('DB_DATABASE', $defaultDatabase),
    'user' => $env('DB_USERNAME', 'hapa'),
    'pass' => $password,
    'port' => (int) $env('DB_PORT', '5432'),
    'charset' => 'utf8',
];

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeders',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => $selectedEnvironment,
        'development' => $connection('hapa'),
        'testing' => $connection('hapa_test'),
        'production' => $connection('hapa'),
    ],
    'version_order' => 'creation',
];
