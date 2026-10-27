<?php

namespace App\Traits;

use App\Enums\DeliveryBoy\DeliveryBoyVerificationStatusEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SystemVendorTypeEnum;
use App\Enums\UserLoginTypeEnum;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserRegistered;
use App\Http\Requests\Auth\VerifyOtpRequest; // [CHANGE] Added VerifyOtpRequest for OTP verification parity
use App\Http\Resources\User\UserResource;
use App\Libs\libphonenumber\NumberParseException;
use App\Libs\libphonenumber\PhoneNumberUtil;
use App\Models\Country;
use App\Models\DeliveryBoy;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\OtpService; // [CHANGE] Added OtpService for SMS OTP sending and verification
use App\Services\ProfileService;
use App\Services\ReferralService;
use App\Services\SettingService;
use App\Services\WalletService;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator; // [CHANGE] Added Validator facade for OTP request validation
use Illuminate\Validation\ValidationException;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Factory;

trait AuthTrait
{
    /**
     * Store or update FCM token for a user if provided in the request
     */
    protected function storeFcmToken(Request $request, User $user): void
    {
        try {
            $fcmToken = $request->input('fcm_token');
            $deviceType = $request->input('device_type');
            $roleType = NotificationRoleTypeEnum::fromAuthContext(
                property_exists($this, 'role') ? $this->role : null
            ) ?? NotificationRoleTypeEnum::CUSTOMER;

            if (! empty($fcmToken) && ! empty($deviceType)) {
                UserFcmToken::updateOrCreate(
                    [
                        'fcm_token' => $fcmToken,
                    ],
                    [
                        'user_id' => $user->id,
                        'device_type' => $deviceType,
                        'role_type' => $roleType->value,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error('Error updating or creating FCM token: ' . $e->getMessage());
        }
    }

    /**
     * Build credentials and return identifier meta
     *
     * @return array{credentials: array, field: string, value: mixed}
     */
    protected function buildCredentials(Request $request, array $validated): array
    {
        $identifierField = $request->filled('email') ? 'email' : 'mobile';
        $identifierValue = $request->input($identifierField);

        return [
            'credentials' => [
                $identifierField => (string) $identifierValue,
                'password' => $validated['password'],
            ],
            'field' => $identifierField,
            'value' => $identifierValue,
        ];
    }

    /**
     * Perform optional role-based gate checks; returns a response if blocked, or null if allowed
     */
    protected function checkRoleAccess(?string $role, string $identifierField, $identifierValue): ?JsonResponse
    {
        if (! $role) {
            return null;
        }

        $user = User::where($identifierField, $identifierValue)->first();

        if (! $user) {
            return $this->invalidCredentials();
        }

        return match ($role) {
            'seller' => $this->checkSellerAccess($user),
            'admin' => $this->checkAdminAccess($user),
            'delivery_boy' => $this->checkDeliveryBoyAccess($user),
            default => null,
        };
    }

    protected function invalidCredentials(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('labels.invalid_credentials'),
            'data' => [],
        ]);
    }

    protected function isDemoModeEnabled(): bool
    {
        return Setting::isDemoModeEnabled();
    }

    /** Attempt to authenticate with given credentials */
    protected function attemptAuthentication(array $credentials): bool
    {
        return FacadesAuth::attempt($credentials);
    }

    protected function panelLoginRedirectUrl(Request $request): ?string
    {
        if (! $this->isPanelWebLogin($request)) {
            return null;
        }

        $role = property_exists($this, 'role') ? $this->role : null;

        return match ($role) {
            'admin' => redirect()->intended(route('admin.dashboard'))->getTargetUrl(),
            'seller' => redirect()->intended(route('seller.dashboard'))->getTargetUrl(),
            default => null,
        };
    }

    protected function isPanelWebLogin(Request $request): bool
    {
        return $request->routeIs('admin.login.post') || $request->routeIs('seller.login.post');
    }

    protected function checkSellerAccess(User $user): ?JsonResponse
    {
        if (Setting::systemType() !== SystemVendorTypeEnum::MULTIPLE()) {
            return null;
        }

        if (! empty($user->access_panel?->value) && $user->access_panel->value !== 'seller') {
            return $this->invalidCredentials();
        }

        $seller = $user->seller();

        if (! $seller) {
            return response()->json([
                'success' => false,
                'message' => __('labels.not_a_seller') ?? 'Not a seller account.',
                'data' => [],
            ], 403);
        }

        if ($seller->verification_status !== SellerVerificationStatusEnum::Approved()) {
            return response()->json([
                'success' => false,
                'message' => __('labels.account_not_verified') ?? 'Your seller account is not approved yet.',
                'data' => [
                    'verification_status' => $seller->verification_status,
                ],
            ], 403);
        }

        return null;
    }

    protected function checkAdminAccess(User $user): ?JsonResponse
    {
        if (! empty($user->access_panel?->value) && $user->access_panel->value !== 'admin') {
            return $this->invalidCredentials();
        }

        return null;
    }

    protected function checkDeliveryBoyAccess(User $user): ?JsonResponse
    {
        $deliveryBoy = DeliveryBoy::where('user_id', $user->id)->first();

        if (! $deliveryBoy) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.not_a_delivery_boy'),
                data: []
            );
        }

        if ($deliveryBoy->verification_status !== DeliveryBoyVerificationStatusEnum::VERIFIED) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.account_not_verified'),
                data: [
                    'verification_status' => $deliveryBoy->verification_status,
                    'verification_remark' => $deliveryBoy->verification_remark,
                ]
            );
        }

        // [CHANGE] Added is_blocked check for Delivery Boy to ensure consistent security across all auth flows
        if ($deliveryBoy->is_blocked) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.delivery_boy_account_blocked'),
                data: [
                    'blocked_reason' => $deliveryBoy->blocked_reason,
                    'blocked_at' => $deliveryBoy->blocked_at,
                ]
            );
        }

        return null;
    }

    /** Finalize successful login */
    protected function finalizeLogin(Request $request, ?User $user = null, ?bool $isRegister = null): JsonResponse
    {
        $user = $user ?? $request->user();
        $this->storeFcmToken($request, $user);
        $token = $user->createToken($user->email ?? ($user->mobile ?? 'api-token'))->plainTextToken;
        event(new UserLoggedIn($user));

        // Determine assigned permissions for seller logins
        $assignedPermissions = [];
        try {
            if (! empty($user->access_panel?->value) && $user->access_panel->value === 'seller') {
                $seller = $user->seller();
                if ($seller) {
                    // If the logged-in user is the main seller (owner of the seller record), grant all permissions
                    if ((int) $seller->user_id === (int) $user->id) {
                        $assignedPermissions = SellerPermissionEnum::values();
                    } else {
                        // For system users under a seller, fetch permissions assigned within the seller team context
                        if (function_exists('setPermissionsTeamId')) {
                            setPermissionsTeamId($seller->id);
                        }
                        $assignedPermissions = $user->getAllPermissions()->pluck('name')->toArray();
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fail silently; permissions are best-effort and should not break login
            Log::warning('Failed determining seller permissions on login: ' . $e->getMessage());
        }

        // Only callers that explicitly pass a flag (e.g. phoneCallback) get
        // is_register surfaced inside `data`; classic /api/login keeps its
        // historical shape untouched.
        $data = new UserResource($user);
        if (! is_null($isRegister)) {
            $data = $data->resolve($request);
            $data['is_register'] = $isRegister;
        }

        return response()->json([
            'success' => true,
            'message' => __('labels.login_successful'),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => $data,
            // Pass assigned permissions (array of names). For main seller, this will be the full SellerPermissionEnum values
            'assigned_permissions' => $assignedPermissions,
        ]);
    }

    /**
     * Login
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // 1) Validate input
            $validated = $request->validate([
                'email' => 'required_without:mobile|email',
                'mobile' => 'required_without:email|numeric',
                'password' => 'required',
            ]);

            // 2) Build credentials and identifier
            $meta = $this->buildCredentials($request, $validated);
            $credentials = $meta['credentials'];
            $identifierField = $meta['field'];
            $identifierValue = $meta['value'];

            // 3) Optional role-based access check (admin/seller)
            $role = property_exists($this, 'role') ? $this->role : null;
            if ($response = $this->checkRoleAccess($role, $identifierField, $identifierValue)) {
                return $response;
            }

            // 4) Attempt authentication
            if (! $this->attemptAuthentication($credentials)) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.invalid_credentials'),
                    data: []
                );
            }

            if ($this->isPanelWebLogin($request)) {
                $request->session()->regenerate();
            }

            $redirectUrl = $this->panelLoginRedirectUrl($request);

            // 5) Finalize login response
            $response = $this->finalizeLogin($request);

            if ($redirectUrl) {
                $payload = $response->getData(true);
                $payload['redirect_url'] = $redirectUrl;
                $payload['csrf_token'] = csrf_token();

                return response()->json($payload, $response->getStatusCode());
            }

            return $response;
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error') . ':- ' . $e->getMessage(),
                data: []
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.login_failed', ['error' => $e->getMessage()]),
                data: []
            );
        }
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|unique:users',
                'mobile' => 'required|numeric',
                'password' => 'required|string|min:6|confirmed',
                'country' => 'nullable|string|max:255',
                'country_code' => 'nullable|string|max:10',
                'iso_2' => 'nullable|string|max:2',
                'friends_code' => 'nullable|string|max:32|exists:users,referral_code',
                'idToken' => 'nullable|string',
            ]);

            // Generate a unique referral code for the new user
            $referralService = app(ReferralService::class);
            $referralCode = $referralService->generateCode();

            $mobile = (string) preg_replace('/\D+/', '', (string) $validated['mobile']);
            $countryCode = $validated['country_code'] ?? null;
            $mobileVerifiedAt = null;

            if (! empty($validated['idToken'] ?? null)) {
                $firebase = $this->verifyFirebasePhoneToken($validated['idToken']);
                if ($firebase instanceof JsonResponse) {
                    return $firebase;
                }
                $mobile = $firebase['mobile'];
                $countryCode = $firebase['country_code'];
                $mobileVerifiedAt = now();
            } elseif (empty($countryCode) && ! empty($validated['country'])) {
                $country = Country::where('name', $validated['country'])->first();
                if ($country && ! empty($country->phonecode)) {
                    $countryCode = $country->phonecode;
                }
            }

            if (User::where('mobile', $mobile)->exists()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.mobile_already_in_use_by_another_user',
                    data: ['field' => 'mobile']
                );
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'mobile' => $mobile,
                'country_code' => $countryCode,
                'mobile_verified_at' => $mobileVerifiedAt,
                'country' => $validated['country'] ?? null,
                'iso_2' => $validated['iso_2'] ?? null,
                'password' => Hash::make($validated['password']),
                'referral_code' => $referralCode,
                'friends_code' => $validated['friends_code'] ?? null,
                'logged_in_type' => UserLoginTypeEnum::PLATFORM(),
            ]);

            // Grant welcome wallet balance if enabled in system settings
            try {
                $settingService = app(SettingService::class);
                $systemSettingsResource = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
                $systemSettings = $systemSettingsResource?->toArray(request())['value'] ?? [];
                $welcomeAmount = (float) ($systemSettings['welcomeWalletBalanceAmount'] ?? 0);

                if ($welcomeAmount > 0) {
                    $walletService = app(WalletService::class);
                    $walletService->addBalance($user->id, [
                        'amount' => $welcomeAmount,
                        'payment_method' => 'system',
                        'description' => __('labels.welcome_wallet_bonus') ?? 'Welcome bonus added to wallet',
                    ]);
                }
            } catch (\Throwable $th) {
                Log::error('Welcome wallet credit failed for user ' . $user->id . ': ' . $th->getMessage());
            }

            // Create referral record if the user entered a valid friends_code
            if (! empty($validated['friends_code'])) {
                try {
                    $referralService->handleRegistration($user, $validated['friends_code']);
                } catch (\Throwable $th) {
                    Log::error('Referral link creation failed for user ' . $user->id . ': ' . $th->getMessage());
                }
            }

            $this->storeFcmToken($request, $user);
            event(new UserRegistered($user));

            // Dispatch Laravel's default verification mail (signed URL) so the
            // user can confirm ownership of the address right after sign-up.
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $th) {
                Log::error('Email verification dispatch failed for user ' . $user->id . ': ' . $th->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => __('labels.registration_successful_verification_sent'),
                'access_token' => $user->createToken($validated['email'])->plainTextToken,
                'token_type' => 'Bearer',
                'data' => [
                    'user' => new UserResource($user),
                    'is_register' => true,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('labels.validation_error') . ':- ' . $e->getMessage(),
                'data' => [],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('labels.registration_failed', ['error' => $e->getMessage()]),
                'data' => [],
            ], 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $request->validate(['email' => 'required|email']);

            $role = property_exists($this, 'role') ? $this->role : null;
            if ($response = $this->checkRoleAccess($role, 'email', $request->email)) {
                return $response;
            }

            $status = Password::sendResetLink($request->only('email'));
            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => __($status),
                    'data' => [],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => __($status),
                'data' => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('labels.password_reset_failed', ['error' => $e->getMessage()]),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->validate(['fcm_token' => 'nullable']);
            $fcmId = $request->input('fcm_token');
            if (! empty($fcmId)) {
                $request->user()->fcmTokens()->where('fcm_token', $fcmId)->delete();
            }
            $request->user()->currentAccessToken()->delete();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.logout_successful',
                data: []
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.logout_failed',
            );
        }
    }

    protected function getFirebaseAuth(): array
    {
        try {
            $settingService = app(SettingService::class);
            $authSetting = $settingService->getSettingByVariable(SettingTypeEnum::AUTHENTICATION());
            if (empty($authSetting)) {
                return [
                    'success' => false,
                    'message' => 'labels.setting_not_found',
                    'data' => [],
                ];
            }
            $serviceAccount = storage_path('app/private/settings/service-account-file.json');

            $factory = (new Factory)->withServiceAccount($serviceAccount);

            return [
                'success' => true,
                'message' => 'labels.token_generated',
                'data' => $factory->createAuth(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'labels.something_went_wrong',
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Verify a Firebase phone ID token and return the parsed country code +
     * national number. Returns a short-circuit JsonResponse on any failure
     * (bootstrap, invalid token, missing phone, unparseable number) so the
     * caller can `return` it directly.
     *
     * @return JsonResponse|array{country_code: string, mobile: string}
     */
    protected function verifyFirebasePhoneToken(string $idToken): JsonResponse|array
    {
        $auth = $this->getFirebaseAuth();
        if ($auth['success'] === false) {
            return ApiResponseType::sendJsonResponse(
                $auth['success'],
                $auth['message'],
                $auth['data']
            );
        }
        $auth = $auth['data'];

        try {
            $verifiedIdToken = $auth->verifyIdToken($idToken);
        } catch (FailedToVerifyToken $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.invalid_firebase_token',
                data: ['error' => $e->getMessage()],
            );
        }

        $uid = $verifiedIdToken->claims()->get('sub');
        $firebaseUser = $auth->getUser($uid);
        $phone = $firebaseUser->phoneNumber ?? null;

        if (empty($phone)) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.firebase_phone_not_found',
                data: []
            );
        }

        try {
            $parsed = PhoneNumberUtil::getInstance()->parse($phone);
        } catch (NumberParseException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.invalid_mobile_number',
                data: ['error' => $e->getMessage()],
            );
        }

        return [
            'country_code' => '+' . $parsed->getCountryCode(),
            'mobile' => (string) $parsed->getNationalNumber(),
        ];
    }

    /**
     * Handle Firebase Phone (Mobile OTP) authentication callback
     */
    public function phoneCallback(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'idToken' => 'required|string',
                'name' => 'nullable|string|max:255',
                'friends_code' => 'nullable|string|max:32|exists:users,referral_code',
            ]);

            $auth = $this->getFirebaseAuth();
            if ($auth['success'] === false) {
                return ApiResponseType::sendJsonResponse(
                    $auth['success'],
                    $auth['message'],
                    $auth['data']
                );
            }
            $auth = $auth['data'];

            // Verify the Firebase ID token issued after successful phone OTP verification
            $verifiedIdToken = $auth->verifyIdToken($request->idToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            // Get user info from Firebase (should include phoneNumber)
            $firebaseUser = $auth->getUser($uid);
            $phone = $firebaseUser->phoneNumber ?? null;

            if (empty($phone)) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.something_went_wrong',
                    data: ['error' => 'Phone number not found in Firebase user']
                );
            }

            // persisted separately (see app/Libs/libphonenumber).01
            try {
                $parsed = PhoneNumberUtil::getInstance()->parse($phone);
            } catch (NumberParseException $e) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.invalid_mobile_number',
                    data: ['error' => $e->getMessage()],
                );
            }

            $countryCode = '+' . $parsed->getCountryCode();
            $nationalNumber = $parsed->getNationalNumber();

            $authedUser = auth('sanctum')->user();

            if ($authedUser) {
                if ($this->isDemoModeEnabled()) {
                    return ApiResponseType::sendJsonResponse(
                        false,
                        __('labels.demo_mode_message_placeholder'),
                        [],
                        403
                    );
                }

                $result = app(ProfileService::class)->attachVerifiedMobile(
                    $authedUser,
                    $nationalNumber,
                    [
                        'country_code' => $countryCode,
                        'name' => $request->input('name'),
                        'friends_code' => $request->input('friends_code'),
                    ]
                );

                return response()->json([
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'token' => request()->bearerToken(),
                    'data' => $result['success'] ? new UserResource($result['data']) : $result['data'],
                ]);
            }

            $user = User::query()
                ->where(function ($q) use ($nationalNumber, $countryCode) {
                    $q->where('mobile', $nationalNumber)
                        ->where(function ($q2) use ($countryCode) {
                            $q2->where('country_code', $countryCode)
                                ->orWhereNull('country_code');
                        });
                })
                ->orWhere('mobile', $parsed->getCountryCode() . $nationalNumber)
                ->orWhere('mobile', $phone)
                ->first();

            if (! $user) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.user_not_found',
                    data: []
                );
            }

            // Optional role-based access check (admin/seller)
            $role = property_exists($this, 'role') ? $this->role : null;
            if ($response = $this->checkRoleAccess($role, 'mobile', $user->mobile)) {
                return $response;
            }

            // Successful login for existing user
            FacadesAuth::login($user);

            return $this->finalizeLogin($request, $user, false);
        } catch (AuthenticationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.authentication_error',
                data: ['error' => $e->getMessage()],
            );
        } catch (FailedToVerifyToken $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.invalid_firebase_token',
                data: ['error' => $e->getMessage()],
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.something_went_wrong',
                data: ['error' => $e->getMessage()],
            );
        }
    }

    /**
     * Send OTP via Custom SMS Gateway
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.validation_error'),
                ['errors' => $validator->errors()]
            );
        }

        $mobile = $request->input('mobile');
        $otpService = app(OtpService::class);

        $result = $otpService->sendOtp($mobile);

        if ($result['success']) {
            return ApiResponseType::sendJsonResponse(
                true,
                $result['message'],
                [
                    'mobile' => $mobile,
                    'expires_in' => $result['expires_in'],
                ]
            );
        }

        return ApiResponseType::sendJsonResponse(
            false,
            $result['message'],
            $result['debug'] ?? []
        );
    }

    /**
     * Verify OTP and authenticate Seller or Rider
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $otpService = app(OtpService::class);
            $sanitizedMobile = $otpService->sanitizeMobile($validated['mobile']);
            $otp = $validated['otp'];

            $authedUser = auth('sanctum')->user();

            if ($authedUser && $this->isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.demo_mode_message_placeholder'),
                    [],
                    403
                );
            }

            // Verify OTP code against stored record
            $verificationResult = $otpService->verifyOtp($sanitizedMobile, $otp);

            if (! $verificationResult['success']) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    $verificationResult['message'],
                    $verificationResult
                );
            }

            if ($authedUser) {
                // Attach verified mobile number to already authenticated user
                app(ProfileService::class)->attachVerifiedMobile(
                    $authedUser,
                    $sanitizedMobile,
                    [
                        'name' => $validated['name'] ?? null,
                        'friends_code' => $validated['friends_code'] ?? null,
                    ]
                );
                $user = $authedUser;
            } else {
                // Locate user by mobile number for OTP login or registration (matching with or without country code)
                $user = User::query()
                    ->where(function ($q) use ($sanitizedMobile, $validated) {
                        $q->where('mobile', $sanitizedMobile)
                            ->orWhere('mobile', $validated['mobile'])
                            ->orWhere('mobile', 'like', '%' . $sanitizedMobile);
                    })
                    ->first();
            }

            if (! $user) {
                $resolved = $otpService->resolveMobileUser($sanitizedMobile, $validated);
                if ($resolved['status'] === 'pending') {
                    return response()->json([
                        'success' => true,
                        'message' => __('labels.mobile_verified_successfully') ?? 'Your mobile number has been verified.',
                        'status' => 'pending',
                        'data' => [
                            'mobile' => $sanitizedMobile,
                        ],
                    ]);
                }

                $user = $resolved['user'];
            }

            // Role-based access check (seller approval or delivery boy verification/block check)
            $role = property_exists($this, 'role') ? $this->role : null;
            if ($response = $this->checkRoleAccess($role, 'mobile', $user->mobile)) {
                return $response;
            }

            // [CHANGE] Log in user and finalize response using role controller's finalizeLogin implementation
            FacadesAuth::login($user);

            return $this->finalizeLogin($request, $user, false);
        } catch (\Throwable $e) {
            Log::error('OTP verification failed in AuthTrait: ' . $e->getMessage(), [
                'mobile' => $request->input('mobile'),
                'error' => $e->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.something_went_wrong'),
                ['error' => $e->getMessage()]
            );
        }
    }
}
