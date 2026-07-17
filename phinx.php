<?php

declare(strict_types=1);

use Hapa\Core\Configuration\EnvironmentReader;

require_once __DIR__ . '/vendor/autoload.php';

$selectedEnvironment = strtolower(EnvironmentReader::value('APP_ENV', 'development'));
if (!in_array($selectedEnvironment, ['development', 'testing', 'production'], true)) {
    throw new \RuntimeException(sprintf('Ambiente Phinx non valido: %s', $selectedEnvironment));
}

$password = EnvironmentReader::secret('DB_PASSWORD', '');
if ($selectedEnvironment === 'production' && strlen($password) < 16) {
    throw new \RuntimeException('DB_PASSWORD deve essere configurata esplicitamente in produzione.');
}

$connection = static fn (string $defaultDatabase): array => [
    'adapter' => 'pgsql',
    'host' => EnvironmentReader::value('DB_HOST', 'postgres'),
    'name' => EnvironmentReader::value('DB_DATABASE', $defaultDatabase),
    'user' => EnvironmentReader::value('DB_USERNAME', 'hapa'),
    'pass' => $password,
    'port' => (int) EnvironmentReader::value('DB_PORT', '5432'),
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
