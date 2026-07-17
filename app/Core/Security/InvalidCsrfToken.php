<?php

declare(strict_types=1);

namespace Hapa\Core\Security;

use RuntimeException;

final class InvalidCsrfToken extends RuntimeException
{
}
