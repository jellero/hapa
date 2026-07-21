<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\WebSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait UiControllerSupport
{
    /** @param array<string,mixed> $page */
    private function collection(Request $request, string $active, array $page): Response
    {
        $page['query'] = trim($request->query->getString('q'));
        $selected = $request->query->getString('status');
        /** @var list<string> $filters */
        $filters = $page['filters'];
        $page['selectedFilter'] = in_array($selected, $filters, true) ? $selected : '';
        $page['clearUrl'] = $request->getPathInfo();
        return $this->operational($request, 'ui/collection', $active, $page);
    }

    private function can(Request $request, string $permission): bool
    {
        $user = $request->attributes->get('current_user');
        return $user instanceof UserIdentity && $this->authorization?->allows($user, $permission) === true;
    }

    /** @param array<string,mixed> $page */
    private function operational(Request $request, string $template, string $active, array $page): Response
    {
        $user = $request->attributes->get('current_user');
        $session = $request->attributes->get('security_session');
        return $this->views->render($template, array_replace($page, [
            'active' => $active, 'navigation' => $this->navigation(), 'environment' => $this->environment,
            'correlationId' => $request->attributes->getString('correlation_id'),
            'currentUser' => $user instanceof UserIdentity ? $user : null,
            'logoutCsrfToken' => $session instanceof WebSession ? $session->csrfToken('logout') : '',
        ]));
    }

    /** @return list<array{label:string,items:list<array{label:string,href:string,icon:string,active:string}>}> */
    private function navigation(): array
    {
        return [
            ['label' => 'Operatività', 'items' => [
                ['label' => 'Dashboard', 'href' => '/ui', 'icon' => 'dashboard', 'active' => 'dashboard'],
                ['label' => 'Clienti', 'href' => '/ui/customers', 'icon' => 'customer', 'active' => 'customers'],
                ['label' => 'Ordini', 'href' => '/ui/orders', 'icon' => 'orders', 'active' => 'orders'],
                ['label' => 'Catalogo e prezzi', 'href' => '/ui/catalog', 'icon' => 'box', 'active' => 'catalog'],
                ['label' => 'Picking', 'href' => '/ui/picking', 'icon' => 'scan', 'active' => 'picking'],
                ['label' => 'Spedizioni', 'href' => '/ui/shipments', 'icon' => 'truck', 'active' => 'shipments'],
            ]],
            ['label' => 'Controllo', 'items' => [
                ['label' => 'Integrazioni', 'href' => '/ui/integrations', 'icon' => 'integration', 'active' => 'integrations'],
                ['label' => 'Audit', 'href' => '/ui/audit', 'icon' => 'audit', 'active' => 'audit'],
            ]],
            ['label' => 'Amministrazione', 'items' => [
                ['label' => 'Utenti e ruoli', 'href' => '/ui/users', 'icon' => 'users', 'active' => 'users'],
                ['label' => 'Impostazioni', 'href' => '/ui/settings', 'icon' => 'settings', 'active' => 'settings'],
            ]],
        ];
    }
}
