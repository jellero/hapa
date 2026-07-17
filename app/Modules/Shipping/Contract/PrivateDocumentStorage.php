<?php

declare(strict_types=1);

namespace Hapa\Modules\Shipping\Contract;

interface PrivateDocumentStorage
{
    public function store(string $scope, string $format, string $content): StoredDocument;

    public function read(string $reference, string $expectedChecksum): string;

    public function delete(string $reference): void;
}
