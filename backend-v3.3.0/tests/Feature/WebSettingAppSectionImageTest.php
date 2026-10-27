<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware();
    $this->admin = User::factory()->create([
        'access_panel' => GuardNameEnum::ADMIN->value,
    ]);
    Role::findOrCreate(DefaultSystemRolesEnum::SUPER_ADMIN->value, GuardNameEnum::ADMIN->value);
    $this->admin->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN->value);
});

it('renders the appSectionImage filepond input on web settings view', function () {
    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->get(route('admin.settings.show', ['setting' => 'web']));

    $response->assertOk()
        ->assertSee('name="appSectionImage"', false)
        ->assertSee('remove_appSectionImage', false);
});

it('allows admin to upload, retain, and remove appSectionImage in web settings', function () {
    Storage::fake('public');

    // 1. Upload new appSectionImage
    $file = UploadedFile::fake()->image('app_banner.png', 600, 400);

    $response = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::WEB(),
            'siteName' => 'Hyperlocal Store',
            'siteCopyright' => '© 2026 Hyperlocal',
            'supportNumber' => '1234567890',
            'supportEmail' => 'support@example.com',
            'address' => '123 Street',
            'shortDescription' => 'Best hyperlocal shopping app',
            'siteHeaderLogo' => UploadedFile::fake()->image('header.png'),
            'siteHeaderDarkLogo' => UploadedFile::fake()->image('header-dark.png'),
            'siteFooterLogo' => UploadedFile::fake()->image('footer.png'),
            'siteFavicon' => UploadedFile::fake()->image('favicon.ico'),
            'appDownloadSection' => '1',
            'appSectionTitle' => 'Download our App',
            'appSectionTagline' => 'Shop anywhere, anytime',
            'appSectionImage' => $file,
            'pwaName' => 'Hyperlocal PWA',
            'pwaDescription' => 'Hyperlocal PWA Description',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $setting = Setting::where('variable', SettingTypeEnum::WEB())->first();
    expect($setting)->not->toBeNull();
    $storedPath = $setting->value['appSectionImage'] ?? null;
    expect($storedPath)->not->toBeEmpty();
    Storage::disk('public')->assertExists($storedPath);

    // 2. Resource serialization contains URL
    $settingService = app(SettingService::class);
    $resource = $settingService->getSettingByVariable(SettingTypeEnum::WEB());
    $resolved = $resource->toArray(request());
    expect($resolved['value']['appSectionImage'])->toContain('storage/' . $storedPath);

    // 3. Updating without re-uploading retains the image
    $updateResponse = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::WEB(),
            'siteName' => 'Hyperlocal Store Updated',
            'siteCopyright' => '© 2026 Hyperlocal',
            'supportNumber' => '1234567890',
            'supportEmail' => 'support@example.com',
            'address' => '123 Street',
            'shortDescription' => 'Best hyperlocal shopping app',
            'siteHeaderLogo' => UploadedFile::fake()->image('header.png'),
            'siteHeaderDarkLogo' => UploadedFile::fake()->image('header-dark.png'),
            'siteFooterLogo' => UploadedFile::fake()->image('footer.png'),
            'siteFavicon' => UploadedFile::fake()->image('favicon.ico'),
            'appDownloadSection' => '1',
            'appSectionTitle' => 'Download our App v2',
            'pwaName' => 'Hyperlocal PWA',
            'pwaDescription' => 'Hyperlocal PWA Description',
        ]);

    $updateResponse->assertOk()
        ->assertJson(['success' => true]);
    $setting->refresh();
    expect($setting->value['appSectionImage'])->toBe($storedPath);
    Storage::disk('public')->assertExists($storedPath);

    // 4. Remove image using remove_appSectionImage
    $removeResponse = $this->actingAs($this->admin, GuardNameEnum::ADMIN->value)
        ->postJson(route('admin.settings.store'), [
            'type' => SettingTypeEnum::WEB(),
            'siteName' => 'Hyperlocal Store Updated',
            'siteCopyright' => '© 2026 Hyperlocal',
            'supportNumber' => '1234567890',
            'supportEmail' => 'support@example.com',
            'address' => '123 Street',
            'shortDescription' => 'Best hyperlocal shopping app',
            'siteHeaderLogo' => UploadedFile::fake()->image('header.png'),
            'siteHeaderDarkLogo' => UploadedFile::fake()->image('header-dark.png'),
            'siteFooterLogo' => UploadedFile::fake()->image('footer.png'),
            'siteFavicon' => UploadedFile::fake()->image('favicon.ico'),
            'appDownloadSection' => '1',
            'appSectionTitle' => 'Download our App v2',
            'pwaName' => 'Hyperlocal PWA',
            'pwaDescription' => 'Hyperlocal PWA Description',
            'remove_appSectionImage' => '1',
        ]);

    $removeResponse->assertOk()
        ->assertJson(['success' => true]);
    $setting->refresh();
    expect($setting->value['appSectionImage'])->toBe('');
    Storage::disk('public')->assertMissing($storedPath);
});
