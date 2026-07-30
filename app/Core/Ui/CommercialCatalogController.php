<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class CommercialCatalogController
{
    public function __construct(private CommercialCatalogManagement $catalogs)
    {
    }

    public function create(Request $request): Response
    {
        try {
            $id = $this->catalogs->create([
                'code' => $request->request->getString('code'),
                'name' => $request->request->getString('name'),
                'description' => $request->request->getString('description'),
                'marketplace_ids' => $request->request->all('marketplace_ids'),
                'priority' => $request->request->getString('priority', '100'),
            ], $this->actor($request));

            return new RedirectResponse('/ui/catalog?catalog=' . $id . '&catalog_saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException $exception) {
            return new RedirectResponse('/ui/catalog?catalog_error=' . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        } catch (Throwable) {
            return new RedirectResponse('/ui/catalog?catalog_error=' . rawurlencode('Impossibile creare il catalogo commerciale.'), Response::HTTP_SEE_OTHER);
        }
    }

    public function delete(Request $request): Response
    {
        $catalogId = $request->attributes->getInt('catalogId');
        try {
            $this->catalogs->delete(
                $catalogId,
                $request->request->getString('confirmation'),
                $this->actor($request),
                $request->attributes->getString('correlation_id'),
            );

            return new RedirectResponse('/ui/catalog?catalog_deleted=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException $exception) {
            return new RedirectResponse(
                '/ui/catalog?catalog=' . $catalogId . '&catalog_delete_error=' . rawurlencode($exception->getMessage()),
                Response::HTTP_SEE_OTHER,
            );
        } catch (Throwable) {
            return new RedirectResponse(
                '/ui/catalog?catalog=' . $catalogId . '&catalog_delete_error='
                . rawurlencode('Impossibile cancellare il catalogo commerciale.'),
                Response::HTTP_SEE_OTHER,
            );
        }
    }

    public function status(Request $request): Response
    {
        $catalogId = $request->attributes->getInt('catalogId');
        try {
            $this->catalogs->setEnabled(
                $catalogId,
                $request->request->getString('status') === 'active',
                $this->actor($request),
                $request->attributes->getString('correlation_id'),
            );

            return new RedirectResponse(
                '/ui/catalog?catalog=' . $catalogId . '&catalog_status_saved=1',
                Response::HTTP_SEE_OTHER,
            );
        } catch (InvalidArgumentException $exception) {
            return new RedirectResponse(
                '/ui/catalog?catalog=' . $catalogId . '&catalog_status_error=' . rawurlencode($exception->getMessage()),
                Response::HTTP_SEE_OTHER,
            );
        } catch (Throwable) {
            return new RedirectResponse(
                '/ui/catalog?catalog=' . $catalogId . '&catalog_status_error='
                . rawurlencode('Impossibile modificare lo stato del catalogo.'),
                Response::HTTP_SEE_OTHER,
            );
        }
    }

    private function actor(Request $request): UserIdentity
    {
        $actor = $request->attributes->get('current_user');
        if (!$actor instanceof UserIdentity) {
            throw new InvalidArgumentException('Attore autenticato non disponibile.');
        }

        return $actor;
    }
}
