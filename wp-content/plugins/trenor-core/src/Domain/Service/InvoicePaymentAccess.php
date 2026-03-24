<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

interface InvoicePaymentAccess
{
    /** @param array<string, mixed> $data */
    public function create(array $data): ?int;

    /** @return array<int, array<string, mixed>> */
    public function byInvoice(int $invoiceId): array;
}
