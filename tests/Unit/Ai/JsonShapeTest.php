<?php

use App\Support\Ai\JsonShape;

test('object key order is ignored at every depth', function () {
    expect(JsonShape::same(['b' => ['y' => 1, 'x' => [3, 2]], 'a' => 'k'], ['a' => 'k', 'b' => ['x' => [3, 2], 'y' => 1]]))->toBeTrue();
});

test('list order, scalar types and extra keys still matter', function () {
    expect(JsonShape::same(['a' => [1, 2]], ['a' => [2, 1]]))->toBeFalse()
        ->and(JsonShape::same(['a' => '1'], ['a' => 1]))->toBeFalse()
        ->and(JsonShape::same(['a' => null], []))->toBeFalse()
        ->and(JsonShape::same(['a' => true], ['a' => 1]))->toBeFalse();
});

test('lists of objects keep their order but sort the keys inside each element', function () {
    expect(JsonShape::same([['b' => 1, 'a' => 2], ['d' => 4]], [['a' => 2, 'b' => 1], ['d' => 4]]))->toBeTrue()
        ->and(JsonShape::same([['a' => 1], ['b' => 2]], [['b' => 2], ['a' => 1]]))->toBeFalse();
});
