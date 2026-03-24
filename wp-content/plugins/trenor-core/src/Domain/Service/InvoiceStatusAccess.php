<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

interface InvoiceStatusAccess
{
    public function find(int $id): ?array;

    public function transitionStatus(int $id, string $status): bool;
}
