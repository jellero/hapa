<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class SpacePurchaseController
{
    public function __construct(private SpacePurchaseManagement $purchases)
    {
    }

    public function generate(Request $request): Response
    {
        $orderNumber = strtoupper(trim($request->attributes->getString('orderId')));
        $path = '/ui/orders/' . rawurlencode($orderNumber);

        try {
            $this->purchases->generateForOrder(
                $orderNumber,
                $request->attributes->getString('correlation_id'),
            );

            return new RedirectResponse($path . '?purchase_generated=1#purchases', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return new RedirectResponse(
                $path . '?purchase_error=' . rawurlencode($exception->getMessage()) . '#purchases',
                Response::HTTP_SEE_OTHER,
            );
        } catch (Throwable) {
            return new RedirectResponse(
                $path . '?purchase_error=' . rawurlencode('Impossibile generare l\'acquisto Space.') . '#purchases',
                Response::HTTP_SEE_OTHER,
            );
        }
    }
}
