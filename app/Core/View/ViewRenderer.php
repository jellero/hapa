<?php

declare(strict_types=1);

namespace Hapa\Core\View;

use Hapa\Core\Exception\HapaRuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class ViewRenderer
{
    public function __construct(private string $templatesPath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(
        string $template,
        array $data = [],
        string $layout = 'layouts/app',
        int $status = Response::HTTP_OK,
    ): Response {
        $content = $this->evaluate($this->resolve($template), $data);
        $html = $this->evaluate(
            $this->resolve($layout),
            array_replace($data, ['content' => $content]),
        );

        return new Response($html, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function resolve(string $template): string
    {
        if (!preg_match('#^[a-z0-9/_-]+$#D', $template)) {
            throw new HapaRuntimeException('Nome template non valido.');
        }

        $root = realpath($this->templatesPath);
        $file = realpath($this->templatesPath . '/' . $template . '.php');

        if (
            $root === false
            || $file === false
            || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)
            || !is_file($file)
        ) {
            throw new HapaRuntimeException(sprintf('Template non trovato: %s', $template));
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function evaluate(string $file, array $data): string
    {
        $data['e'] = static fn (mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        extract($data, EXTR_SKIP);
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            // Templates must execute on every render; require_once would make
            // every render after the first one return an empty response.
            require $file; // NOSONAR
            $output = ob_get_clean();

            if ($output === false) {
                throw new HapaRuntimeException('Impossibile completare il rendering del template.');
            }

            return $output;
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw $exception;
        }
    }
}
