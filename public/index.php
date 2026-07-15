<?php

declare(strict_types=1);

use Hapa\Core\Bootstrap\Bootstrap;
use Hapa\Core\KernelFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

try {
    $context = (new Bootstrap())->initialize($basePath);
    $request = Request::createFromGlobals();
    $response = (new KernelFactory())->create($basePath, $context)->handle($request);
} catch (Throwable $exception) {
    try {
        $incidentId = bin2hex(random_bytes(8));
    } catch (Throwable) {
        $incidentId = 'bootstrap-unavailable';
    }

    error_log(sprintf(
        'HAPA bootstrap failure [%s]: %s (%s)',
        $incidentId,
        $exception::class,
        (string) $exception->getCode(),
    ));
    $response = new JsonResponse(
        ['error' => 'Servizio non disponibile', 'incident_id' => $incidentId],
        Response::HTTP_SERVICE_UNAVAILABLE,
    );
}

$response->send();
