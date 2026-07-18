<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class CatalogReviewController
{
    public function __construct(private CatalogProductManagement $products)
    {
    }

    public function review(Request $request): Response
    {
        try {
            $actor = $request->attributes->get('current_user');
            if (!$actor instanceof UserIdentity) {
                throw new InvalidArgumentException('Attore autenticato non disponibile.');
            }
            $this->products->review(
                $request->attributes->getInt('itemId'),
                $request->request->getInt('version'),
                $request->request->getString('decision'),
                $actor,
                $request->attributes->getString('correlation_id'),
            );

            return new RedirectResponse('/ui/catalog?review_saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | CatalogReviewConflict $exception) {
            return new RedirectResponse(
                '/ui/catalog?review_error=' . rawurlencode($exception->getMessage()),
                Response::HTTP_SEE_OTHER,
            );
        } catch (Throwable) {
            return new RedirectResponse(
                '/ui/catalog?review_error=' . rawurlencode('Impossibile registrare la revisione del prodotto.'),
                Response::HTTP_SEE_OTHER,
            );
        }
    }

    public function updateAvailability(Request $request): Response
    {
        try {
            $actor = $request->attributes->get('current_user');
            if (!$actor instanceof UserIdentity) {
                throw new InvalidArgumentException('Attore autenticato non disponibile.');
            }
            $this->products->updateSafetyStock(
                $request->attributes->getInt('itemId'),
                $request->request->getInt('version'),
                $request->request->getInt('safety_stock'),
                $actor,
                $request->attributes->getString('correlation_id'),
            );

            return new RedirectResponse('/ui/catalog?availability_saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | CatalogReviewConflict $exception) {
            return new RedirectResponse(
                '/ui/catalog?availability_error=' . rawurlencode($exception->getMessage()),
                Response::HTTP_SEE_OTHER,
            );
        } catch (Throwable) {
            return new RedirectResponse(
                '/ui/catalog?availability_error=' . rawurlencode('Impossibile aggiornare la scorta di sicurezza.'),
                Response::HTTP_SEE_OTHER,
            );
        }
    }
}
