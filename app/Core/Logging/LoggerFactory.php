<?php

declare(strict_types=1);

namespace Hapa\Core\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public function create(): LoggerInterface
    {
        $level = strtoupper($_ENV['LOG_LEVEL'] ?? (string) getenv('LOG_LEVEL') ?: 'INFO');
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
}
