<?php

declare(strict_types=1);

namespace Hapa\Core\Automation;

use RuntimeException;

final class LostAutomationLock extends RuntimeException
{
    public function __construct(string $code)
    {
        parent::__construct(sprintf('Il lock dell’automazione "%s" non è più valido.', $code));
    }
}
