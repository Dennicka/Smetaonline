<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class AvtalIssuePolicy
{
    public function canIssueFromOffertStatus(string $status): bool
    {
        return sanitize_key($status) === 'accepted';
    }
}
