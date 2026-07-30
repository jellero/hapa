<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class CatalogPublicationRuleController
{
    public function __construct(private CatalogPublicationRuleManagement $rules)
    {
    }

    public function create(Request $request): Response
    {
        try {
            $this->rules->create([
                'code' => $request->request->getString('code'),
                'name' => $request->request->getString('name'),
                'marketplace_id' => $request->request->getString('marketplace_id'),
                'action' => $request->request->getString('action'),
                'field' => $request->request->getString('field'),
                'operator' => $request->request->getString('operator'),
                'match_value' => $request->request->getString('match_value'),
                'priority' => $request->request->getString('priority', '100'),
            ], $this->actor($request));
            return new RedirectResponse('/ui/catalog?publication_rule_saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException $exception) {
            return new RedirectResponse('/ui/catalog?publication_rule_error=' . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        } catch (Throwable) {
            return new RedirectResponse('/ui/catalog?publication_rule_error=' . rawurlencode('Impossibile salvare la regola di pubblicazione.'), Response::HTTP_SEE_OTHER);
        }
    }

    public function retire(Request $request): Response
    {
        try {
            $this->rules->retire($request->attributes->getInt('ruleId'), $this->actor($request));
            return new RedirectResponse('/ui/catalog?publication_rule_saved=1', Response::HTTP_SEE_OTHER);
        } catch (Throwable) {
            return new RedirectResponse('/ui/catalog?publication_rule_error=' . rawurlencode('Impossibile disattivare la regola.'), Response::HTTP_SEE_OTHER);
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
