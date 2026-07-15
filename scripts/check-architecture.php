<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$errors = [];

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
            if ($importedModule !== $currentModule && !str_contains($import, '\\Contract\\')) {
                $errors[] = sprintf(
                    '%s dipende direttamente dal modulo %s; usare un contratto esplicito.',
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
