<?php

declare(strict_types=1);

namespace Trenor\Core\Domain\ValueObject;

use InvalidArgumentException;

final class Money
{
    private int $minor;
    private string $currency;

    public function __construct(int $minor, string $currency = 'SEK')
    {
        if ($currency === '') {
            throw new InvalidArgumentException('Currency cannot be empty');
        }

        $this->minor = $minor;
        $this->currency = strtoupper($currency);
    }

    public static function fromFloat(float $amount, string $currency = 'SEK'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function asFloat(): float
    {
        return $this->minor / 100;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch');
        }
    }
}
