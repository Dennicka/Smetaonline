<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class InvoiceIssuePolicy
{
    public function canIssueFromOffertStatus(string $status): bool
    {
        return (new DocumentFinanceTransitionPolicy())->canIssueInvoiceFromOffertStatus($status);
    }
}
