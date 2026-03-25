<?php

declare(strict_types=1);

namespace Trenor\Core\Import\Contract;

interface PriceImportBatchStoreInterface
{
    public function create(array $data): ?int;

    public function updateStatus(int $id, string $status, array $resultSummary): bool;

    public function findCompletedByChecksum(int $supplierId, string $checksum): ?array;
}
