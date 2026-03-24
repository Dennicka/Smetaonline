<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class OffertStatusTransitionPolicy
{
    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        $from = sanitize_key($fromStatus);
        $to = sanitize_key($toStatus);

        $allowedTransitions = [
            'issued' => ['accepted', 'rejected', 'archived'],
            'accepted' => ['archived'],
            'rejected' => ['archived'],
        ];

        return in_array($to, $allowedTransitions[$from] ?? [], true);
    }
}
