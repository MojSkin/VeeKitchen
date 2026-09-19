<?php

use App\Support\Money;

test('roundUp rounds to the nearest 100 toman upward', function (int $input, int $expected) {
    expect(Money::of($input)->roundUp()->toman)->toBe($expected);
})->with([
    [0, 0],
    [1, 100],
    [99, 100],
    [100, 100],
    [101, 200],
    [1234, 1300],
    [999, 1000],
    [1000, 1000],
]);

test('roundUp keeps zero at zero and never returns below 100 for positive input', function () {
    expect(Money::of(-50)->roundUp()->toman)->toBe(0);
});

test('arithmetic works on money values', function () {
    $a = Money::of(250_000);
    $b = Money::of(180_000);

    expect($a->plus($b)->toman)->toBe(430_000)
        ->and($a->minus($b)->toman)->toBe(70_000)
        ->and($a->multipliedBy(3)->toman)->toBe(750_000);
});

test('percentage computes fractional shares without premature rounding', function () {
    expect(Money::of(999)->percentage(9)->toman)->toBe(90);
});
