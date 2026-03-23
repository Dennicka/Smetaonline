<?php

declare(strict_types=1);

namespace Trenor\Core\Support;

final class Snapshot
{
    public static function freeze(array $state): array
    {
        return unserialize(serialize($state), ['allowed_classes' => false]);
    }
}
