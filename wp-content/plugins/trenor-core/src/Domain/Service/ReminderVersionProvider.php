<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

interface ReminderVersionProvider
{
    public function nextVersionNo(int $invoiceId): int;
}
