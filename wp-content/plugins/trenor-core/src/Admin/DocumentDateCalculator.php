<?php

declare(strict_types=1);

namespace Trenor\Core\Admin;

use DateInterval;
use DateTimeImmutable;
use Exception;

final class DocumentDateCalculator
{
    public function addDays(string $issuedAt, string $days): string
    {
        $issuedAtValue = trim($issuedAt);
        if ($issuedAtValue === '') {
            return '';
        }

        if (! ctype_digit(trim($days))) {
            return '';
        }

        $daysValue = (int) trim($days);
        if ($daysValue <= 0) {
            return '';
        }

        try {
            $date = new DateTimeImmutable($issuedAtValue);
        } catch (Exception) {
            return '';
        }

        return $date->add(new DateInterval('P' . $daysValue . 'D'))->format('Y-m-d');
    }
}
