<?php

declare(strict_types=1);

namespace Hapa\Core\Bootstrap;

use Hapa\Core\Configuration\Environment;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Health\ReadinessCheck;
use Psr\Log\LoggerInterface;

final readonly class ApplicationContext
{
    public function __construct(
        public Environment $environment,
        public LoggerInterface $logger,
        public ConnectionFactory $connections,
        public ReadinessCheck $readiness,
    ) {
    }
}
