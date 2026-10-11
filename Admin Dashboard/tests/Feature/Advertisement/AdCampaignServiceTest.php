<?php

use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Models\AdCampaign;
use App\Models\Seller;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AdCampaignService;
use App\Services\AdWalletService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a seller user with an ad wallet pre-funded to $balance.
 */
function makeFundedSeller(float $balance = 100.00): array
{
    $user   = User::factory()->create(['access_panel' => GuardNameEnum::SELLER->value]);
    $seller = Seller::factory()->create(['user_id' => $user->id]);

    $wallet = Wallet::create([
        'user_id'       => $user->id,
        'type'          => WalletTypeEnum::SELLER_AD(),
        'balance'       => $balance,
        'currency_code' => 'USD',
    ]);

    return compact('user', 'seller', 'wallet');
}

/**
 * Insert a bare-minimum product row directly (bypasses FKs — SQLite in-memory).
 */
function makeProduct(int $sellerId): int
{
    return DB::table('products')->insertGetId([
        'uuid'               => (string) \Illuminate\Support\Str::uuid(),
        'seller_id'          => $sellerId,
        'category_id'        => 1,
        'product_condition_id' => 1,
        'title'              => 'Test Product ' . uniqid(),
        'slug'               => 'test-product-' . uniqid(),
        'short_description'  => 'Short desc',
        'description'        => 'Full description',
        'tags'               => '[]',
        'status'             => 'approved',
        'minimum_order_quantity' => 1,
        'quantity_step_size' => 1,
        'total_allowed_quantity' => 1,
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);
}

/**
 * Seed advertisement settings.
 */
function setCampaignSettings(array $values = []): void
{
    app(SettingService::class)->saveSetting(SettingTypeEnum::ADVERTISEMENT(), array_merge([
        'featureEnabled'   => true,
        'cpcRate'          => 1.00,
        'walletMinTopup'   => 5.00,
    ], $values));
}

/**
 * Make an admin user.
 */
function makeAdmin(): User
{
    return User::factory()->create(['access_panel' => GuardNameEnum::ADMIN->value]);
}

// ---------------------------------------------------------------------------
// Service tests
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->service = app(AdCampaignService::class);
    setCampaignSettings();
});

// ── create() ────────────────────────────────────────────────────────────────

it('returns failure when the ads feature is disabled', function () {
    setCampaignSettings(['featureEnabled' => false]);

    ['user' => $user, 'seller' => $seller] = makeFundedSeller(50.00);
    $productId = makeProduct($seller->id);

    $result = $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('labels.ad_feature_disabled');
});

it('returns failure when wallet balance is insufficient', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(5.00);
    $productId = makeProduct($seller->id);

    $result = $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 50.00]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('labels.ad_insufficient_wallet_balance');
});

it('returns failure when CPC rate is zero', function () {
    setCampaignSettings(['cpcRate' => 0]);

    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);

    $result = $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('labels.ad_cpc_rate_not_configured');
});

it('creates a campaign and deducts wallet on happy path', function () {
    ['user' => $user, 'seller' => $seller, 'wallet' => $wallet] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);

    $result = $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 30.00]);

    expect($result['success'])->toBeTrue();

    // Campaign persisted with correct status
    $campaign = AdCampaign::first();
    expect($campaign)->not->toBeNull();
    expect($campaign->status)->toBe(AdCampaignStatusEnum::PENDING_APPROVAL);
    expect((float) $campaign->budget)->toBe(30.00);
    expect((float) $campaign->spent)->toBe(0.00);

    // Wallet debited
    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(70.00);
});

it('defaults placements to all slots', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);

    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 10.00]);

    $campaign = AdCampaign::first();
    expect($campaign->placements)->toContain('search');
    expect($campaign->placements)->toContain('related');
});

it('snapshots the CPC rate at campaign creation time', function () {
    setCampaignSettings(['cpcRate' => 2.50]);

    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);

    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 10.00]);

    $campaign = AdCampaign::first();
    expect((float) $campaign->cpc_rate_snapshot)->toBe(2.50);
});

// ── approve() ───────────────────────────────────────────────────────────────

it('admin approves a pending campaign — status becomes running', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    $admin    = makeAdmin();
    $campaign = AdCampaign::first();

    $result = $this->service->approve($campaign->id, $admin);

    expect($result['success'])->toBeTrue();
    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaignStatusEnum::RUNNING);
    expect($campaign->approved_by)->toBe($admin->id);
});

it('cannot approve a non-pending campaign', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    $admin    = makeAdmin();
    $campaign = AdCampaign::first();
    // Manually move to running first
    $campaign->update(['status' => AdCampaignStatusEnum::RUNNING()]);

    // Now try to approve again — should throw ModelNotFoundException (not pending)
    $result = $this->service->approve($campaign->id, $admin);
    expect($result['success'])->toBeFalse();
});

// ── reject() ────────────────────────────────────────────────────────────────

it('admin rejects a pending campaign and refunds the full budget', function () {
    ['user' => $user, 'seller' => $seller, 'wallet' => $wallet] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 40.00]);

    // Wallet should now be at 60.00
    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(60.00);

    $admin    = makeAdmin();
    $campaign = AdCampaign::first();

    $result = $this->service->reject($campaign->id, $admin, 'Product violates policy.');

    expect($result['success'])->toBeTrue();
    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaignStatusEnum::REJECTED);
    expect($campaign->rejection_reason)->toBe('Product violates policy.');

    // Budget refunded — wallet back to 100
    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(100.00);
});

// ── pause() / resume() ───────────────────────────────────────────────────────

it('seller can pause a running campaign', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    $campaign = AdCampaign::first();
    $campaign->update(['status' => AdCampaignStatusEnum::RUNNING()]);

    $result = $this->service->pause($campaign->id, $seller->id);

    expect($result['success'])->toBeTrue();
    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaignStatusEnum::PAUSED);
});

it('seller cannot pause a non-running campaign', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    $campaign = AdCampaign::first();
    // Status is pending_approval, not running
    $result = $this->service->pause($campaign->id, $seller->id);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('labels.ad_campaign_cannot_pause');
});

it('seller can resume a paused campaign', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    $campaign = AdCampaign::first();
    $campaign->update(['status' => AdCampaignStatusEnum::PAUSED()]);

    $result = $this->service->resume($campaign->id, $seller->id);

    expect($result['success'])->toBeTrue();
    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaignStatusEnum::RUNNING);
});

it('seller cannot resume an admin-paused campaign', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    $campaign = AdCampaign::first();
    $campaign->update(['status' => AdCampaignStatusEnum::PAUSED_BY_ADMIN()]);

    $result = $this->service->resume($campaign->id, $seller->id);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('labels.ad_campaign_cannot_resume');
});

// ── forceStop() ──────────────────────────────────────────────────────────────

it('admin force-stops a running campaign and refunds unspent budget', function () {
    ['user' => $user, 'seller' => $seller, 'wallet' => $wallet] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 50.00]);

    $campaign = AdCampaign::first();
    // Simulate 10.00 already spent
    $campaign->update([
        'status' => AdCampaignStatusEnum::RUNNING(),
        'spent'  => 10.00,
    ]);

    $admin  = makeAdmin();
    $result = $this->service->forceStop($campaign->id, $admin, 'Spam detected.');

    expect($result['success'])->toBeTrue();
    $campaign->refresh();
    expect($campaign->status)->toBe(AdCampaignStatusEnum::FORCE_STOPPED);
    expect($campaign->force_stop_reason)->toBe('Spam detected.');
    expect($campaign->force_stopped_by)->toBe($admin->id);
    expect($campaign->force_stopped_at)->not->toBeNull();

    // Remaining budget (40.00) refunded to wallet (started at 50.00 after campaign deduction)
    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(90.00); // 50 remaining + 40 refund
});

it('cannot force-stop an already-terminal campaign', function () {
    ['user' => $user, 'seller' => $seller] = makeFundedSeller(100.00);
    $productId = makeProduct($seller->id);
    $this->service->create($user->id, $seller->id, ['product_id' => $productId, 'budget' => 20.00]);

    $campaign = AdCampaign::first();
    $campaign->update(['status' => AdCampaignStatusEnum::COMPLETED()]);

    $admin  = makeAdmin();
    $result = $this->service->forceStop($campaign->id, $admin, 'reason');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('labels.ad_campaign_already_terminal');
});

// ── AdCampaign model helpers ──────────────────────────────────────────────

it('computes spend_progress correctly', function () {
    $campaign = new AdCampaign(['budget' => 100.00, 'spent' => 25.00]);
    expect($campaign->spend_progress)->toBe(25);
});

it('spend_progress is capped at 100', function () {
    $campaign = new AdCampaign(['budget' => 100.00, 'spent' => 120.00]);
    expect($campaign->spend_progress)->toBe(100);
});

it('remaining_budget is never negative', function () {
    $campaign = new AdCampaign(['budget' => 50.00, 'spent' => 60.00]);
    expect((float) $campaign->remaining_budget)->toBe(0.00);
});

it('AdCampaignStatusEnum terminal list contains rejected completed force_stopped', function () {
    $terminal = AdCampaignStatusEnum::terminal();
    expect($terminal)->toContain(AdCampaignStatusEnum::REJECTED());
    expect($terminal)->toContain(AdCampaignStatusEnum::COMPLETED());
    expect($terminal)->toContain(AdCampaignStatusEnum::FORCE_STOPPED());
});

it('AdCampaignStatusEnum badge classes are defined for all cases', function () {
    foreach (AdCampaignStatusEnum::cases() as $case) {
        expect($case->badgeClass())->toBeString()->not->toBeEmpty();
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
