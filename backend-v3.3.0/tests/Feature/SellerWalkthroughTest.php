<?php

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\User;
use App\Services\LanguageService;
use App\Services\SellerTourCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

function walkthroughUser(bool $owner = true, array $permissions = []): User
{
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 123;
    $user->shouldReceive('hasRole')->andReturnUsing(fn ($role) => $owner && (string) $role === (string) DefaultSystemRolesEnum::SELLER());
    $user->shouldReceive('hasPermissionTo')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));

    return $user;
}

function walkthroughFeatures(bool $enabled = true): array
{
    return array_fill_keys(['pos', 'scheduled', 'subscription', 'ads', 'watch', 'stories'], $enabled);
}

it('resolves every seller walkthrough to translated content and registered GET routes', function () {
    $catalog = app(SellerTourCatalog::class)->forUser(walkthroughUser(), walkthroughFeatures());

    expect($catalog)->toHaveCount(42);
    foreach ($catalog as $id => $tour) {
        expect($id)->toStartWith('seller_')
            ->and($tour['title'])->not->toStartWith('labels.seller_tours.')
            ->and($tour['description'])->not->toStartWith('labels.seller_tours.')
            ->and($tour['group'])->not->toStartWith('labels.seller_tours.');

        foreach ($tour['stages'] as $stage) {
            expect(Route::has($stage['route']))->toBeTrue()
                ->and(Route::getRoutes()->getByName($stage['route'])->methods())->toContain('GET')
                ->and($stage['steps'])->not->toBeEmpty();
            foreach ($stage['steps'] as $step) {
                expect($step['title'])->not->toStartWith('labels.seller_tours.')
                    ->and($step['description'])->not->toStartWith('labels.seller_tours.')
                    ->and($step['selector'])->not->toBeEmpty();
                if (isset($step['permission'])) {
                    expect(SellerPermissionEnum::values())->toContain($step['permission']);
                }
                if (isset($step['reveal'])) {
                    expect(['modal', 'tab', 'wizard'])->toContain($step['reveal']['type']);
                }
            }
        }
    }
});

it('hides inaccessible cards and filters action steps for restricted staff', function () {
    $catalog = app(SellerTourCatalog::class)->forUser(walkthroughUser(false, ['product.view', 'order.view']), walkthroughFeatures());

    expect(array_keys($catalog))->toEqualCanonicalizing(['seller_product_list', 'seller_orders', 'seller_order_details', 'seller_scheduled_orders']);
    $productSteps = $catalog['seller_product_list']['stages'][0]['steps'];
    expect(array_column($productSteps, 'selector'))->not->toContain('#add-product-btn');
    expect(app(SellerTourCatalog::class)->forUser(walkthroughUser(false), walkthroughFeatures()))->toBe([])
        ->and(app(SellerTourCatalog::class)->forUser(null))->toBe([]);
});

it('hides disabled feature cards even for seller owners', function () {
    $catalog = app(SellerTourCatalog::class)->forUser(walkthroughUser(), walkthroughFeatures(false));

    foreach (['seller_pos_checkout', 'seller_pos_refunds', 'seller_scheduled_orders', 'seller_subscriptions', 'seller_ad_dashboard', 'seller_stories', 'seller_reels'] as $id) {
        expect($catalog)->not->toHaveKey($id);
    }
    expect($catalog)->toHaveKey('seller_store_configuration');
    expect(array_column($catalog['seller_store_configuration']['stages'][0]['steps'], 'selector'))->not->toContain('#pills-pos', '#pills-scheduled-delivery');
});

it('removes inaccessible continuation pages rather than navigating staff to forbidden forms', function () {
    $catalog = app(SellerTourCatalog::class)->forUser(walkthroughUser(false, ['addon_group.view', 'role.view']), walkthroughFeatures());

    expect($catalog['seller_addon_groups']['stages'])->toHaveCount(1)
        ->and($catalog['seller_roles']['stages'])->toHaveCount(1);
});

it('uses named routes with deployment URL prefixes and record placeholders', function () {
    URL::forceRootUrl('https://example.test/panel');
    URL::forceScheme('https');
    try {
        $catalog = app(SellerTourCatalog::class)->forUser(walkthroughUser(), walkthroughFeatures());
        expect($catalog['seller_product_create']['stages'][0]['url'])->toBe('https://example.test/panel/seller/products/create')
            ->and($catalog['seller_product_edit']['stages'][0]['url'])->toBe('https://example.test/panel/seller/products/:record/edit')
            ->and($catalog['seller_product_edit']['stages'][0]['listingUrl'])->toBe('https://example.test/panel/seller/products');
    } finally {
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
});

it('renders only eligible seller groups and exposes safe translated JSON', function () {
    $user = walkthroughUser(false, ['notification.view']);
    Auth::setUser($user);
    $catalog = app(SellerTourCatalog::class)->forUser($user, walkthroughFeatures());
    $catalog['seller_notifications']['title'] = '</script><script>alert(1)</script>';
    $service = Mockery::mock(SellerTourCatalog::class);
    $service->shouldReceive('forUser')->once()->with($user)->andReturn($catalog);
    app()->instance(SellerTourCatalog::class, $service);
    $request = Request::create('/seller/dashboard');
    $request->setRouteResolver(fn () => Route::getRoutes()->getByName('seller.dashboard'));
    app()->instance('request', $request);

    $html = view('layouts.partials._tour_drawer')->render();
    expect($html)->toContain('seller-tour-catalog', 'seller_notifications', 'Team &amp; Communication')
        ->not->toContain('data-tour-start="seller_pos_checkout"', 'Store Operations', '</script><script>alert(1)</script>');
    preg_match('/id="seller-tour-catalog">(.*?)<\/script>/s', $html, $matches);
    $payload = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    expect($payload['userId'])->toBe('123')
        ->and($payload['text']['close'])->toBe(__('labels.seller_tours.close'))
        ->and($payload['tours'])->toHaveCount(1);
});

it('registers all seller walkthrough text in the dynamic panel language source', function () {
    $source = app(LanguageService::class)->source();
    foreach (config('tours.seller') as $tour) {
        foreach (['title', 'description', 'group'] as $field) {
            expect($source)->toHaveKey(substr($tour[$field], strlen('labels.')));
        }
        foreach ($tour['stages'] as $stage) {
            foreach ($stage['steps'] as $step) {
                expect($source)->toHaveKey(substr($step['title'], strlen('labels.')))
                    ->and($source)->toHaveKey(substr($step['description'], strlen('labels.')));
            }
        }
    }
    expect($source)->toHaveKey('seller_tours.close')
        ->and($source)->toHaveKey('seller_tours.progress')
        ->and(file_exists(lang_path('en/seller_tours.php')))->toBeFalse();
});

it('provides a translation fallback and leaves admin tour configuration intact', function () {
    app()->setLocale('unknown-locale');
    $catalog = app(SellerTourCatalog::class)->forUser(walkthroughUser(), walkthroughFeatures());
    expect($catalog['seller_dashboard']['title'])->toBe('Dashboard & Reports')
        ->and(config('tours.admin'))->not->toBeEmpty();
    app()->setLocale('en');
    expect(Lang::has('labels.seller_tours.select_record'))->toBeTrue();
});
