<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use RuntimeException;

final class PermanentProcessingFailure extends RuntimeException
{
}
