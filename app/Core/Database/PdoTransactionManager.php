<?php

declare(strict_types=1);

namespace Hapa\Core\Database;

use PDO;
use Throwable;

final readonly class PdoTransactionManager implements TransactionManager
{
    public function __construct(private PDO $pdo)
    {
    }

    public function transactional(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $operation();
        }

        $this->pdo->beginTransaction();

        try {
            $result = $operation();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
