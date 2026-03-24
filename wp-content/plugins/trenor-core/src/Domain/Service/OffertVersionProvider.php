<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

interface OffertVersionProvider
{
    public function nextVersionNo(int $estimateId): int;
}

