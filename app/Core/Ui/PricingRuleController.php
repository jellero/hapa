<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Security\UserIdentity;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class PricingRuleController
{
    public function __construct(private PricingRuleManagement $rules)
    {
    }

    public function create(Request $request): Response
    {
        return $this->mutate(function () use ($request): void {
            $this->rules->create($this->input($request), $this->actor($request), $this->correlationId($request));
        }, $request->request->getInt('commercial_catalog_id'));
    }

    public function update(Request $request): Response
    {
        return $this->mutate(function () use ($request): void {
            $this->rules->update(
                $request->attributes->getInt('ruleId'),
                $request->request->getInt('version'),
                $this->input($request),
                $this->actor($request),
                $this->correlationId($request),
            );
        }, $request->request->getInt('commercial_catalog_id'));
    }

    public function retire(Request $request): Response
    {
        return $this->mutate(function () use ($request): void {
            $this->rules->retire(
                $request->attributes->getInt('ruleId'),
                $request->request->getInt('version'),
                $this->actor($request),
                $this->correlationId($request),
            );
        }, $request->request->getInt('commercial_catalog_id'));
    }

    /** @param callable(): void $operation */
    private function mutate(callable $operation, int $catalogId): Response
    {
        $catalogQuery = $catalogId > 0 ? 'catalog=' . $catalogId . '&' : '';
        try {
            $operation();

            return new RedirectResponse('/ui/catalog?' . $catalogQuery . 'pricing_saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | PricingRuleConflict $exception) {
            return new RedirectResponse(
                '/ui/catalog?' . $catalogQuery . 'pricing_error=' . rawurlencode($exception->getMessage()),
                Response::HTTP_SEE_OTHER,
            );
        } catch (Throwable) {
            return new RedirectResponse(
                '/ui/catalog?' . $catalogQuery . 'pricing_error=' . rawurlencode('Impossibile salvare la regola di ricarico.'),
                Response::HTTP_SEE_OTHER,
            );
        }
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        $adjustmentType = $request->request->getString('adjustment_type');

        return [
            'commercial_catalog_id' => $request->request->getString('commercial_catalog_id'),
            'code' => $request->request->getString('code'),
            'name' => $request->request->getString('name'),
            'scope' => $request->request->getString('scope'),
            'marketplace_id' => $request->request->getString('marketplace_id'),
            'sku' => $request->request->getString('sku'),
            'adjustment_type' => $adjustmentType,
            'adjustment_value' => $this->adjustmentValue($request, $adjustmentType),
            'currency' => $request->request->getString('currency', 'EUR'),
            'minimum_price_minor' => $request->request->getString('minimum_price_minor'),
            'maximum_price_minor' => $request->request->getString('maximum_price_minor'),
            'priority' => $request->request->getString('priority', '100'),
            'enabled' => $request->request->getBoolean('enabled'),
            'valid_from' => $request->request->getString('valid_from'),
            'valid_until' => $request->request->getString('valid_until'),
        ];
    }

    private function adjustmentValue(Request $request, string $adjustmentType): string
    {
        if ($adjustmentType !== 'percentage' || !$request->request->has('percentage_value')) {
            return $request->request->getString('adjustment_value');
        }
        $percentage = str_replace(',', '.', trim($request->request->getString('percentage_value')));
        if ($percentage === '' || !is_numeric($percentage) || (float) $percentage < 0) {
            throw new InvalidArgumentException('Inserisci una percentuale valida.');
        }

        return (string) (int) round((float) $percentage * 100);
    }

    private function actor(Request $request): UserIdentity
    {
        $actor = $request->attributes->get('current_user');
        if (!$actor instanceof UserIdentity) {
            throw new InvalidArgumentException('Attore autenticato non disponibile.');
        }

        return $actor;
    }

    private function correlationId(Request $request): string
    {
        return $request->attributes->getString('correlation_id');
    }
}
