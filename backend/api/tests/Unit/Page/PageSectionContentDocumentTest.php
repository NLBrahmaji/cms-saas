<?php

use App\Support\Page\PageSectionContentDocument;

test('non-empty legacy list root does not equal empty object document', function () {
    $legacy = [['title' => 'Legacy']];
    $empty = [];

    expect(PageSectionContentDocument::equals(
        PageSectionContentDocument::normalizeForComparison($legacy),
        $empty,
    ))->toBeFalse();
});

test('canonical equality ignores object key order at root and nested levels', function () {
    $left = [
        'heading' => 'Hello',
        'meta' => ['a' => 1, 'b' => 2],
        'items' => [
            ['title' => 'A', 'description' => 'B'],
        ],
    ];

    $right = [
        'meta' => ['b' => 2, 'a' => 1],
        'heading' => 'Hello',
        'items' => [
            ['description' => 'B', 'title' => 'A'],
        ],
    ];

    expect(PageSectionContentDocument::equals($left, $right))->toBeTrue();
});

test('canonical equality preserves array order significance', function () {
    $left = [
        'items' => [
            ['title' => 'A'],
            ['title' => 'B'],
        ],
    ];

    $right = [
        'items' => [
            ['title' => 'B'],
            ['title' => 'A'],
        ],
    ];

    expect(PageSectionContentDocument::equals($left, $right))->toBeFalse();
});
