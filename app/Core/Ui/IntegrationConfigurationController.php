<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Integration\IntegrationAccountConfiguration;
use Hapa\Core\Integration\IntegrationAccountRepository;
use Hapa\Core\Integration\ProviderSecretFields;
use Hapa\Core\Integration\ProviderSecretGateway;
use Hapa\Core\Integration\ProviderConfigurationGateway;
use Hapa\Core\Security\UserIdentity;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class IntegrationConfigurationController
{
    public function __construct(
        private IntegrationAccountConfiguration $validator,
        private IntegrationAccountRepository $accounts,
        private ProviderSecretGateway $secretGateway,
        private ProviderSecretFields $secretFields,
        private ProviderConfigurationGateway $configurationGateway,
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

    public function replaceSecrets(Request $request): Response
    {
        try {
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            if ($account['desired_status'] === 'retired') {
                throw new InvalidArgumentException('L’account ritirato non può ricevere nuove credenziali.');
            }
            $actor = $this->actor($request);
            $correlationId = $request->attributes->getString('correlation_id');
            $secrets = $this->secretFields->submitted((string) $account['provider_code'], $request->request->all('secrets'));
            $status = $this->secretGateway->replace(
                (string) $account['code'],
                (string) $account['provider_code'],
                $secrets,
                $actor->id,
                $correlationId,
            );
            $this->accounts->recordSecretStatus((int) $account['id'], $status, $actor, $correlationId);

            return new RedirectResponse('/ui/integrations?secrets_saved=1', Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | JsonException | RuntimeException $exception) {
            return new RedirectResponse('/ui/integrations?error=' . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function revokeSecrets(Request $request): Response
    {
        try {
            if ($request->request->getString('confirm_revoke') !== 'yes') {
                throw new RuntimeException('La revoca richiede conferma esplicita.');
            }
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            $actor = $this->actor($request);
            $correlationId = $request->attributes->getString('correlation_id');
            $status = $this->secretGateway->revoke(
                (string) $account['code'],
                (string) $account['provider_code'],
                $actor->id,
                $correlationId,
            );
            $this->accounts->recordSecretStatus((int) $account['id'], $status, $actor, $correlationId);

            return new RedirectResponse('/ui/integrations?secrets_revoked=1', Response::HTTP_SEE_OTHER);
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse('/ui/integrations?error=' . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function synchronizeConfiguration(Request $request): Response
    {
        try {
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            $actor = $this->actor($request);
            $correlationId = $request->attributes->getString('correlation_id');
            $status = $this->configurationGateway->apply($account, $actor->id, $correlationId);
            $this->accounts->recordAutomationConfigurationStatus((int) $account['id'], $status, $actor, $correlationId);

            return new RedirectResponse('/ui/integrations?configuration_synced=1', Response::HTTP_SEE_OTHER);
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse('/ui/integrations?error=' . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function refreshTechnicalStatus(Request $request): Response
    {
        try {
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            $actor = $this->actor($request);
            $correlationId = $request->attributes->getString('correlation_id');
            $secretStatus = $this->secretGateway->status((string) $account['code']);
            $this->accounts->recordSecretStatus((int) $account['id'], $secretStatus, $actor, $correlationId);
            $configurationStatus = $this->configurationGateway->configurationStatus((string) $account['code']);
            if (($configurationStatus['status'] ?? null) === 'applied') {
                $this->accounts->recordAutomationConfigurationStatus((int) $account['id'], $configurationStatus, $actor, $correlationId);
            }

            return new RedirectResponse('/ui/integrations?status_refreshed=1', Response::HTTP_SEE_OTHER);
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse('/ui/integrations?error=' . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function changeStatus(Request $request): Response
    {
        try {
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            $target = $request->request->getString('target_status');
            if ($account['environment'] === 'production' && in_array($target, ['pilot', 'active'], true)
                && $request->request->getString('confirm_production') !== 'yes') {
                throw new RuntimeException('L’attivazione in produzione richiede conferma esplicita.');
            }
            $this->accounts->changeDesiredStatus(
                (int) $account['id'],
                $request->request->getInt('configuration_version'),
                $target,
                $this->actor($request),
                $request->attributes->getString('correlation_id'),
            );

            return new RedirectResponse('/ui/integrations?saved=1', Response::HTTP_SEE_OTHER);
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse('/ui/integrations?error=' . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
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
