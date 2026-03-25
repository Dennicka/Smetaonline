<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class TaxMode
{
    public const PRIVATE_CONSUMER = 'private_consumer';
    public const BUSINESS_STANDARD_VAT = 'business_standard_vat';
    public const BUSINESS_REVERSE_CHARGE = 'business_reverse_charge';

    public static function normalize(mixed $raw): string
    {
        $mode = sanitize_key((string) $raw);
        if (in_array($mode, self::all(), true)) {
            return $mode;
        }

        return self::PRIVATE_CONSUMER;
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return [
            self::PRIVATE_CONSUMER,
            self::BUSINESS_STANDARD_VAT,
            self::BUSINESS_REVERSE_CHARGE,
        ];
    }

    public static function isReverseCharge(string $taxMode): bool
    {
        return self::normalize($taxMode) === self::BUSINESS_REVERSE_CHARGE;
    }
}
