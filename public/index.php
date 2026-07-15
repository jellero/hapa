<?php

declare(strict_types=1);

use Hapa\Core\Bootstrap;
use Hapa\Core\KernelFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

try {
    $bootstrap = Bootstrap::initialize($basePath);
    $request = Request::createFromGlobals();
    $response = (new KernelFactory())->create($basePath, $bootstrap)->handle($request);
} catch (Throwable $exception) {
    $failureId = bin2hex(random_bytes(8));
    error_log(sprintf(
        'HAPA bootstrap failure id=%s exception=%s code=%s',
        $failureId,
        $exception::class,
        (string) $exception->getCode(),
    ));
    $response = new JsonResponse(
        ['error' => 'Servizio non disponibile', 'failure_id' => $failureId],
        Response::HTTP_SERVICE_UNAVAILABLE,
    );
}

$response->send();
