<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount in Toman. The only currency VeeKitchen supports.
 *
 * Every amount the customer or the accounts ever see must pass through
 * `roundUp()`: the business rule is that final figures are rounded UP to the
 * nearest 100 Toman, because no coin below that is in circulation.
 */
final class Money implements JsonSerializable, Stringable
{
    public const ROUNDING_STEP = 100;

    private function __construct(public readonly int $toman) {}

    public static function of(int|float $toman): self
    {
        if (! is_finite((float) $toman)) {
            throw new InvalidArgumentException('Amount must be a finite number.');
        }

        return new self((int) round($toman));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->toman + $other->toman);
    }

    public function minus(self $other): self
    {
        return new self($this->toman - $other->toman);
    }

    public function multipliedBy(int|float $factor): self
    {
        return self::of($this->toman * $factor);
    }

    /**
     * A share of this amount, e.g. `percentage(9)` for a 9% tax component.
     */
    public function percentage(int|float $percent): self
    {
        return self::of($this->toman * $percent / 100);
    }

    /**
     * Apply the 100-Toman ceiling rule. Call this on final figures only —
     * rounding intermediate steps inflates the total.
     */
    public function roundUp(): self
    {
        if ($this->toman <= 0) {
            return new self(0);
        }

        return new self((int) (ceil($this->toman / self::ROUNDING_STEP) * self::ROUNDING_STEP));
    }

    public function isZero(): bool
    {
        return $this->toman === 0;
    }

    public function equals(self $other): bool
    {
        return $this->toman === $other->toman;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->toman > $other->toman;
    }

    /**
     * Grouped digits plus the unit, for receipts and exports.
     */
    public function formatted(): string
    {
        return number_format($this->toman).' تومان';
    }

    public function jsonSerialize(): int
    {
        return $this->toman;
    }

    public function __toString(): string
    {
        return (string) $this->toman;
    }
}
