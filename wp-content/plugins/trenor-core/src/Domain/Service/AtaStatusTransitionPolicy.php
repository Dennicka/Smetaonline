<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class AtaStatusTransitionPolicy
{
    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        $from = sanitize_key($fromStatus);
        $to = sanitize_key($toStatus);

        $allowed = [
            'draft' => ['issued', 'archived'],
            'issued' => ['approved', 'rejected', 'archived'],
            'approved' => ['archived'],
            'rejected' => ['archived'],
            'archived' => [],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }
}
