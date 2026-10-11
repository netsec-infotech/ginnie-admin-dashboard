<?php

use App\Enums\SettingTypeEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AdWalletService;
use App\Services\SettingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Helper: create a user without a UserFactory (this project doesn't ship one).
 */
function makeAdsUser(array $overrides = []): User
{
    static $counter = 0;
    $counter++;

    return User::create(array_merge([
        'name'     => 'Ads Tester ' . $counter,
        'email'    => 'ads-tester-' . $counter . '-' . uniqid() . '@example.test',
        'password' => 'secret-password',
    ], $overrides));
}

/**
 * Persist the advertisement settings JSON row directly.
 */
function setAdsSettings(array $values): void
{
    app(SettingService::class)->saveSetting(SettingTypeEnum::ADVERTISEMENT(), $values);
}

beforeEach(function () {
    $this->adWalletService = app(AdWalletService::class);
    $this->walletService = app(WalletService::class);

    setAdsSettings([
        'featureEnabled' => true,
        'cpcRate' => 0.05,
        'walletMinTopup' => 5.00,
        'broadcastDriver' => 'pusher',
    ]);
});

it('lazy-creates the seller_ad wallet on first access', function () {
    $user = makeAdsUser();

    $result = $this->adWalletService->getAdWallet($user->id);

    expect($result['success'])->toBeTrue();
    expect($result['data']->type->value)->toBe(WalletTypeEnum::SELLER_AD->value);
    expect((float) $result['data']->balance)->toBe(0.0);
    expect(Wallet::where('user_id', $user->id)->where('type', WalletTypeEnum::SELLER_AD->value)->count())->toBe(1);
});

it('blocks gateway top-up when the master toggle is off', function () {
    $user = makeAdsUser();
    setAdsSettings(['featureEnabled' => false, 'walletMinTopup' => 5.00]);

    $result = $this->adWalletService->prepareGatewayTopUp($user->id, [
        'amount' => 50,
        'payment_method' => 'razorpay',
    ]);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('labels.ad_feature_disabled'));
});

it('rejects gateway top-up below the configured minimum', function () {
    $user = makeAdsUser();
    setAdsSettings(['featureEnabled' => true, 'walletMinTopup' => 10.00]);

    $result = $this->adWalletService->prepareGatewayTopUp($user->id, [
        'amount' => 5,
        'payment_method' => 'razorpay',
    ]);

    expect($result['success'])->toBeFalse();
});

it('atomically transfers from earnings wallet to ad wallet', function () {
    $user = makeAdsUser();

    // Seed earnings wallet with 100.
    $this->walletService->addBalance($user->id, ['amount' => 100, 'payment_method' => 'test'], WalletTypeEnum::SELLER());

    $result = $this->adWalletService->transferFromEarningWallet($user->id, 30.00);

    expect($result['success'])->toBeTrue();

    $earningsWallet = Wallet::where('user_id', $user->id)->where('type', WalletTypeEnum::SELLER->value)->first();
    $adWallet = Wallet::where('user_id', $user->id)->where('type', WalletTypeEnum::SELLER_AD->value)->first();

    expect((float) $earningsWallet->balance)->toBe(70.0);
    expect((float) $adWallet->balance)->toBe(30.0);

    // Both ledgers wrote a transaction row.
    expect(WalletTransaction::where('wallet_id', $earningsWallet->id)
        ->where('transaction_type', WalletTransactionTypeEnum::PAYMENT())
        ->where('amount', 30.00)->count())->toBe(1);

    expect(WalletTransaction::where('wallet_id', $adWallet->id)
        ->where('transaction_type', WalletTransactionTypeEnum::DEPOSIT())
        ->where('amount', 30.00)->count())->toBe(1);
});

it('refuses earnings transfer when balance is insufficient', function () {
    $user = makeAdsUser();
    $this->walletService->addBalance($user->id, ['amount' => 10, 'payment_method' => 'test'], WalletTypeEnum::SELLER());

    $result = $this->adWalletService->transferFromEarningWallet($user->id, 100.00);

    expect($result['success'])->toBeFalse();

    // Earnings wallet balance unchanged; ad wallet not created or zero.
    $earningsWallet = Wallet::where('user_id', $user->id)->where('type', WalletTypeEnum::SELLER->value)->first();
    expect((float) $earningsWallet->balance)->toBe(10.0);
});

it('refuses earnings transfer when feature is disabled', function () {
    $user = makeAdsUser();
    $this->walletService->addBalance($user->id, ['amount' => 100, 'payment_method' => 'test'], WalletTypeEnum::SELLER());
    setAdsSettings(['featureEnabled' => false, 'walletMinTopup' => 5.00]);

    $result = $this->adWalletService->transferFromEarningWallet($user->id, 30.00);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe(__('labels.ad_feature_disabled'));
});

it('promoCredit credits the ad wallet and writes a transaction row', function () {
    $user = makeAdsUser();

    $result = $this->adWalletService->promoCredit($user->id, 25.00, 'Welcome bonus');

    expect($result['success'])->toBeTrue();

    $adWallet = Wallet::where('user_id', $user->id)->where('type', WalletTypeEnum::SELLER_AD->value)->first();
    expect((float) $adWallet->balance)->toBe(25.0);

    $transaction = WalletTransaction::where('wallet_id', $adWallet->id)->latest('id')->first();
    expect($transaction->payment_method)->toBe('admin_promo_credit');
    expect((float) $transaction->amount)->toBe(25.0);
});

it('adjust applies signed amounts and rejects below-zero balance', function () {
    $user = makeAdsUser();
    $this->adWalletService->promoCredit($user->id, 10.00);

    $debit = $this->adWalletService->adjust($user->id, -3.00, 'Manual debit');
    expect($debit['success'])->toBeTrue();

    $adWallet = Wallet::where('user_id', $user->id)->where('type', WalletTypeEnum::SELLER_AD->value)->first();
    expect((float) $adWallet->balance)->toBe(7.0);

    // Cannot push the wallet below zero.
    $tooBig = $this->adWalletService->adjust($user->id, -100.00, 'Should fail');
    expect($tooBig['success'])->toBeFalse();
    expect((float) $adWallet->fresh()->balance)->toBe(7.0);
});
