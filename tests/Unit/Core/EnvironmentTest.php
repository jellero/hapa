<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Configuration\ConfigurationLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvironmentTest extends TestCase
{
    /** @var array<string, string|null> */
    private array $original = [];

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->original as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
                putenv($key);
                continue;
            }
            $_ENV[$key] = $value;
            putenv(sprintf('%s=%s', $key, $value));
        }

        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->original = [];
        $this->temporaryFiles = [];
    }

    public function testProductionRejectsDebugMode(): void
    {
        $this->setEnvironment($this->productionEnvironment(['APP_DEBUG' => 'true']));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG');
        ConfigurationLoader::load();
    }

    public function testItRejectsAnInvalidDebugValue(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'not-a-boolean',
            'APP_URL' => 'http://localhost',
            'APP_TIMEZONE' => 'UTC',
            'TRUSTED_PROXIES' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG');
        ConfigurationLoader::load();
    }

    public function testItRejectsAnInvalidApplicationUrl(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'localhost-without-scheme',
            'APP_TIMEZONE' => 'UTC',
            'TRUSTED_PROXIES' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_URL');
        ConfigurationLoader::load();
    }

    public function testItRejectsApplicationUrlCredentials(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://operator:secret@hapa.example.com',
            'APP_TIMEZONE' => 'UTC',
            'TRUSTED_PROXIES' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_URL');
        ConfigurationLoader::load();
    }

    public function testItRejectsApplicationUrlQueryAndFragment(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://hapa.example.com?debug=true#fragment',
            'APP_TIMEZONE' => 'UTC',
            'TRUSTED_PROXIES' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_URL');
        ConfigurationLoader::load();
    }

    public function testRabbitMqConsumerIsDisabledByDefault(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost',
            'APP_TIMEZONE' => 'UTC',
            'TRUSTED_PROXIES' => '',
            'RABBITMQ_CONSUMER_ENABLED' => 'false',
            'RABBITMQ_CONSUMER_BINDINGS' => 'integration.transport.#',
            'RABBITMQ_CONSUMER_MAX_ATTEMPTS' => '5',
        ]);

        $configuration = ConfigurationLoader::load()->rabbitMqConsumer;

        self::assertFalse($configuration->enabled);
        self::assertSame(['integration.transport.#'], $configuration->bindings);
        self::assertSame(5, $configuration->maximumAttempts);
    }

    public function testProductionRejectsMissingTrustedProxies(): void
    {
        $this->setEnvironment($this->productionEnvironment(['TRUSTED_PROXIES' => '']));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TRUSTED_PROXIES');
        ConfigurationLoader::load();
    }

    public function testProductionAcceptsExplicitSecureConfiguration(): void
    {
        $this->setEnvironment($this->productionEnvironment());
        $configuration = ConfigurationLoader::load();

        self::assertTrue($configuration->application->isProduction());
        self::assertFalse($configuration->application->debug);
        self::assertSame(['127.0.0.1', 'REMOTE_ADDR'], $configuration->proxy->trustedProxies);
        self::assertFalse($configuration->rabbitMqConsumer->enabled);
    }

    public function testProductionRequiresStrongConsumerSecretWhenEnabled(): void
    {
        $this->setEnvironment($this->productionEnvironment([
            'RABBITMQ_CONSUMER_ENABLED' => 'true',
            'RABBITMQ_CONSUMER_USERNAME' => 'hapa-consumer',
            'RABBITMQ_CONSUMER_PASSWORD' => 'weak',
            'RABBITMQ_CONSUMER_BINDINGS' => 'integration.transport.#',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('RABBITMQ_CONSUMER_PASSWORD');
        ConfigurationLoader::load();
    }

    public function testProductionReadsSecretsFromFiles(): void
    {
        $databaseSecret = $this->secretFile('database-secret-from-file');
        $redisSecret = $this->secretFile('redis-secret-from-file');
        $consumerSecret = $this->secretFile('consumer-secret-from-file');

        $this->setEnvironment($this->productionEnvironment([
            'DB_PASSWORD' => '',
            'REDIS_PASSWORD' => '',
            'DB_PASSWORD_FILE' => $databaseSecret,
            'REDIS_PASSWORD_FILE' => $redisSecret,
            'RABBITMQ_CONSUMER_ENABLED' => 'true',
            'RABBITMQ_CONSUMER_USERNAME' => 'hapa-consumer',
            'RABBITMQ_CONSUMER_PASSWORD' => '',
            'RABBITMQ_CONSUMER_PASSWORD_FILE' => $consumerSecret,
            'RABBITMQ_CONSUMER_BINDINGS' => 'integration.transport.#',
        ]));

        $configuration = ConfigurationLoader::load();
        self::assertTrue($configuration->application->isProduction());
        self::assertSame('database-secret-from-file', $configuration->database->password);
        self::assertSame('redis-secret-from-file', $configuration->redis->password);
        self::assertSame('consumer-secret-from-file', $configuration->rabbitMqConsumer->password);
    }

    /** @param array<string, string> $overrides
     *  @return array<string, string>
     */
    private function productionEnvironment(array $overrides = []): array
    {
        return array_replace([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://hapa.example.com',
            'APP_TIMEZONE' => 'Europe/Rome',
            'TRUSTED_PROXIES' => '127.0.0.1,REMOTE_ADDR',
            'DB_PASSWORD' => 'secure-database-password',
            'REDIS_PASSWORD' => 'secure-redis-password',
            'DB_PASSWORD_FILE' => '',
            'REDIS_PASSWORD_FILE' => '',
            'RABBITMQ_ENABLED' => 'false',
            'RABBITMQ_PASSWORD' => '',
            'RABBITMQ_PASSWORD_FILE' => '',
            'RABBITMQ_CONSUMER_ENABLED' => 'false',
            'RABBITMQ_CONSUMER_PASSWORD' => '',
            'RABBITMQ_CONSUMER_PASSWORD_FILE' => '',
        ], $overrides);
    }

    /** @param array<string, string> $values */
    private function setEnvironment(array $values): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $this->original)) {
                $existing = $_ENV[$key] ?? getenv($key);
                $this->original[$key] = $existing === false ? null : (string) $existing;
            }
            $_ENV[$key] = $value;
            putenv(sprintf('%s=%s', $key, $value));
        }
    }

    private function secretFile(string $secret): string
    {
        $file = tempnam(sys_get_temp_dir(), 'hapa-secret-');
        if ($file === false) {
            throw new RuntimeException('Impossibile creare un secret file temporaneo.');
        }
        file_put_contents($file, $secret . PHP_EOL);
        $this->temporaryFiles[] = $file;

        return $file;
    }
}
