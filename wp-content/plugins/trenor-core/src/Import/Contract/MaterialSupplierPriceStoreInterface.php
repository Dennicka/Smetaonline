<?php

declare(strict_types=1);

namespace Trenor\Core\Import\Contract;

interface MaterialSupplierPriceStoreInterface
{
    public function findCurrentPrice(int $supplierId, string $materialKey): ?array;

    public function closeActivePrice(int $priceId, string $effectiveTo): bool;

    public function createPrice(array $data): ?int;
}
