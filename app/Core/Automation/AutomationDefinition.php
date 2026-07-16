<?php

declare(strict_types=1);

namespace Hapa\Core\Automation;

final readonly class AutomationDefinition
{
    public function __construct(
        public string $name,
        public string $flow,
        public string $frequency,
        public string $control,
        public string $status,
        public string $tone,
    ) {
    }
}
