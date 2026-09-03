<?php

use App\Services\DebtStrategy;

function sortByAvalanche(array $debts): array
{
    usort($debts, fn (array $a, array $b): int => $b['factor'] <=> $a['factor']);

    return $debts;
}

function sortBySnowball(array $debts): array
{
    usort($debts, fn (array $a, array $b): int => $a['remaining'] <=> $b['remaining']);

    return $debts;
}

dataset('debts', function () {
    return [
        'mixed' => [
            [
                ['name' => 'Auto', 'remaining' => 3000.0, 'factor' => 1.8, 'installment' => 400.0],
                ['name' => 'Tarjeta', 'remaining' => 1500.0, 'factor' => 2.5, 'installment' => 300.0],
                ['name' => 'Personal', 'remaining' => 800.0, 'factor' => 1.2, 'installment' => 200.0],
            ],
        ],
        'single' => [
            [
                ['name' => 'Única', 'remaining' => 5000.0, 'factor' => 1.5, 'installment' => 500.0],
            ],
        ],
        'empty' => [
            [],
        ],
        'avalanche tie' => [
            [
                ['name' => 'A', 'remaining' => 1000.0, 'factor' => 1.5, 'installment' => 100.0],
                ['name' => 'B', 'remaining' => 2000.0, 'factor' => 1.5, 'installment' => 200.0],
                ['name' => 'C', 'remaining' => 500.0, 'factor' => 2.0, 'installment' => 150.0],
            ],
        ],
        'snowball tie' => [
            [
                ['name' => 'A', 'remaining' => 1000.0, 'factor' => 1.5, 'installment' => 100.0],
                ['name' => 'B', 'remaining' => 1000.0, 'factor' => 2.0, 'installment' => 200.0],
                ['name' => 'C', 'remaining' => 700.0, 'factor' => 1.2, 'installment' => 150.0],
            ],
        ],
    ];
});

test('avalanche order sorts by factor descending', function (array $debts) {
    $strategy = new DebtStrategy($debts);

    expect($strategy->avalancheOrder())->toBe(sortByAvalanche($debts));
})->with('debts');

test('snowball order sorts by remaining ascending', function (array $debts) {
    $strategy = new DebtStrategy($debts);

    expect($strategy->snowballOrder())->toBe(sortBySnowball($debts));
})->with('debts');

test('weighted factor is the remaining-weighted average of factors', function (array $debts) {
    $strategy = new DebtStrategy($debts);

    $totalRemaining = array_sum(array_column($debts, 'remaining'));

    if ($totalRemaining <= 0) {
        expect($strategy->weightedFactor())->toBe(0.0);

        return;
    }

    $expected = array_sum(array_map(
        fn (array $debt): float => $debt['remaining'] * $debt['factor'],
        $debts,
    )) / $totalRemaining;

    expect($strategy->weightedFactor())->toBe($expected);
})->with('debts');
