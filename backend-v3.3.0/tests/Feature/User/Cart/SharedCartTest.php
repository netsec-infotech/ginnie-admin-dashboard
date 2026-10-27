<?php

use App\Enums\Addon\AddonGroupSelectionTypeEnum;
use App\Enums\Addon\AddonGroupStatusEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\VerifyLicense;
use App\Models\AddonGroup;
use App\Models\AddonItem;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\OrderPaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SharedCart;
use App\Models\Store;
use App\Models\StoreAddonItem;
use App\Models\StoreProductVariant;
use App\Models\StoreProductVariantAddon;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** @param array<int, array<string, mixed>> $items */
function createSharedCartSnapshot(User $user, string $token, array $items, $expiresAt = null): SharedCart
{
    return SharedCart::query()->create([
        'token_hash' => SharedCart::hashToken($token),
        'user_id' => $user->id,
        'title' => 'Shared groceries',
        'cart_data' => $items,
        'expires_at' => $expiresAt ?? now()->addDays(7),
    ]);
}

beforeEach(function () {
    $this->withoutMiddleware([VerifyLicense::class, CheckInstallation::class]);
    Event::fake();

    $this->cartService = app(CartService::class);
    $this->sender = User::forceCreate([
        'name' => 'Cart Sender',
        'email' => 'sender.'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'access_panel' => GuardNameEnum::WEB->value,
    ]);
    $this->recipient = User::forceCreate([
        'name' => 'Cart Recipient',
        'email' => 'recipient.'.uniqid().'@example.test',
        'password' => bcrypt('secret'),
        'access_panel' => GuardNameEnum::WEB->value,
    ]);
    $this->seller = Seller::factory()->create();
    $this->store = Store::forceCreate(testStoreData($this->seller->id, [
        'name' => 'Share Cart Store',
        'status' => 'online',
        'visibility_status' => 'visible',
        'verification_status' => 'approved',
        'is_temporarily_closed' => false,
    ]));
    $this->product = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Shared Milk',
        'status' => 'active',
        'verification_status' => 'approved',
        'total_allowed_quantity' => 20,
    ]));
    $this->variant = ProductVariant::forceCreate(testVariantData($this->product->id, [
        'title' => 'One litre',
        'availability' => true,
    ]));
    $this->storeVariant = StoreProductVariant::forceCreate([
        'product_variant_id' => $this->variant->id,
        'store_id' => $this->store->id,
        'sku' => 'SHARE-MILK',
        'price' => 50,
        'special_price' => 45,
        'cost' => 30,
        'stock' => 50,
    ]);

    $this->otherProduct = Product::forceCreate(testProductData($this->seller->id, [
        'title' => 'Shared Bread',
        'status' => 'active',
        'verification_status' => 'approved',
        'total_allowed_quantity' => 20,
    ]));
    $this->otherVariant = ProductVariant::forceCreate(testVariantData($this->otherProduct->id));
    $this->otherStoreVariant = StoreProductVariant::forceCreate([
        'product_variant_id' => $this->otherVariant->id,
        'store_id' => $this->store->id,
        'sku' => 'SHARE-BREAD',
        'price' => 30,
        'special_price' => 0,
        'cost' => 15,
        'stock' => 50,
    ]);

    $this->addonGroup = AddonGroup::query()->create([
        'seller_id' => $this->seller->id,
        'title' => 'Packaging',
        'selection_type' => AddonGroupSelectionTypeEnum::MULTIPLE->value,
        'is_required' => false,
        'status' => AddonGroupStatusEnum::ACTIVE->value,
    ]);
    $this->addonItem = AddonItem::query()->create([
        'addon_group_id' => $this->addonGroup->id,
        'title' => 'Glass bottle',
        'price' => 10,
        'is_available' => true,
        'status' => AddonGroupStatusEnum::ACTIVE->value,
    ]);
    $this->attachment = StoreProductVariantAddon::query()->create(testStoreProductVariantAddonData([
        'store_id' => $this->store->id,
        'product_variant_id' => $this->variant->id,
        'addon_group_id' => $this->addonGroup->id,
        'addon_item_id' => $this->addonItem->id,
    ]));
    $this->addonInventory = StoreAddonItem::factory()->create([
        'store_id' => $this->store->id,
        'addon_item_id' => $this->addonItem->id,
        'price' => 10,
        'cost' => 5,
        'stock' => 50,
        'is_available' => true,
    ]);

    $this->snapshot = [[
        'store_id' => $this->store->id,
        'product_id' => $this->product->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
        'addons' => [],
    ]];
});

test('share creation requires authentication and an active cart', function () {
    $this->postJson('/api/user/cart/share', ['title' => 'Weekly cart'])->assertUnauthorized();

    Sanctum::actingAs($this->sender);
    $this->postJson('/api/user/cart/share', ['title' => 'Weekly cart'])
        ->assertSuccessful()
        ->assertJsonPath('success', false);
});

test('creates a secure immutable snapshot and excludes save for later items', function () {
    $this->cartService->addToCart($this->sender, [
        'store_id' => $this->store->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 2,
        'addons' => [[
            'addon_group_id' => $this->addonGroup->id,
            'addon_item_id' => $this->addonItem->id,
        ]],
    ]);
    $cart = Cart::query()->where('user_id', $this->sender->id)->firstOrFail();
    CartItem::query()->create([
        'cart_id' => $cart->id,
        'product_id' => $this->otherProduct->id,
        'product_variant_id' => $this->otherVariant->id,
        'store_id' => $this->store->id,
        'quantity' => 1,
        'save_for_later' => true,
    ]);

    Sanctum::actingAs($this->sender);
    $response = $this->postJson('/api/user/cart/share', ['title' => 'Weekly cart'])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items_count', 1);

    $token = $response->json('data.share_code');
    expect($token)->toBeString()->toHaveLength(48)
        ->and($response->json('data.share_url'))->toEndWith('/cart/shared/'.$token);
    $this->assertDatabaseHas('shared_carts', ['token_hash' => SharedCart::hashToken($token)]);
    $this->assertDatabaseMissing('shared_carts', ['token_hash' => $token]);

    $sharedCart = SharedCart::query()->firstOrFail();
    expect($sharedCart->cart_data)->toHaveCount(1)
        ->and($sharedCart->cart_data[0]['addons'])->toHaveCount(1);

    $this->cartService->clearCart($this->sender);
    $this->getJson('/api/user/cart/shared/'.$token)
        ->assertSuccessful()
        ->assertJsonPath('data.summary.total_items', 1);
});

test('public preview uses live values and never exposes sender identity', function () {
    createSharedCartSnapshot($this->sender, 'PUBLIC-PREVIEW-TOKEN', [[
        ...$this->snapshot[0],
        'addons' => [[
            'addon_group_id' => $this->addonGroup->id,
            'addon_item_id' => $this->addonItem->id,
        ]],
    ]]);
    $this->storeVariant->update(['price' => 60, 'special_price' => 55]);

    $this->getJson('/api/user/cart/shared/PUBLIC-PREVIEW-TOKEN')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items.0.effective_unit_price', 65)
        ->assertJsonPath('data.items.0.total_price', 130)
        ->assertJsonPath('data.items.0.is_available', true)
        ->assertJsonMissingPath('data.shared_by')
        ->assertJsonMissingPath('data.creator_name');
});

test('preview validates tokens expiry coordinates and address ownership', function () {
    $this->getJson('/api/user/cart/shared/INVALID')->assertNotFound();
    createSharedCartSnapshot($this->sender, 'EXPIRED-TOKEN', $this->snapshot, now()->subMinute());
    $this->getJson('/api/user/cart/shared/EXPIRED-TOKEN')->assertStatus(410);

    createSharedCartSnapshot($this->sender, 'LOCATION-TOKEN', $this->snapshot);
    $this->getJson('/api/user/cart/shared/LOCATION-TOKEN?latitude=invalid&longitude=70')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['latitude']);

    $address = Address::query()->create([
        'user_id' => $this->sender->id,
        'address_line1' => 'One Main Street',
        'city' => 'Test City',
        'state' => 'Test State',
        'zipcode' => '12345',
        'mobile' => '9999999999',
        'address_type' => 'home',
        'country' => 'India',
        'country_code' => 'IN',
        'latitude' => 23.242,
        'longitude' => 69.6669,
    ]);

    $this->getJson('/api/user/cart/shared/LOCATION-TOKEN?address_id='.$address->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['address_id']);

    Sanctum::actingAs($this->recipient);
    $this->getJson('/api/user/cart/shared/LOCATION-TOKEN?address_id='.$address->id)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['address_id']);
});

test('merge synchronizes quantities and repeated imports leave sufficient quantities unchanged', function () {
    createSharedCartSnapshot($this->sender, 'MERGE-TOKEN', $this->snapshot);
    $this->cartService->addToCart($this->recipient, [
        'store_id' => $this->store->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
    ]);
    Sanctum::actingAs($this->recipient);

    $this->postJson('/api/user/cart/shared/MERGE-TOKEN/import', ['action' => 'merge'])
        ->assertSuccessful()
        ->assertJsonPath('data.import_summary.updated_count', 1)
        ->assertJsonPath('data.import_summary.unchanged_count', 0);

    $this->postJson('/api/user/cart/shared/MERGE-TOKEN/import', ['action' => 'merge'])
        ->assertSuccessful()
        ->assertJsonPath('data.import_summary.updated_count', 0)
        ->assertJsonPath('data.import_summary.unchanged_count', 1);

    $line = CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $this->recipient->id))->firstOrFail();
    expect($line->quantity)->toBe(2);
});

test('replace imports valid lines reports invalid lines and preserves saved items', function () {
    $this->cartService->addToCart($this->recipient, [
        'store_id' => $this->store->id,
        'product_variant_id' => $this->otherVariant->id,
        'quantity' => 1,
    ]);
    $cart = Cart::query()->where('user_id', $this->recipient->id)->firstOrFail();
    $cart->items()->firstOrFail()->update(['save_for_later' => true]);
    $this->otherStoreVariant->update(['stock' => 0]);
    createSharedCartSnapshot($this->sender, 'PARTIAL-REPLACE', [
        $this->snapshot[0],
        [
            'store_id' => $this->store->id,
            'product_id' => $this->otherProduct->id,
            'product_variant_id' => $this->otherVariant->id,
            'quantity' => 1,
            'addons' => [],
        ],
    ]);
    Sanctum::actingAs($this->recipient);

    $this->postJson('/api/user/cart/shared/PARTIAL-REPLACE/import', ['action' => 'replace'])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.import_summary.imported_count', 1)
        ->assertJsonPath('data.import_summary.skipped_count', 1);

    $cart->refresh();
    expect($cart->items()->where('save_for_later', '0')->pluck('product_variant_id')->all())
        ->toBe([$this->variant->id])
        ->and($cart->items()->where('save_for_later', '1')->count())->toBe(1);
});

test('replace leaves the existing cart untouched when every shared line is invalid', function () {
    $this->cartService->addToCart($this->recipient, [
        'store_id' => $this->store->id,
        'product_variant_id' => $this->otherVariant->id,
        'quantity' => 1,
    ]);
    $this->storeVariant->update(['stock' => 0]);
    createSharedCartSnapshot($this->sender, 'INVALID-REPLACE', $this->snapshot);
    Sanctum::actingAs($this->recipient);

    $this->postJson('/api/user/cart/shared/INVALID-REPLACE/import', ['action' => 'replace'])
        ->assertSuccessful()
        ->assertJsonPath('success', false);

    $line = CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $this->recipient->id))->firstOrFail();
    expect($line->product_variant_id)->toBe($this->otherVariant->id);
});

test('pending payment prevents replacement without clearing the cart', function () {
    $this->cartService->addToCart($this->recipient, [
        'store_id' => $this->store->id,
        'product_variant_id' => $this->otherVariant->id,
        'quantity' => 1,
    ]);
    OrderPaymentTransaction::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $this->recipient->id,
        'transaction_id' => 'pending-share-cart',
        'amount' => 100,
        'currency' => 'INR',
        'payment_method' => PaymentTypeEnum::XENDIT(),
        'payment_status' => PaymentStatusEnum::PENDING(),
    ]);
    createSharedCartSnapshot($this->sender, 'PAYMENT-GUARD', $this->snapshot);
    Sanctum::actingAs($this->recipient);

    $this->postJson('/api/user/cart/shared/PAYMENT-GUARD/import', ['action' => 'replace'])
        ->assertSuccessful()
        ->assertJsonPath('success', false);

    $line = CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $this->recipient->id))->firstOrFail();
    expect($line->product_variant_id)->toBe($this->otherVariant->id);
});

test('detached addons are unavailable while catalog-only nullable stock remains importable', function () {
    $snapshot = [[
        ...$this->snapshot[0],
        'addons' => [[
            'addon_group_id' => $this->addonGroup->id,
            'addon_item_id' => $this->addonItem->id,
        ]],
    ]];
    $this->addonInventory->forceDelete();
    createSharedCartSnapshot($this->sender, 'CATALOG-ADDON', $snapshot);

    $this->getJson('/api/user/cart/shared/CATALOG-ADDON')
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.is_available', true);

    $this->attachment->delete();
    createSharedCartSnapshot($this->sender, 'DETACHED-ADDON', $snapshot);
    $this->getJson('/api/user/cart/shared/DETACHED-ADDON')
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.is_available', false);

    $this->attachment->restore();
    $deletedInventory = StoreAddonItem::factory()->create([
        'store_id' => $this->store->id,
        'addon_item_id' => $this->addonItem->id,
        'price' => 10,
        'cost' => 5,
        'stock' => 50,
        'is_available' => true,
    ]);
    $deletedInventory->delete();
    createSharedCartSnapshot($this->sender, 'DELETED-INVENTORY', $snapshot);
    $this->getJson('/api/user/cart/shared/DELETED-INVENTORY')
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.is_available', false);
});

test('merge treats different addon selections as separate cart lines', function () {
    $this->cartService->addToCart($this->recipient, [
        'store_id' => $this->store->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 1,
        'addons' => [[
            'addon_group_id' => $this->addonGroup->id,
            'addon_item_id' => $this->addonItem->id,
        ]],
    ]);
    createSharedCartSnapshot($this->sender, 'ADDON-SIGNATURE', $this->snapshot);
    Sanctum::actingAs($this->recipient);

    $this->postJson('/api/user/cart/shared/ADDON-SIGNATURE/import', ['action' => 'merge'])
        ->assertSuccessful()
        ->assertJsonPath('data.import_summary.imported_count', 1);

    $cart = Cart::query()->where('user_id', $this->recipient->id)->firstOrFail();
    expect($cart->items()->where('save_for_later', '0')->count())->toBe(2);
});

test('replacement rolls back when a mutation fails after clearing', function () {
    $this->cartService->addToCart($this->recipient, [
        'store_id' => $this->store->id,
        'product_variant_id' => $this->otherVariant->id,
        'quantity' => 1,
    ]);
    createSharedCartSnapshot($this->sender, 'ROLLBACK-REPLACE', $this->snapshot);

    $this->partialMock(CartService::class, function ($mock): void {
        $mock->shouldReceive('clearCart')->once()->passthru();
        $mock->shouldReceive('addToCart')->once()->andReturn([
            'success' => false,
            'message' => 'Inventory changed.',
            'data' => [],
        ]);
        $mock->shouldReceive('getUserCart')->passthru();
    });
    Sanctum::actingAs($this->recipient);

    $this->postJson('/api/user/cart/shared/ROLLBACK-REPLACE/import', ['action' => 'replace'])
        ->assertSuccessful()
        ->assertJsonPath('success', false);

    $line = CartItem::query()->whereHas('cart', fn ($query) => $query->where('user_id', $this->recipient->id))->firstOrFail();
    expect($line->product_variant_id)->toBe($this->otherVariant->id);
});

test('different recipients can reuse the same shared link', function () {
    $anotherRecipient = User::factory()->create();
    createSharedCartSnapshot($this->sender, 'REUSABLE-LINK', $this->snapshot);

    Sanctum::actingAs($this->recipient);
    $this->postJson('/api/user/cart/shared/REUSABLE-LINK/import', ['action' => 'merge'])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    Sanctum::actingAs($anotherRecipient);
    $this->postJson('/api/user/cart/shared/REUSABLE-LINK/import', ['action' => 'merge'])
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    expect(Cart::query()->whereIn('user_id', [$this->recipient->id, $anotherRecipient->id])->count())->toBe(2);
});

test('preview and import agree on quantity and store ordering restrictions', function () {
    $this->product->update([
        'minimum_order_quantity' => 2,
        'quantity_step_size' => 2,
        'total_allowed_quantity' => 4,
    ]);
    $invalidQuantity = [[...$this->snapshot[0], 'quantity' => 3]];
    createSharedCartSnapshot($this->sender, 'QUANTITY-RULES', $invalidQuantity);

    $this->getJson('/api/user/cart/shared/QUANTITY-RULES')
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.is_available', false);

    Sanctum::actingAs($this->recipient);
    $this->postJson('/api/user/cart/shared/QUANTITY-RULES/import', ['action' => 'merge'])
        ->assertSuccessful()
        ->assertJsonPath('success', false);

    $this->store->update(['is_temporarily_closed' => true]);
    createSharedCartSnapshot($this->sender, 'CLOSED-STORE', $this->snapshot);
    $this->getJson('/api/user/cart/shared/CLOSED-STORE')
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.store_is_accepting_orders', false)
        ->assertJsonPath('data.items.0.is_available', false);
});

test('preview query count remains bounded as snapshot size grows', function () {
    createSharedCartSnapshot($this->sender, 'ONE-LINE', $this->snapshot);
    createSharedCartSnapshot($this->sender, 'MANY-LINES', array_fill(0, 10, $this->snapshot[0]));

    DB::enableQueryLog();
    $this->getJson('/api/user/cart/shared/ONE-LINE')->assertSuccessful();
    $singleCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    $this->getJson('/api/user/cart/shared/MANY-LINES')->assertSuccessful();
    $manyCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($manyCount)->toBeLessThanOrEqual($singleCount + 2);
});

test('rate limits share creation and validates import action', function () {
    Sanctum::actingAs($this->sender);

    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/user/cart/share')->assertSuccessful();
    }
    $this->postJson('/api/user/cart/share')->assertStatus(429);

    createSharedCartSnapshot($this->sender, 'ACTION-TOKEN', $this->snapshot);
    Sanctum::actingAs($this->recipient);
    $this->postJson('/api/user/cart/shared/ACTION-TOKEN/import', ['action' => 'append'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['action']);
});

test('rate limits imports per user and public previews per ip', function () {
    createSharedCartSnapshot($this->sender, 'RATE-LIMIT-TOKEN', $this->snapshot);
    Sanctum::actingAs($this->recipient);

    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/user/cart/shared/RATE-LIMIT-TOKEN/import', ['action' => 'merge'])
            ->assertSuccessful();
    }
    $this->postJson('/api/user/cart/shared/RATE-LIMIT-TOKEN/import', ['action' => 'merge'])
        ->assertStatus(429);

    auth('sanctum')->forgetUser();
    foreach (range(1, 60) as $attempt) {
        $this->getJson('/api/user/cart/shared/RATE-LIMIT-TOKEN')->assertSuccessful();
    }
    $this->getJson('/api/user/cart/shared/RATE-LIMIT-TOKEN')->assertStatus(429);
});

test('cleanup retains recently expired shares and removes shares expired seven days ago', function () {
    $recent = createSharedCartSnapshot($this->sender, 'RECENTLY-EXPIRED', [], now()->subDays(6));
    $old = createSharedCartSnapshot($this->sender, 'OLD-EXPIRED', [], now()->subDays(8));

    $this->artisan('cart:clean-expired-shares')->assertSuccessful();

    expect($recent->fresh())->not->toBeNull()
        ->and($old->fresh())->toBeNull();
});
