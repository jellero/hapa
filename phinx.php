<?php

declare(strict_types=1);

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeders',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => $_ENV['APP_ENV'] ?? 'development',
        'development' => [
            'adapter' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'postgres',
            'name' => $_ENV['DB_DATABASE'] ?? 'hapa',
            'user' => $_ENV['DB_USERNAME'] ?? 'hapa',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
            'charset' => 'utf8',
        ],
        'testing' => [
            'adapter' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? 'postgres',
            'name' => $_ENV['DB_DATABASE'] ?? 'hapa_test',
            'user' => $_ENV['DB_USERNAME'] ?? 'hapa',
            'pass' => $_ENV['DB_PASSWORD'] ?? '',
            'port' => (int) ($_ENV['DB_PORT'] ?? 5432),
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation',
];
