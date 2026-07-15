<?php

declare(strict_types=1);

use Hapa\Core\Configuration\Environment;
use Hapa\Core\KernelFactory;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

if (is_file($basePath . '/.env')) {
    (new Dotenv())->usePutenv()->loadEnv($basePath . '/.env');
}

try {
    $environment = Environment::load();
    date_default_timezone_set($environment->timezone);

    $request = Request::createFromGlobals();
    $response = (new KernelFactory())->create($basePath, $environment)->handle($request);
} catch (Throwable $exception) {
    error_log(sprintf('HAPA bootstrap failure: %s', $exception->getMessage()));
    $response = new JsonResponse(['error' => 'Servizio non disponibile'], Response::HTTP_SERVICE_UNAVAILABLE);
}

$response->send();
