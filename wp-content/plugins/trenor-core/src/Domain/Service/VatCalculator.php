<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\Service;

use Trenor\Core\Domain\ValueObject\Money;

final class VatCalculator
{
    public function addVat(Money $net, float $ratePercent): Money
    {
        $gross = $net->minor() * (1 + $ratePercent / 100);
        return new Money((int) round($gross), $net->currency());
    }

    public function vatPart(Money $net, float $ratePercent): Money
    {
        $vat = $net->minor() * ($ratePercent / 100);
        return new Money((int) round($vat), $net->currency());
    }
}
