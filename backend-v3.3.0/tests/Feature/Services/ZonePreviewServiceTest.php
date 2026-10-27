<?php

use App\Models\DeliveryZone;
use App\Services\ZonePreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns an empty paginator when the zone has no stores', function () {
    $zone = DeliveryZone::factory()->create();

    $page = app(ZonePreviewService::class)->availableBrands($zone->id);

    expect($page->total())->toBe(0)
        ->and($page->items())->toBeEmpty();
});
