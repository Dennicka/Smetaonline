<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use RuntimeException;

final class ReverseChargePolicy
{
    /**
     * @param array<string, mixed> $estimate
     * @param array<string, mixed> $client
     */
    public function assertEstimateEligibility(array $estimate, array $client): void
    {
        $taxMode = TaxMode::normalize($estimate['tax_mode'] ?? null);
        $rotRequested = ! empty($estimate['rot_requested']);

        if (! TaxMode::isReverseCharge($taxMode)) {
            return;
        }

        if ($rotRequested) {
            throw new RuntimeException('Reverse charge cannot be combined with ROT.');
        }

        $orgNumber = trim((string) ($client['org_number'] ?? ''));
        $vatNumber = trim((string) ($client['vat_number'] ?? ''));
        $companyName = trim((string) ($client['company_name'] ?? ($client['name'] ?? '')));

        if ($companyName === '' || $orgNumber === '' || $vatNumber === '') {
            throw new RuntimeException('Reverse charge requires company name, organisation number, and VAT number.');
        }
    }
}
