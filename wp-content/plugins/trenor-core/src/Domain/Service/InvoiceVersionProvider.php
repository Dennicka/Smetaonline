<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

interface InvoiceVersionProvider
{
    public function nextVersionNo(int $offertId): int;
}
