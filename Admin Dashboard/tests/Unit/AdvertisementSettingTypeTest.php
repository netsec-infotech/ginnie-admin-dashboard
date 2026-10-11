<?php

use App\Enums\Advertisement\AdsDisableBehaviorEnum;
use App\Types\Settings\AdvertisementSettingType;

it('exposes the expected defaults via public properties', function () {
    $type = new AdvertisementSettingType();

    expect($type->featureEnabled)->toBeFalse();
    expect($type->disableBehavior)->toBe(AdsDisableBehaviorEnum::KEEP_RUNNING()->value);
    expect($type->cpcRate)->toBeNull();
    expect($type->walletMinTopup)->toBeNull();
});

it('keeps engineering knobs as hardcoded class constants (not settings)', function () {
    expect(AdvertisementSettingType::AD_TOKEN_TTL_MINUTES)->toBe(15);
    expect(AdvertisementSettingType::AD_IMPRESSION_VISIBILITY_PCT)->toBe(70);
    expect(AdvertisementSettingType::AD_IMPRESSION_VISIBILITY_MS)->toBe(1000);

    // And they must not appear as public properties on the type — admins shouldn't
    // be able to edit them via the settings form.
    $reflection = new ReflectionClass(AdvertisementSettingType::class);
    $publicProps = array_map(
        fn ($p) => $p->getName(),
        $reflection->getProperties(ReflectionProperty::IS_PUBLIC),
    );
    expect($publicProps)->not->toContain('tokenTtlMinutes');
    expect($publicProps)->not->toContain('impressionVisibilityPct');
    expect($publicProps)->not->toContain('impressionVisibilityMs');
});

it('hydrates from a partial array and applies type-aware defaults', function () {
    $type = AdvertisementSettingType::fromArray([
        'featureEnabled' => true,
        'cpcRate' => 0.04,
        'walletMinTopup' => 10.00,
    ]);

    expect($type->featureEnabled)->toBeTrue();
    expect($type->cpcRate)->toBe(0.04);
    expect($type->walletMinTopup)->toBe(10.0);
});

it('rejects an unknown disable behavior value', function () {
    expect(fn () => AdvertisementSettingType::fromArray([
        'disableBehavior' => 'definitely_invalid',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('rejects negative pricing values', function () {
    expect(fn () => AdvertisementSettingType::fromArray([
        'cpcRate' => -1,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});
