<?php

declare(strict_types=1);

namespace Hapa\Core\Logging;

use Hapa\Core\Configuration\Environment;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class LoggerFactory
{
    public function create(): LoggerInterface
    {
        $level = $this->parseLevel(Environment::value('LOG_LEVEL', 'info'));
        $handler = new StreamHandler('php://stderr', $level);
        $handler->setFormatter(new JsonFormatter());

        $redactor = new SensitiveDataRedactor();
        $logger = new Logger('hapa');
        $logger->pushProcessor(
            static fn (LogRecord $record): LogRecord => $record->with(
                context: $redactor->redact($record->context),
                extra: $redactor->redact($record->extra),
            ),
        );
        $logger->pushHandler($handler);

        return $logger;
    }

    private function parseLevel(string $value): Level
    {
        return match (strtolower(trim($value))) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => throw new RuntimeException(sprintf('LOG_LEVEL non valido: %s', $value)),
        };
    }
}
