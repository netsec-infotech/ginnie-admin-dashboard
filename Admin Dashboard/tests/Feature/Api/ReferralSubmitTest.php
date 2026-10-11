<?php

use App\Enums\GuardNameEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Referral;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable the referral program for every test in this file. Individual
    // tests that need it disabled override this via the same service.
    app(SettingService::class)->saveSetting(SettingTypeEnum::SYSTEM(), [
        'referEarnStatus'              => true,
        'referEarnMinimumOrderAmount'  => 0,
        'referEarnNumberOfTimesBonus'  => 1,
        'referEarnMethodReferral'      => 'flat',
        'referEarnBonusReferral'       => 10,
        'referEarnMaximumBonusAmountReferral' => 100,
        'referEarnMethodUser'          => 'flat',
        'referEarnBonusUser'           => 5,
        'referEarnMaximumBonusAmountUser' => 50,
    ]);

    $this->referrer = User::forceCreate([
        'name'          => 'Referrer',
        'email'         => 'referrer.'.uniqid().'@example.test',
        'mobile'        => '+1555000'.random_int(1000, 9999),
        'referral_code' => 'REF-REFERRER1',
        'access_panel'  => GuardNameEnum::WEB->value,
    ]);

    $this->user = User::forceCreate([
        'name'          => 'Submitter',
        'email'         => 'submitter.'.uniqid().'@example.test',
        'mobile'        => '+1555111'.random_int(1000, 9999),
        'referral_code' => 'REF-SUBMITTER',
        'access_panel'  => GuardNameEnum::WEB->value,
    ]);
});

it('rejects unauthenticated callers', function () {
    $this->postJson('/api/user/referral/submit', ['friends_code' => 'REF-REFERRER1'])
        ->assertStatus(401);
});

it('returns referral_program_not_enabled when the program is disabled', function () {
    app(SettingService::class)->saveSetting(SettingTypeEnum::SYSTEM(), [
        'referEarnStatus' => false,
    ]);
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', ['friends_code' => 'REF-REFERRER1'])
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('labels.referral_program_not_enabled'));
});

it('applies a valid friends_code and creates a referral row', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', ['friends_code' => 'REF-REFERRER1'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('labels.referral_code_applied_successfully'))
        ->assertJsonPath('data.friends_code', 'REF-REFERRER1');

    $this->user->refresh();
    expect($this->user->friends_code)->toBe('REF-REFERRER1');
    expect($this->user->referral_prompt_dismissed_at)->toBeNull();

    expect(Referral::where('referred_id', $this->user->id)->where('referrer_id', $this->referrer->id)->exists())
        ->toBeTrue();
});

it('rejects an unknown friends_code with a 422', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', ['friends_code' => 'REF-DOESNTEXIST'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('friends_code');
});

it('dismisses the prompt when the payload is empty', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', [])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('labels.referral_prompt_dismissed_successfully'))
        ->assertJsonPath('data.friends_code', null);

    $this->user->refresh();
    expect($this->user->friends_code)->toBeNull();
    expect($this->user->referral_prompt_dismissed_at)->not->toBeNull();
});

it('dismisses the prompt when friends_code is an empty string', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', ['friends_code' => ''])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('labels.referral_prompt_dismissed_successfully'));

    $this->user->refresh();
    expect($this->user->referral_prompt_dismissed_at)->not->toBeNull();
});

it('dismisses the prompt when friends_code is null', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', ['friends_code' => null])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('labels.referral_prompt_dismissed_successfully'));

    $this->user->refresh();
    expect($this->user->referral_prompt_dismissed_at)->not->toBeNull();
});

it('returns 422 referral_already_decided when the user has already applied a code', function () {
    $this->user->update(['friends_code' => 'REF-REFERRER1']);
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', [])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('labels.referral_already_decided'));
});

it('returns 422 referral_already_decided when the user has already skipped', function () {
    $this->user->update(['referral_prompt_dismissed_at' => now()]);
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', ['friends_code' => 'REF-REFERRER1'])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('labels.referral_already_decided'));
});

it('blocks self-referral with a 422', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/user/referral/submit', ['friends_code' => $this->user->referral_code])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('labels.referral_self_not_allowed'));

    $this->user->refresh();
    expect($this->user->friends_code)->toBeNull();
    expect($this->user->referral_prompt_dismissed_at)->toBeNull();
});
