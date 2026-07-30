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
use Hapa\Core\Exception\HapaRuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class IntegrationConfigurationController
{
    private const SAVED_PATH = '/ui/integrations?saved=1';
    private const ERROR_PATH = '/ui/integrations?error=';

    public function __construct(
        private IntegrationAccountConfiguration $validator,
        private IntegrationAccountRepository $accounts,
        private ProviderSecretGateway $secretGateway,
        private ProviderSecretFields $secretFields,
        private ProviderConfigurationGateway $configurationGateway,
        private SpacePurchaseManagement $spacePurchases,
        private CatalogProductManagement $catalog,
    ) {
    }

    public function create(Request $request): Response
    {
        try {
            $configuration = $this->configuration($request);
            $actor = $this->actor($request);
            $correlationId = $request->attributes->getString('correlation_id');
            $spaceToken = $configuration['provider'] === 'space'
                ? trim($request->request->getString('space_bearer_token'))
                : '';
            if ($configuration['provider'] === 'space' && $spaceToken === '') {
                throw new InvalidArgumentException('Inserire il token Bearer Space.');
            }
            $accountId = $this->accounts->create($configuration, $actor, $correlationId);
            $redirect = self::SAVED_PATH;

            if ($configuration['provider'] === 'space') {
                $account = $this->accounts->find($accountId);
                $configurationStatus = $this->configurationGateway->apply($account, $actor->id, $correlationId);
                $this->accounts->recordAutomationConfigurationStatus($accountId, $configurationStatus, $actor, $correlationId);

                $secretStatus = $this->secretGateway->replace(
                    (string) $account['code'],
                    'space',
                    ['api_key' => $spaceToken],
                    $actor->id,
                    $correlationId,
                );
                $this->accounts->recordSecretStatus($accountId, $secretStatus, $actor, $correlationId);
                $redirect = '/ui/integrations?saved=1&configuration_synced=1&secrets_saved=1';
            }

            return new RedirectResponse($redirect, Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | JsonException | RuntimeException $exception) {
            return new RedirectResponse(
                self::ERROR_PATH . rawurlencode($exception->getMessage()),
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

            return new RedirectResponse(self::SAVED_PATH, Response::HTTP_SEE_OTHER);
        } catch (InvalidArgumentException | JsonException $exception) {
            return new RedirectResponse(
                self::ERROR_PATH . rawurlencode($exception->getMessage()),
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

        return new RedirectResponse(self::SAVED_PATH, Response::HTTP_SEE_OTHER);
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
            return new RedirectResponse(self::ERROR_PATH . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function revokeSecrets(Request $request): Response
    {
        try {
            if ($request->request->getString('confirm_revoke') !== 'yes') {
                throw new HapaRuntimeException('La revoca richiede conferma esplicita.');
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
            return new RedirectResponse(self::ERROR_PATH . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
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
            if ($account['provider_code'] === 'space') {
                $this->spacePurchases->generateOutstanding($correlationId);
            } elseif ($account['provider_code'] === 'sellrapido') {
                $this->catalog->recalculateOffers();
            }

            return new RedirectResponse('/ui/integrations?configuration_synced=1', Response::HTTP_SEE_OTHER);
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse(self::ERROR_PATH . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
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
            return new RedirectResponse(self::ERROR_PATH . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function changeStatus(Request $request): Response
    {
        try {
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            $target = $request->request->getString('target_status');
            if ($account['environment'] === 'production' && in_array($target, ['pilot', 'active'], true)
                && $request->request->getString('confirm_production') !== 'yes') {
                throw new HapaRuntimeException('L’attivazione in produzione richiede conferma esplicita.');
            }
            $this->accounts->changeDesiredStatus(
                (int) $account['id'],
                $request->request->getInt('configuration_version'),
                $target,
                $this->actor($request),
                $request->attributes->getString('correlation_id'),
            );

            return new RedirectResponse(self::SAVED_PATH, Response::HTTP_SEE_OTHER);
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse(self::ERROR_PATH . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function testConnection(Request $request): Response
    {
        try {
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            if (!in_array($account['provider_code'], ['sellrapido', 'space'], true)) {
                throw new HapaRuntimeException('Il test operativo è disponibile per SellRapido e Space.');
            }
            $actor = $this->actor($request);
            $correlationId = $request->attributes->getString('correlation_id');
            $result = $this->configurationGateway->testConnection((string) $account['code']);
            $this->accounts->recordConnectionTest((int) $account['id'], $result, $actor, $correlationId);
            if ($account['provider_code'] === 'space') {
                $this->spacePurchases->generateOutstanding($correlationId);
            } elseif ($account['provider_code'] === 'sellrapido') {
                $this->catalog->recalculateOffers();
            }

            return new RedirectResponse('/ui/integrations?connection_tested=1', Response::HTTP_SEE_OTHER);
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse(self::ERROR_PATH . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function importOrders(Request $request): Response
    {
        try {
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            if ($account['provider_code'] !== 'sellrapido' || !in_array($account['desired_status'], ['pilot', 'active'], true)) {
                throw new HapaRuntimeException('L’import manuale richiede un account SellRapido pilot o attivo.');
            }
            $actor = $this->actor($request);
            $correlationId = $request->attributes->getString('correlation_id');
            $result = $this->configurationGateway->importOrders((string) $account['code']);
            $this->accounts->recordManualImport((int) $account['id'], $result, $actor, $correlationId);

            return new RedirectResponse('/ui/integrations?orders_imported=1&published=' . (int) ($result['published'] ?? 0), Response::HTTP_SEE_OTHER);
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse(self::ERROR_PATH . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    public function synchronizeCatalog(Request $request): Response
    {
        try {
            $account = $this->accounts->find($request->attributes->getInt('accountId'));
            if ($account['provider_code'] !== 'space'
                || !in_array($account['desired_status'], ['pilot', 'active'], true)
                || !in_array('catalog.read', $account['capabilities'], true)) {
                throw new HapaRuntimeException('La sincronizzazione manuale richiede un account Space pilot o attivo con catalog.read.');
            }
            $actor = $this->actor($request);
            $correlationId = $request->attributes->getString('correlation_id');
            $result = $this->configurationGateway->synchronizeCatalog((string) $account['code']);
            $this->accounts->recordManualCatalogSync((int) $account['id'], $result, $actor, $correlationId);

            return new RedirectResponse(
                '/ui/integrations?catalog_synchronized=1&published=' . (int) ($result['published'] ?? 0),
                Response::HTTP_SEE_OTHER,
            );
        } catch (JsonException | RuntimeException $exception) {
            return new RedirectResponse(self::ERROR_PATH . rawurlencode($exception->getMessage()), Response::HTTP_SEE_OTHER);
        }
    }

    /** @return array<string, mixed> @throws JsonException */
    private function configuration(Request $request): array
    {
        $provider = strtolower(trim($request->request->getString('provider')));
        $capabilities = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', $request->request->getString('capabilities')),
        ), static fn (string $value): bool => $value !== ''));
        $settings = [];
        if ($provider !== 'space') {
            $settingsJson = trim($request->request->getString('settings_json', '{}'));
            if ($settingsJson === '') {
                $settingsJson = '{}';
            }
            $settings = json_decode($settingsJson, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($settings) || array_is_list($settings)) {
                throw new InvalidArgumentException('Le impostazioni devono essere un oggetto JSON.');
            }
        }
        if ($provider === 'space' && $request->request->has('space_base_url')) {
            $spaceSettings = [
                'base_url' => $request->request->getString('space_base_url'),
                'health_path' => $request->request->getString('space_health_path'),
                'catalog_incremental_path' => $request->request->getString('space_catalog_incremental_path'),
                'catalog_confirmation_path' => $request->request->getString('space_catalog_confirmation_path'),
                'catalog_entity' => $request->request->getString('space_catalog_entity'),
                'poll_interval_seconds' => $request->request->getInt('space_poll_interval_seconds'),
                'catalog_page_size' => $request->request->getInt('space_catalog_page_size'),
                'maximum_catalog_pages_per_run' => $request->request->getInt('space_maximum_catalog_pages_per_run'),
                'authentication_scheme' => 'bearer',
            ];
            foreach ($spaceSettings as $key => $value) {
                if ($value !== '' && $value !== 0) {
                    $settings[$key] = $value;
                }
            }
            $mapping = [];
            foreach ($request->request->all('space_field_mapping') as $target => $source) {
                if (is_string($target) && is_string($source) && trim($source) !== '') {
                    $mapping[$target] = trim($source);
                }
            }
            if ($mapping !== []) {
                $settings['catalog_field_mapping'] = $mapping;
            }
        }

        return $this->validator->validate(
            $provider,
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
