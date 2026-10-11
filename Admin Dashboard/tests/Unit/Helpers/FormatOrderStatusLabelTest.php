<?php

/**
 * Phase 2 — formatOrderStatusLabel() helper. Pure logic, no DB.
 */

it('returns the translated label when one exists', function () {
    // delivery_failed and returning_to_store were added to labels in Phase 1B.
    expect(formatOrderStatusLabel('delivery_failed'))->toBe('Delivery Failed')
        ->and(formatOrderStatusLabel('returning_to_store'))->toBe('Returning to Store');
});

it('falls back to a title-cased humanised string when no key exists', function () {
    // 'cancelled' isn't seeded as a top-level labels.* key, so it falls back.
    expect(formatOrderStatusLabel('cancelled'))->toBe('Cancelled')
        ->and(formatOrderStatusLabel('something_unknown'))->toBe('Something Unknown');
});

it('returns empty string for null or empty input', function () {
    expect(formatOrderStatusLabel(null))->toBe('')
        ->and(formatOrderStatusLabel(''))->toBe('');
});
