<?php

namespace Database\Seeders;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PosFoundationSeeder extends Seeder
{
    private const PLACEHOLDER_EMAIL = 'walkin@system.local';

    public function run(): void
    {
        $walkinUserId = $this->ensureWalkinPlaceholderUser();
        $this->ensurePosSettings($walkinUserId);
    }

    /**
     * Create the system-wide walk-in placeholder user if it doesn't exist
     * and ensure it carries the customer role. Returns its id either way.
     *
     * The user is intentionally inactive so it can never be used to log in.
     * Its password is a long random hash.
     */
    private function ensureWalkinPlaceholderUser(): int
    {
        $existing = DB::table('users')->where('email', self::PLACEHOLDER_EMAIL)->first();
        if ($existing) {
            $userId = (int) $existing->id;
        } else {
            $userId = (int) DB::table('users')->insertGetId([
                'name'               => 'Walk-in Customer',
                'email'              => self::PLACEHOLDER_EMAIL,
                'mobile'             => null,
                'country_code'       => null,
                'email_verified_at'  => null,
                'mobile_verified_at' => null,
                'password'           => Hash::make(Str::random(64)),
                'access_panel'       => GuardNameEnum::WEB(),
                'logged_in_type'     => UserLoginTypeEnum::PLATFORM(),
                'iso_2'              => null,
                'country'            => null,
                'status'             => ActiveInactiveStatusEnum::INACTIVE(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        // Ensure customer role assignment via Spatie's model_has_roles pivot.
        $customerRole = DB::table('roles')
            ->where('name', DefaultSystemRolesEnum::CUSTOMER())
            ->first();
        if ($customerRole) {
            $alreadyAssigned = DB::table('model_has_roles')
                ->where('role_id', $customerRole->id)
                ->where('model_type', User::class)
                ->where('model_id', $userId)
                ->exists();
            if (!$alreadyAssigned) {
                DB::table('model_has_roles')->insert([
                    'role_id'    => $customerRole->id,
                    'model_type' => User::class,
                    'model_id'   => $userId,
                ]);
            }
        }

        return $userId;
    }

    /**
     * Seed pos_settings row with the walkin user id and an empty default
     * receipt template (admin will configure this later via the POS settings panel).
     */
    private function ensurePosSettings(int $walkinUserId): void
    {
        $existing = DB::table('settings')->where('variable', 'pos_settings')->first();
        $value = $existing ? (json_decode($existing->value, true) ?: []) : [];

        $defaults = [
            'walkin_user_id'           => $walkinUserId,
            'default_receipt_template' => null,
        ];
        $merged = array_merge($defaults, $value);
        // Always overwrite walkin_user_id since it's load-bearing for POS order creation.
        $merged['walkin_user_id'] = $walkinUserId;

        DB::table('settings')->updateOrInsert(
            ['variable' => 'pos_settings'],
            ['value' => json_encode($merged), 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
