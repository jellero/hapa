<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$errors = [];

/** @var mixed $rawModuleDependencies */
$rawModuleDependencies = require_once $basePath . '/config/module-dependencies.php';
/** @var array<string, list<string>> $moduleDependencies */
$moduleDependencies = [];

if (!is_array($rawModuleDependencies)) {
    $errors[] = 'config/module-dependencies.php deve restituire un array.';
} else {
    foreach ($rawModuleDependencies as $module => $dependencies) {
        if (!is_string($module) || !is_array($dependencies)) {
            $errors[] = 'Ogni voce di config/module-dependencies.php deve associare un modulo a una lista.';
            continue;
        }

        $normalizedDependencies = [];
        foreach ($dependencies as $dependency) {
            if (!is_string($dependency) || $dependency === '') {
                $errors[] = sprintf('Il modulo %s contiene una dipendenza non valida.', $module);
                continue;
            }

            $normalizedDependencies[] = $dependency;
        }

        $moduleDependencies[$module] = array_values(array_unique($normalizedDependencies));
    }
}

$modulePaths = glob($basePath . '/app/Modules/*', GLOB_ONLYDIR) ?: [];
$discoveredModules = array_map('basename', $modulePaths);
sort($discoveredModules);
$registeredModules = array_keys($moduleDependencies);
sort($registeredModules);

foreach (array_diff($discoveredModules, $registeredModules) as $module) {
    $errors[] = sprintf('Il modulo %s non è registrato in config/module-dependencies.php.', $module);
}

foreach (array_diff($registeredModules, $discoveredModules) as $module) {
    $errors[] = sprintf('Il manifesto registra il modulo inesistente %s.', $module);
}

foreach ($moduleDependencies as $module => $dependencies) {
    foreach ($dependencies as $dependency) {
        if ($dependency === $module) {
            $errors[] = sprintf('Il modulo %s non può dipendere da sé stesso.', $module);
        } elseif (!array_key_exists($dependency, $moduleDependencies)) {
            $errors[] = sprintf('Il modulo %s dipende dal modulo sconosciuto %s.', $module, $dependency);
        }
    }
}

/** @var array<string, int> $visitState */
$visitState = [];
/** @var list<string> $visitStack */
$visitStack = [];
/** @var Closure(string): void $visitModule */
$visitModule = function (string $module) use (&$visitModule, &$visitState, &$visitStack, &$errors, $moduleDependencies): void {
    $state = $visitState[$module] ?? 0;
    if ($state === 2) {
        return;
    }

    if ($state === 1) {
        $cycleStart = array_search($module, $visitStack, true);
        $cycle = $cycleStart === false ? [$module] : array_slice($visitStack, $cycleStart);
        $cycle[] = $module;
        $errors[] = 'Ciclo tra moduli: ' . implode(' -> ', $cycle) . '.';

        return;
    }

    $visitState[$module] = 1;
    $visitStack[] = $module;

    foreach ($moduleDependencies[$module] ?? [] as $dependency) {
        if (array_key_exists($dependency, $moduleDependencies)) {
            $visitModule($dependency);
        }
    }

    array_pop($visitStack);
    $visitState[$module] = 2;
};

foreach (array_keys($moduleDependencies) as $module) {
    $visitModule($module);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath . '/app', FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path) ?: '';
    $relative = str_replace('\\', '/', str_replace($basePath . '/', '', $path));

    if (str_contains($content, 'Pms\\')) {
        $errors[] = sprintf('%s contiene riferimenti al namespace PMS.', $relative);
    }

    if (!preg_match('/^namespace\s+([^;]+);/m', $content, $namespaceMatch)) {
        $errors[] = sprintf('%s non dichiara un namespace.', $relative);
        continue;
    }

    $namespace = trim($namespaceMatch[1]);

    if (str_starts_with($relative, 'app/Core/') && !str_starts_with($namespace, 'Hapa\\Core')) {
        $errors[] = sprintf('%s deve appartenere a Hapa\\Core.', $relative);
    }

    if (preg_match('#^app/Modules/([^/]+)/#', $relative, $moduleMatch)) {
        $module = $moduleMatch[1];
        $expectedPrefix = 'Hapa\\Modules\\' . $module;

        if (!str_starts_with($namespace, $expectedPrefix)) {
            $errors[] = sprintf('%s deve appartenere a %s.', $relative, $expectedPrefix);
        }
    }

    preg_match_all('/^use\s+([^;]+);/m', $content, $useMatches);
    /** @var list<string> $imports */
    $imports = array_map('trim', $useMatches[1]);

    if (str_starts_with($namespace, 'Hapa\\Core')) {
        foreach ($imports as $import) {
            if (str_starts_with($import, 'Hapa\\Modules\\')) {
                $errors[] = sprintf('%s viola il vincolo Core -> Modules tramite %s.', $relative, $import);
            }
        }
    }

    if (preg_match('/^Hapa\\\\Modules\\\\([^\\\\]+)/', $namespace, $currentModuleMatch)) {
        $currentModule = $currentModuleMatch[1];

        foreach ($imports as $import) {
            if (!preg_match('/^Hapa\\\\Modules\\\\([^\\\\]+)/', $import, $importModuleMatch)) {
                continue;
            }

            $importedModule = $importModuleMatch[1];
            if ($importedModule === $currentModule) {
                continue;
            }

            if (!str_contains($import, '\\Contract\\')) {
                $errors[] = sprintf(
                    '%s dipende direttamente dal modulo %s; usare un contratto esplicito.',
                    $relative,
                    $importedModule,
                );
            }

            if (!in_array($importedModule, $moduleDependencies[$currentModule] ?? [], true)) {
                $errors[] = sprintf(
                    '%s usa il modulo %s senza dichiararlo in config/module-dependencies.php.',
                    $relative,
                    $importedModule,
                );
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Architecture check: OK\n");
