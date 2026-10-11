<?php

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\FeaturedSection\FeaturedSectionStyleEnum;
use App\Enums\FeaturedSection\FeaturedSectionTypeEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\FeaturedSection;
use App\Services\Visibility\FeaturedSectionVisibilityInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fsInspector(): FeaturedSectionVisibilityInspector
{
    return app(FeaturedSectionVisibilityInspector::class);
}

function makeVisibilityInspectorFeaturedSection(array $overrides = []): FeaturedSection
{
    return FeaturedSection::create(array_merge([
        'title' => 'Test Section ' . uniqid(),
        'style' => FeaturedSectionStyleEnum::WITHOUT_BACKGROUND(),
        'section_type' => FeaturedSectionTypeEnum::FEATURED(),
        'status' => ActiveInactiveStatusEnum::ACTIVE(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
    ], $overrides));
}

it('marks an inactive section as hidden', function () {
    $section = makeVisibilityInspectorFeaturedSection(['status' => ActiveInactiveStatusEnum::INACTIVE()]);

    $result = fsInspector()->inspect($section);

    expect($result['status'])->toBe('hidden');
    expect(collect($result['checks'])->firstWhere('key', 'status')['state'])->toBe('fail');
});

it('marks a category-scoped section without scope_id as hidden', function () {
    $section = makeVisibilityInspectorFeaturedSection([
        'scope_type' => HomePageScopeEnum::CATEGORY(),
        'scope_id' => null,
    ]);

    $result = fsInspector()->inspect($section);

    expect($result['status'])->toBe('hidden');
    expect(collect($result['checks'])->firstWhere('key', 'scope')['state'])->toBe('fail');
});

it('warns when no background type is picked', function () {
    $section = makeVisibilityInspectorFeaturedSection();

    $result = fsInspector()->inspect($section);

    expect(collect($result['checks'])->firstWhere('key', 'background')['state'])->toBe('warn');
});
