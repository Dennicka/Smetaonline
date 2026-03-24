<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

interface AtaVersionProvider
{
    public function nextVersionNo(int $projectId): int;
}
