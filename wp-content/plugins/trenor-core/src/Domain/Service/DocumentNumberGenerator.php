<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use DateTimeImmutable;

interface DocumentNumberGenerator
{
    public function next(string $docType, ?DateTimeImmutable $date = null): string;
}
