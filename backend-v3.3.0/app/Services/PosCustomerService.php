<?php

namespace App\Services;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Centralises POS customer search and quick-registration logic.
 *
 * Both the seller-panel web controller and the Sanctum API controller
 * delegate here so the rules (walk-in exclusion, duplicate detection,
 * role assignment) are defined in exactly one place.
 */
class PosCustomerService
{
    private const WALKIN_PLACEHOLDER_EMAIL = 'walkin@system.local';

    /**
     * Return the walk-in placeholder user, creating it and persisting
     * the pos_settings entry if it doesn't exist yet.
     */
    public function ensureWalkinPlaceholder(): User
    {
        $walkinId = (int) (Setting::posWalkinUserId() ?? 0);

        if ($walkinId > 0) {
            $user = User::find($walkinId);
            if ($user) {
                return $user;
            }
        }

        // Setting was lost but the user may still exist.
        $user = User::where('email', self::WALKIN_PLACEHOLDER_EMAIL)->first();

        if (!$user) {
            $user = User::create([
                'name'           => 'Walk-in Customer',
                'email'          => self::WALKIN_PLACEHOLDER_EMAIL,
                'password'       => Hash::make(Str::random(64)),
                'access_panel'   => GuardNameEnum::WEB(),
                'logged_in_type' => UserLoginTypeEnum::PLATFORM(),
                'status'         => ActiveInactiveStatusEnum::INACTIVE(),
            ]);

            $customerRole = DB::table('roles')
                ->where('name', DefaultSystemRolesEnum::CUSTOMER())
                ->first();
            if ($customerRole) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id'    => $customerRole->id,
                    'model_type' => User::class,
                    'model_id'   => $user->id,
                ]);
            }
        }

        $this->persistWalkinSetting($user->id);

        return $user;
    }

    /**
     * Write the walk-in user ID into pos_settings so subsequent look-ups
     * use the fast path via Setting::posWalkinUserId().
     */
    private function persistWalkinSetting(int $userId): void
    {
        $existing = DB::table('settings')->where('variable', 'pos_settings')->first();
        $value = $existing ? (json_decode($existing->value, true) ?: []) : [];
        $value['walkin_user_id'] = $userId;

        DB::table('settings')->updateOrInsert(
            ['variable' => 'pos_settings'],
            ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]
        );

        app(SettingService::class)->clearSettingCache('pos_settings');
    }

    /**
     * Search existing customers with the customer role, excluding the
     * walk-in placeholder user.
     */
    public function search(string $query, int $perPage = 15): LengthAwarePaginator
    {
        $walkinId = (int) (Setting::posWalkinUserId() ?? 0);
        $q = trim($query);

        $builder = User::query()
            ->where('status', ActiveInactiveStatusEnum::ACTIVE())
            ->when($walkinId > 0, fn($qq) => $qq->where('id', '!=', $walkinId))
            ->whereHas('roles', function ($qq) {
                $qq->where('name', DefaultSystemRolesEnum::CUSTOMER());
            });

        if ($q !== '') {
            $builder->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        return $builder->orderBy('id', 'desc')->paginate($perPage);
    }

    /**
     * Quick-register a new customer from the POS counter.
     *
     * If a user with the same mobile+country_code or email already exists,
     * returns a duplicate hint instead of inserting. This is the
     * "did you mean to search?" fallback.
     *
     * @param  array  $data  Validated input with keys: name, mobile, country_code, email?
     * @return array{success: bool, message: string, data: array}
     */
    public function quickRegister(array $data): array
    {
        $walkinId = (int) (Setting::posWalkinUserId() ?? 0);

        try {
            $existing = User::query()
                ->where('mobile', $data['mobile'])
                ->where('country_code', $data['country_code'])
                ->when($walkinId > 0, fn($qq) => $qq->where('id', '!=', $walkinId))
                ->first();

            if (!$existing && !empty($data['email'])) {
                $existing = User::query()
                    ->where('email', $data['email'])
                    ->when($walkinId > 0, fn($qq) => $qq->where('id', '!=', $walkinId))
                    ->first();
            }

            if ($existing) {
                return [
                    'success' => false,
                    'message' => __('labels.customer_already_exists_did_you_mean_to_search'),
                    'data'    => [
                        'reason'   => 'duplicate',
                        'customer' => $existing,
                    ],
                ];
            }

            $user = DB::transaction(function () use ($data) {
                $u = User::create([
                    'name'               => $data['name'],
                    'email'              => $data['email'] ?? null,
                    'mobile'             => $data['mobile'],
                    'country_code'       => $data['country_code'],
                    'email_verified_at'  => null,
                    'mobile_verified_at' => null,
                    'password'           => Hash::make(Str::random(64)),
                    'access_panel'       => GuardNameEnum::WEB(),
                    'logged_in_type'     => 'platform',
                    'status'             => ActiveInactiveStatusEnum::ACTIVE(),
                ]);
                $u->assignRole(DefaultSystemRolesEnum::CUSTOMER());
                return $u;
            });

            return [
                'success' => true,
                'message' => __('labels.customer_registered_successfully'),
                'data'    => ['customer' => $user],
            ];
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data'    => [],
            ];
        }
    }
}
