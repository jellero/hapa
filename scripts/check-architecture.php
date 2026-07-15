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
    $relative = str_replace($basePath . '/', '', $path);

    if (str_contains($content, 'namespace Pms\\') || str_contains($content, 'use Pms\\')) {
        $errors[] = sprintf('%s contiene riferimenti al namespace PMS.', $relative);
    }

    if (str_starts_with($relative, 'app/Core/') && str_contains($content, 'Hapa\\Modules\\')) {
        $errors[] = sprintf('%s viola il vincolo Core -> Modules.', $relative);
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Architecture check: OK\n");
