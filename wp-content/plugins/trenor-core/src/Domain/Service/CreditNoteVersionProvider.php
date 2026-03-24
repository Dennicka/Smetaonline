<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

interface CreditNoteVersionProvider
{
    public function nextVersionNo(int $invoiceId): int;
}
