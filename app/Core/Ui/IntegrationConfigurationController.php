<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Integration\IntegrationAccountConfiguration;
use Hapa\Core\Integration\IntegrationAccountRepository;
use Hapa\Core\Security\UserIdentity;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class IntegrationConfigurationController
{
    public function __construct(
        private IntegrationAccountConfiguration $validator,
        private IntegrationAccountRepository $accounts,
    ) {
    }

    public function create(Request $request): Response
    {
        try {
            $configuration = $this->configuration($request);
            $this->accounts->create($configuration, $this->actor($request), $request->attributes->getString('correlation_id'));

            return new RedirectResponse('/ui/integrations?saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | JsonException $exception) {
            return new RedirectResponse(
                '/ui/integrations?error=' . rawurlencode($exception->getMessage()),
                Response::HTTP_SEE_OTHER,
            );
        }
    }

    public function update(Request $request): Response
    {
        try {
            $configuration = $this->configuration($request);
            $this->accounts->update(
                $request->attributes->getInt('accountId'),
                $request->request->getInt('configuration_version'),
                $configuration,
                $this->actor($request),
                $request->attributes->getString('correlation_id'),
            );

            return new RedirectResponse('/ui/integrations?saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | JsonException $exception) {
            return new RedirectResponse(
                '/ui/integrations?error=' . rawurlencode($exception->getMessage()),
                Response::HTTP_SEE_OTHER,
            );
        }
    }

    public function retire(Request $request): Response
    {
        $this->accounts->retire(
            $request->attributes->getInt('accountId'),
            $request->request->getInt('configuration_version'),
            $this->actor($request),
            $request->attributes->getString('correlation_id'),
        );

        return new RedirectResponse('/ui/integrations?saved=1', Response::HTTP_SEE_OTHER);
    }

    /** @return array<string, mixed> @throws JsonException */
    private function configuration(Request $request): array
    {
        $capabilities = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', $request->request->getString('capabilities')),
        ), static fn (string $value): bool => $value !== ''));
        $settingsJson = trim($request->request->getString('settings_json', '{}'));
        $settings = json_decode($settingsJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($settings) || array_is_list($settings)) {
            throw new InvalidArgumentException('Le impostazioni devono essere un oggetto JSON.');
        }

        return $this->validator->validate(
            $request->request->getString('provider'),
            $request->request->getString('code'),
            $request->request->getString('display_name'),
            $request->request->getString('environment'),
            $request->request->getString('description'),
            $capabilities,
            $settings,
        );
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
