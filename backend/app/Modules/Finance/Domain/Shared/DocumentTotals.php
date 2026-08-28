<?php

declare(strict_types=1);

namespace App\Modules\Finance\Domain\Shared;

final readonly class DocumentTotals
{
    /**
     * @param  list<TaxBreakdown>  $taxBreakdowns
     */
    public function __construct(
        public Money $net,
        public Money $vat,
        public Money $gross,
        public Money $discount,
        public array $taxBreakdowns,
    ) {}

    public function matchesControlTotals(?Money $net, ?Money $vat, ?Money $gross): bool
    {
        return $this->matches($this->net, $net)
            && $this->matches($this->vat, $vat)
            && $this->matches($this->gross, $gross);
    }

    private function matches(Money $calculated, ?Money $control): bool
    {
        return $control === null
            || ($calculated->minor() === $control->minor()
                && $calculated->currency() === $control->currency());
    }
}
