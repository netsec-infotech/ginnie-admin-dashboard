<?php

use App\Models\DeliveryZone;
use App\Models\FeaturedSection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeFeaturedSection(string $title): FeaturedSection
{
    return FeaturedSection::create([
        'title' => $title,
        'style' => 'grid',
        'section_type' => 'featured',
        'status' => 'active',
        'scope_type' => 'global',
    ]);
}

it('returns a featured section with empty zone pivot for any zone (default = all zones)', function () {
    $section = makeFeaturedSection('Open Section');
    $zone = DeliveryZone::factory()->create();

    $rows = FeaturedSection::query()->availableInZone($zone->id)->pluck('id')->all();

    expect($rows)->toContain($section->id);
});

it('hides a featured section from zones it is not attached to', function () {
    $section = makeFeaturedSection('Mumbai Section');
    $mumbai = DeliveryZone::factory()->create();
    $bandra = DeliveryZone::factory()->create();
    $section->zones()->sync([$mumbai->id]);

    $matchedIds = FeaturedSection::query()->availableInZone($mumbai->id)->pluck('id')->all();
    $missedIds = FeaturedSection::query()->availableInZone($bandra->id)->pluck('id')->all();

    expect($matchedIds)->toContain($section->id)
        ->and($missedIds)->not->toContain($section->id);
});

it('returns all featured sections when no zone is supplied', function () {
    $open = makeFeaturedSection('Open');
    $restricted = makeFeaturedSection('Restricted');
    $zone = DeliveryZone::factory()->create();
    $restricted->zones()->sync([$zone->id]);

    $rows = FeaturedSection::query()->availableInZone(null)->pluck('id')->all();

    expect($rows)->toContain($open->id)->toContain($restricted->id);
});
