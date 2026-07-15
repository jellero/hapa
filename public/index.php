<?php

declare(strict_types=1);

use Hapa\Core\KernelFactory;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

if (is_file($basePath . '/.env')) {
    (new Dotenv())->usePutenv()->loadEnv($basePath . '/.env');
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Rome');

$request = Request::createFromGlobals();
$response = (new KernelFactory())->create($basePath)->handle($request);
$response->send();
