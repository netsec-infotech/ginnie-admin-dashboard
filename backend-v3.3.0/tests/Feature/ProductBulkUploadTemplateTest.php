<?php

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Models\Language;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\CatalogLanguageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware();
    Language::updateOrCreate(['surface' => 'web', 'code' => 'es'], [
        'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr',
        'is_active' => true, 'is_default' => false, 'sort_order' => 2,
    ]);
    app(CatalogLanguageService::class)->clearCache();
});

it('shows product translation template links in both panels', function (string $panel): void {
    $user = User::factory()->create(['access_panel' => $panel]);
    $role = $panel === 'admin' ? DefaultSystemRolesEnum::SUPER_ADMIN->value : DefaultSystemRolesEnum::SELLER->value;
    Role::findOrCreate($role, $panel);
    $user->assignRole($role);
    if ($panel === 'seller') {
        Seller::factory()->create(['user_id' => $user->id]);
    }
    $this->actingAs($user, $panel);

    $this->get(route("{$panel}.products.bulk-upload.page"))
        ->assertSuccessful()
        ->assertViewHas('supportsTranslation', true)
        ->assertViewHas('canTranslate', true)
        ->assertSee(__('labels.download_template'))
        ->assertSee(__('labels.download_translation_template'))
        ->assertSee(route("{$panel}.products.bulk-upload.template", ['locale' => 'es']), false);
})->with(['admin', 'seller']);

it('hides translation templates without product edit permission', function (): void {
    $user = User::factory()->create(['access_panel' => 'admin']);
    $user->givePermissionTo(Permission::findOrCreate(AdminPermissionEnum::PRODUCT_CREATE(), 'admin'));
    $this->actingAs($user, 'admin');

    $this->get(route('admin.products.bulk-upload.page'))
        ->assertSuccessful()
        ->assertViewHas('canTranslate', false)
        ->assertSee(__('labels.download_template'))
        ->assertDontSee(__('labels.download_translation_template'));
});

it('downloads seller translation templates containing only owned products even with selected IDs', function (): void {
    $user = User::factory()->create(['access_panel' => 'seller']);
    $seller = Seller::factory()->create(['user_id' => $user->id]);
    Role::findOrCreate(DefaultSystemRolesEnum::SELLER->value, 'seller');
    $user->assignRole(DefaultSystemRolesEnum::SELLER->value);
    $this->actingAs($user, 'seller');
    $owned = Product::factory()->create(['seller_id' => $seller->id, 'title' => ['en' => 'Owned Product']]);
    $other = Product::factory()->create(['title' => ['en' => 'Other Seller Product']]);

    foreach ([[], ['ids' => [$owned->id, $other->id]]] as $selection) {
        $response = $this->get(route('seller.products.bulk-upload.template', ['locale' => 'es', ...$selection]))
            ->assertSuccessful();
        expect($response->streamedContent())
            ->toStartWith("\xEF\xBB\xBFuuid,id,slug,")
            ->toContain('title_es', 'Owned Product')
            ->not->toContain('Other Seller Product');
    }
});
