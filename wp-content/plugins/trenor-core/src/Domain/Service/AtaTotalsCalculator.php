<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

final class AtaTotalsCalculator
{
    /** @return array{amount_ex_vat_minor:int,vat_rate_percent:float,vat_minor:int,total_inc_vat_minor:int,currency:string} */
    public function calculate(int $amountExVatMinor, float $vatRatePercent, string $currency = 'SEK'): array
    {
        $normalizedAmount = max(0, $amountExVatMinor);
        $normalizedVatRate = max(0.0, $vatRatePercent);
        $vatMinor = (int) round(($normalizedAmount * $normalizedVatRate) / 100, 0, PHP_ROUND_HALF_UP);

        return [
            'amount_ex_vat_minor' => $normalizedAmount,
            'vat_rate_percent' => $normalizedVatRate,
            'vat_minor' => $vatMinor,
            'total_inc_vat_minor' => $normalizedAmount + $vatMinor,
            'currency' => strtoupper(sanitize_text_field($currency !== '' ? $currency : 'SEK')),
        ];
    }
}
