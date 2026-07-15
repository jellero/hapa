<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Configuration\Environment;
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
        $this->setEnvironment([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'true',
            'APP_URL' => 'https://hapa.example.com',
            'APP_TIMEZONE' => 'Europe/Rome',
            'DB_PASSWORD' => 'secure-database-password',
            'REDIS_PASSWORD' => 'secure-redis-password',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG');

        Environment::load();
    }

    public function testProductionAcceptsExplicitSecureConfiguration(): void
    {
        $this->setEnvironment([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://hapa.example.com',
            'APP_TIMEZONE' => 'Europe/Rome',
            'DB_PASSWORD' => 'secure-database-password',
            'REDIS_PASSWORD' => 'secure-redis-password',
        ]);

        $environment = Environment::load();

        self::assertTrue($environment->isProduction());
        self::assertFalse($environment->debug);
    }

    public function testProductionReadsSecretsFromFiles(): void
    {
        $databaseSecret = $this->secretFile('database-secret-from-file');
        $redisSecret = $this->secretFile('redis-secret-from-file');

        $this->setEnvironment([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://hapa.example.com',
            'APP_TIMEZONE' => 'Europe/Rome',
            'DB_PASSWORD' => '',
            'REDIS_PASSWORD' => '',
            'DB_PASSWORD_FILE' => $databaseSecret,
            'REDIS_PASSWORD_FILE' => $redisSecret,
        ]);

        $environment = Environment::load();

        self::assertTrue($environment->isProduction());
        self::assertSame('database-secret-from-file', Environment::secret('DB_PASSWORD'));
        self::assertSame('redis-secret-from-file', Environment::secret('REDIS_PASSWORD'));
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
        self::assertNotFalse($file);
        file_put_contents($file, $secret . PHP_EOL);
        $this->temporaryFiles[] = $file;

        return $file;
    }
}
