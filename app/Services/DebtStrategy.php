<?php

namespace App\Services;

class DebtStrategy
{
    /**
     * @param  list<array{name: string, remaining: float, factor: float, installment: float, ...}>  $debts
     */
    public function __construct(
        private readonly array $debts = [],
    ) {}

    /**
     * Avalanche order: highest rate factor first.
     * Stable sort preserves input order for ties.
     *
     * @return list<array{name: string, remaining: float, factor: float, installment: float, ...}>
     */
    public function avalancheOrder(): array
    {
        $debts = $this->debts;

        usort($debts, fn (array $a, array $b): int => $b['factor'] <=> $a['factor']);

        return $debts;
    }

    /**
     * Snowball order: lowest remaining balance first.
     * Stable sort preserves input order for ties.
     *
     * @return list<array{name: string, remaining: float, factor: float, installment: float, ...}>
     */
    public function snowballOrder(): array
    {
        $debts = $this->debts;

        usort($debts, fn (array $a, array $b): int => $a['remaining'] <=> $b['remaining']);

        return $debts;
    }

    /**
     * Weighted rate factor across all debts: Σ(remaining × factor) / Σ remaining.
     * Returns 0 when there are no debts or no remaining balance.
     */
    public function weightedFactor(): float
    {
        $totalRemaining = array_sum(array_column($this->debts, 'remaining'));

        if ($totalRemaining <= 0) {
            return 0.0;
        }

        $weighted = array_sum(array_map(
            fn (array $debt): float => $debt['remaining'] * $debt['factor'],
            $this->debts,
        ));

        return $weighted / $totalRemaining;
    }
}
