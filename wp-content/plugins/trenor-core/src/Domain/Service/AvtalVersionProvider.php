<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

interface AvtalVersionProvider
{
    public function nextVersionNo(int $offertId): int;
}
