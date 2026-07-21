<?php

declare(strict_types=1);

use Hapa\Core\Bootstrap;
use Hapa\Core\Http\HttpResponsePolicy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

$request = Request::createFromGlobals();
if ($request->isMethod('GET') && $request->getPathInfo() === '/health/live') {
    $correlationId = bin2hex(random_bytes(16));
    $response = new JsonResponse(['status' => 'ok', 'correlation_id' => $correlationId]);
    (new HttpResponsePolicy())->apply($response, $correlationId)->send();

    return;
}

try {
    $bootstrap = Bootstrap::initialize($basePath);
    $response = $bootstrap->kernel()->handle($request);
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
    $response = (new HttpResponsePolicy())->apply($response, $failureId);
}

$response->send();
