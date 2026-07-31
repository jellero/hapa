<?php

declare(strict_types=1);

namespace Hapa\Composition;

use Hapa\Core\Configuration\ConfigurationSet;
use Hapa\Core\Exception\HapaRuntimeException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Throwable;

final readonly class CompiledContainerFactory
{
    public function create(string $basePath, ConfigurationSet $configuration): ContainerInterface
    {
        $signature = $this->signature($basePath, $configuration);
        $className = 'HapaCachedContainer_' . substr($signature, 0, 20);
        $cacheDirectory = sprintf(
            '%s/hapa-container-cache/%s/%s',
            rtrim(sys_get_temp_dir(), '/\\'),
            substr(hash('sha256', $basePath), 0, 16),
            $this->userScope(),
        );
        $cacheFile = $cacheDirectory . '/' . $className . '.php';

        if (!is_file($cacheFile)) {
            $this->compile($basePath, $configuration, $cacheDirectory, $cacheFile, $className);
        }

        require_once $cacheFile;
        if (!class_exists($className, false)) {
            throw new HapaRuntimeException('Il container applicativo compilato non è disponibile.');
        }

        $container = new $className();
        if (!$container instanceof ContainerInterface) {
            throw new HapaRuntimeException('Il container applicativo compilato non è valido.');
        }

        return $container;
    }

    private function compile(
        string $basePath,
        ConfigurationSet $configuration,
        string $cacheDirectory,
        string $cacheFile,
        string $className,
    ): void {
        if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0700, true) && !is_dir($cacheDirectory)) {
            throw new HapaRuntimeException('Impossibile creare la cache del container applicativo.');
        }

        $container = (new ContainerFactory())->create($basePath, $configuration);
        $source = (new PhpDumper($container))->dump(['class' => $className]);
        $temporaryFile = sprintf('%s.%s.tmp', $cacheFile, bin2hex(random_bytes(8)));

        try {
            if (file_put_contents($temporaryFile, $source, LOCK_EX) === false) {
                throw new HapaRuntimeException('Impossibile scrivere la cache del container applicativo.');
            }
            if (!rename($temporaryFile, $cacheFile) && !is_file($cacheFile)) {
                throw new HapaRuntimeException('Impossibile pubblicare la cache del container applicativo.');
            }
            chmod($cacheFile, 0600);
        } catch (Throwable $exception) {
            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
            throw $exception;
        }
    }

    private function signature(string $basePath, ConfigurationSet $configuration): string
    {
        $inputs = [serialize($configuration)];
        foreach ([$basePath . '/app/Composition/ContainerFactory.php', $basePath . '/composer.lock'] as $file) {
            $inputs[] = is_file($file) ? (string) hash_file('sha256', $file) : '';
        }

        return hash('sha256', implode('|', $inputs));
    }

    private function userScope(): string
    {
        return function_exists('posix_geteuid')
            ? (string) posix_geteuid()
            : substr(hash('sha256', get_current_user()), 0, 12);
    }
}
