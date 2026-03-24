<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class AvtalStatusTransitionPolicy
{
    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        $normalizedFrom = sanitize_key($fromStatus);
        $normalizedTo = sanitize_key($toStatus);

        $allowed = [
            'issued' => ['archived'],
            'archived' => [],
        ];

        return in_array($normalizedTo, $allowed[$normalizedFrom] ?? [], true);
    }
}
