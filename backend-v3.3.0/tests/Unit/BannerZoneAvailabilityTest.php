<?php

use App\Enums\Banner\BannerPositionEnum;
use App\Enums\Banner\BannerTypeEnum;
use App\Models\Banner;
use App\Models\DeliveryZone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeBanner(string $title): Banner
{
    return Banner::create([
        'type' => BannerTypeEnum::CUSTOM(),
        'title' => $title,
        'custom_url' => 'https://example.test',
        'position' => BannerPositionEnum::TOP(),
        'visibility_status' => 'published',
        'scope_type' => 'global',
    ]);
}

it('returns a banner with empty zone pivot for any zone (default = all zones)', function () {
    $banner = makeBanner('Open banner');
    $zone = DeliveryZone::factory()->create();

    $rows = Banner::query()->availableInZone($zone->id)->pluck('id')->all();

    expect($rows)->toContain($banner->id);
});

it('returns a banner restricted to a zone only when querying that zone', function () {
    $banner = makeBanner('Mumbai-only banner');
    $mumbai = DeliveryZone::factory()->create();
    $bandra = DeliveryZone::factory()->create();
    $banner->zones()->sync([$mumbai->id]);

    $matchedIds = Banner::query()->availableInZone($mumbai->id)->pluck('id')->all();
    $missedIds = Banner::query()->availableInZone($bandra->id)->pluck('id')->all();

    expect($matchedIds)->toContain($banner->id)
        ->and($missedIds)->not->toContain($banner->id);
});

it('returns all banners when no zone is supplied (zoneId=null short-circuits)', function () {
    $unrestricted = makeBanner('Open');
    $restricted = makeBanner('Restricted');
    $zone = DeliveryZone::factory()->create();
    $restricted->zones()->sync([$zone->id]);

    $rows = Banner::query()->availableInZone(null)->pluck('id')->all();

    expect($rows)->toContain($unrestricted->id)->toContain($restricted->id);
});
