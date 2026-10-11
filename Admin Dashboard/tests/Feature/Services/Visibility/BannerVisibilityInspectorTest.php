<?php

use App\Enums\Banner\BannerPositionEnum;
use App\Enums\Banner\BannerTypeEnum;
use App\Enums\Banner\BannerVisibilityStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\Banner;
use App\Services\Visibility\BannerVisibilityInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bannerInspector(): BannerVisibilityInspector
{
    return app(BannerVisibilityInspector::class);
}

function makeVisibilityInspectorBanner(array $overrides = []): Banner
{
    return Banner::create(array_merge([
        'type' => BannerTypeEnum::CUSTOM(),
        'title' => 'Test Banner ' . uniqid(),
        'custom_url' => 'https://example.test',
        'position' => BannerPositionEnum::TOP(),
        'visibility_status' => BannerVisibilityStatusEnum::PUBLISHED(),
        'scope_type' => HomePageScopeEnum::GLOBAL(),
    ], $overrides));
}

it('marks a draft banner as hidden', function () {
    $banner = makeVisibilityInspectorBanner(['visibility_status' => BannerVisibilityStatusEnum::DRAFT()]);

    $result = bannerInspector()->inspect($banner);

    expect($result['status'])->toBe('hidden');
    expect(collect($result['checks'])->firstWhere('key', 'visibility')['state'])->toBe('fail');
});

it('marks a custom banner without a url as hidden', function () {
    $banner = makeVisibilityInspectorBanner(['custom_url' => null]);

    $result = bannerInspector()->inspect($banner);

    expect(collect($result['checks'])->firstWhere('key', 'linked_entity')['state'])->toBe('fail');
});

it('marks a category-scoped banner without scope_id as hidden', function () {
    $banner = makeVisibilityInspectorBanner([
        'scope_type' => HomePageScopeEnum::CATEGORY(),
        'scope_id' => null,
    ]);

    $result = bannerInspector()->inspect($banner);

    expect(collect($result['checks'])->firstWhere('key', 'scope')['state'])->toBe('fail');
});

it('warns when a banner has no image uploaded', function () {
    $banner = makeVisibilityInspectorBanner();

    $result = bannerInspector()->inspect($banner);

    expect(collect($result['checks'])->firstWhere('key', 'image')['state'])->toBe('warn');
});
