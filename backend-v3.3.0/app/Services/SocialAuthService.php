<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\UserLoginTypeEnum;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth\UserRecord;

class SocialAuthService
{
    public function __construct(
        protected SettingService $settingService,
        protected WalletService $walletService,
        protected ReferralService $referralService,
    ) {}

    /**
     * Find or create a user from a verified Google Firebase profile.
     * Google always ships an email, so we match on email first, then on
     * firebase_uid, before creating.
     *
     * @param  array<string, mixed>  $claims Optional: token claims.
     * @param  array<string, mixed>  $extra Optional: country, iso_2.
     * @return array{user: User, is_new: bool}
     */
    public function loginOrRegisterFromGoogle(
        UserRecord $firebaseUser,
        array $claims = [],
        ?string $friendsCode = null,
        array $extra = [],
    ): array {
        $uid      = $firebaseUser->uid;
        $email    = $firebaseUser->email ?? ($claims['email'] ?? null);
        $photoUrl = $firebaseUser->photoUrl ?? ($claims['picture'] ?? null);

        $user = null;
        if (!empty($email)) {
            $user = User::where('email', $email)->first();
        }
        if (!$user) {
            $user = User::where('firebase_uid', $uid)->first();
        }

        if ($user) {
            $this->backfill($user, [
                'firebase_uid'      => $uid,
                'email'             => $email,
                'email_verified_at' => $firebaseUser->emailVerified ? now() : null,
            ]);
            $this->stampLoginType($user, UserLoginTypeEnum::GOOGLE);
            $this->syncProfilePhoto($user, $photoUrl);

            return ['user' => $user, 'is_new' => false];
        }

        $user = User::create([
            'name'              => $firebaseUser->displayName ?: ($email ?: 'User'),
            'email'             => $email,
            'firebase_uid'      => $uid,
            'email_verified_at' => $firebaseUser->emailVerified ? now() : null,
            'friends_code'      => $friendsCode,
            'country'           => $extra['country'] ?? null,
            'iso_2'             => $extra['iso_2'] ?? null,
            'logged_in_type'    => UserLoginTypeEnum::GOOGLE(),
        ]);

        $this->syncProfilePhoto($user, $photoUrl, force: true);
        $this->postRegistrationHooks($user, $friendsCode);

        return ['user' => $user, 'is_new' => true];
    }

    /**
     * Find or create a user from a verified Apple Firebase profile.
     * Apple may withhold the email — firebase_uid is the only stable id,
     * so we look that up first, fall back to email when present.
     *
     * @param  array<string, mixed>  $claims Verified ID token claims (email / email_verified / name may live here when Firebase's UserRecord lacks them).
     * @param  array<string, mixed>  $extra  Optional: country, iso_2.
     * @return array{user: User, is_new: bool}
     */
    public function loginOrRegisterFromApple(
        UserRecord $firebaseUser,
        array $claims = [],
        ?string $friendsCode = null,
        array $extra = [],
    ): array {
        $uid           = $firebaseUser->uid;
        $email         = $firebaseUser->email ?? ($claims['email'] ?? null);
        $displayName   = $firebaseUser->displayName ?? ($claims['name'] ?? null);
        $photoUrl      = $firebaseUser->photoUrl ?? ($claims['picture'] ?? null);
        // Firebase may omit Apple email details from UserRecord while the verified token claims include them.
        $emailVerified = empty($firebaseUser->email) && array_key_exists('email_verified', $claims)
            ? (bool) $claims['email_verified']
            : (bool) $firebaseUser->emailVerified;

        $user = User::where('firebase_uid', $uid)->first();
        if (! $user && ! empty($email)) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $this->backfill($user, [
                'firebase_uid'      => $uid,
                'email'             => $email,
                'email_verified_at' => $emailVerified ? now() : null,
            ]);
            $this->stampLoginType($user, UserLoginTypeEnum::APPLE);
            $this->syncProfilePhoto($user, $photoUrl);

            return ['user' => $user, 'is_new' => false];
        }

        $user = User::create([
            'name'              => $displayName ?: ($email ?: 'Apple User'),
            'email'             => $email,
            'firebase_uid'      => $uid,
            'email_verified_at' => $emailVerified ? now() : null,
            'friends_code'      => $friendsCode,
            'country'           => $extra['country'] ?? null,
            'iso_2'             => $extra['iso_2'] ?? null,
            'logged_in_type'    => UserLoginTypeEnum::APPLE(),
        ]);

        $this->syncProfilePhoto($user, $photoUrl, force: true);
        $this->postRegistrationHooks($user, $friendsCode);

        return ['user' => $user, 'is_new' => true];
    }

    /**
     * Download and attach the external profile photo if available and not yet set.
     */
    protected function syncProfilePhoto(User $user, ?string $photoUrl, bool $force = false): void
    {
        if (empty($photoUrl) || !filter_var($photoUrl, FILTER_VALIDATE_URL)) {
            return;
        }

        // Only download if forcing (new registration) or user has no existing profile image
        if (!$force && $user->hasMedia(SpatieMediaCollectionName::PROFILE_IMAGE())) {
            return;
        }

        try {
            $user->addMediaFromUrl($photoUrl)
                ->usingFileName('avatar_' . $user->id . '_' . time() . '.jpg')
                ->toMediaCollection(SpatieMediaCollectionName::PROFILE_IMAGE());
        } catch (\Throwable $e) {
            Log::warning("Failed to sync profile photo for user {$user->id} from URL {$photoUrl}: " . $e->getMessage());
        }
    }

    /**
     * Update logged_in_type to reflect the provider just used. Unlike
     * backfill(), this overwrites — a user who signs in with a different
     * provider this time should show that provider.
     */
    protected function stampLoginType(User $user, UserLoginTypeEnum $type): void
    {
        if ($user->logged_in_type?->value === $type->value) {
            return;
        }
        $user->update(['logged_in_type' => $type]);
    }

    /**
     * Patch a returning user's record with any Firebase-provided fields that
     * are still missing locally. Only fills blanks — never overwrites.
     *
     * @param  array<string, mixed>  $candidates
     */
    protected function backfill(User $user, array $candidates): void
    {
        $updates = [];
        foreach ($candidates as $column => $value) {
            if (empty($value)) {
                continue;
            }
            if (empty($user->{$column})) {
                $updates[$column] = $value;
            }
        }

        if (! empty($updates)) {
            $user->update($updates);
        }
    }

    /**
     * Welcome wallet credit + friends_code referral linking for new signups.
     * Failures are logged but never bubble up — signup must not fail when
     * these side effects do.
     */
    protected function postRegistrationHooks(User $user, ?string $friendsCode): void
    {
        try {
            $systemSettings = $this->settingService
                ->getSettingByVariable(SettingTypeEnum::SYSTEM())
                ?->toArray(request())['value'] ?? [];
            $welcomeAmount = (float) ($systemSettings['welcomeWalletBalanceAmount'] ?? 0);

            if ($welcomeAmount > 0) {
                $this->walletService->addBalance($user->id, [
                    'amount'         => $welcomeAmount,
                    'payment_method' => 'system',
                    'description'    => __('labels.welcome_wallet_bonus') ?? 'Welcome bonus added to wallet',
                ]);
            }
        } catch (\Throwable $th) {
            Log::error('Welcome wallet credit failed for user ' . $user->id . ': ' . $th->getMessage());
        }

        if (! empty($friendsCode)) {
            try {
                $this->referralService->handleRegistration($user, $friendsCode);
            } catch (\Throwable $th) {
                Log::error('Referral link creation failed for user ' . $user->id . ': ' . $th->getMessage());
            }
        }
    }
}
