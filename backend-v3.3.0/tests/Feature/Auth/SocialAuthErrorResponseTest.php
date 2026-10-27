<?php

use App\Http\Controllers\Api\User\AuthApiController;
use App\Http\Requests\Auth\AppleCallbackRequest;
use App\Http\Requests\Auth\GoogleCallbackRequest;
use App\Http\Resources\Setting\AuthenticationSettingResource;
use App\Models\Setting;
use App\Services\SettingService;
use App\Services\SocialAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

beforeEach(function () {
    $setting = new Setting;
    $setting->variable = 'authentication';
    $setting->setAttribute('value', json_encode(['googleLogin' => true]));

    $this->settingService = Mockery::mock(SettingService::class);
    $this->settingService
        ->shouldReceive('getSettingByVariable')
        ->andReturn(new AuthenticationSettingResource($setting));

    $this->socialAuthService = Mockery::mock(SocialAuthService::class);
    $this->firebaseUser = UserRecord::fromResponseData([
        'localId' => 'firebase-user-id',
        'createdAt' => (string) (time() * 1000),
        'email' => 'customer@example.test',
        'emailVerified' => true,
        'displayName' => 'Customer',
    ]);

    $this->makeController = function (array|Throwable $firebaseResult): AuthApiController {
        return new class($this->settingService, $this->socialAuthService, $firebaseResult) extends AuthApiController
        {
            public function __construct(
                SettingService $settingService,
                SocialAuthService $socialAuthService,
                private readonly array|Throwable $firebaseResult,
            ) {
                parent::__construct($settingService, $socialAuthService);
            }

            protected function resolveFirebaseUser(string $idToken): JsonResponse|array
            {
                if ($this->firebaseResult instanceof Throwable) {
                    throw $this->firebaseResult;
                }

                return $this->firebaseResult;
            }
        };
    };

    $this->firebaseResult = [
        'user' => $this->firebaseUser,
        'claims' => ['email' => 'customer@example.test'],
    ];

    $this->uniqueConstraintException = function (): UniqueConstraintViolationException {
        $previous = new PDOException(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'private@example.test' for key 'users_email_unique'",
            23000,
        );

        return new UniqueConstraintViolationException(
            'mysql',
            'insert into `users` (`email`) values (?)',
            ['private@example.test'],
            $previous,
        );
    };
});

it('sanitizes Google account conflicts while retaining structured server logs', function () {
    $exception = ($this->uniqueConstraintException)();
    $this->socialAuthService
        ->shouldReceive('loginOrRegisterFromGoogle')
        ->once()
        ->andThrow($exception);
    Log::spy();

    $controller = ($this->makeController)($this->firebaseResult);
    $request = GoogleCallbackRequest::create('/api/auth/google/callback', 'POST', [
        'idToken' => 'sensitive-google-token',
    ]);

    $response = $controller->googleCallback($request);
    $payload = $response->getData(true);
    $encodedPayload = json_encode($payload);

    expect($response->getStatusCode())->toBe(409)
        ->and($payload)->toBe([
            'success' => false,
            'message' => __('labels.social_auth_account_conflict'),
            'data' => [],
        ])
        ->and($encodedPayload)->not->toContain('SQLSTATE')
        ->and($encodedPayload)->not->toContain('users_email_unique')
        ->and($encodedPayload)->not->toContain('private@example.test')
        ->and($encodedPayload)->not->toContain('sensitive-google-token');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Social authentication account conflict.'
            && $context['provider'] === 'google'
            && $context['exception'] === $exception
            && ! in_array('sensitive-google-token', $context, true));
});

it('sanitizes Apple account conflicts', function () {
    $exception = ($this->uniqueConstraintException)();
    $this->socialAuthService
        ->shouldReceive('loginOrRegisterFromApple')
        ->once()
        ->andThrow($exception);
    Log::spy();

    $controller = ($this->makeController)($this->firebaseResult);
    $request = AppleCallbackRequest::create('/api/auth/apple/callback', 'POST', [
        'idToken' => 'sensitive-apple-token',
    ]);

    $response = $controller->appleCallback($request);
    $encodedPayload = json_encode($response->getData(true));

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true))->toBe([
            'success' => false,
            'message' => __('labels.social_auth_account_conflict'),
            'data' => [],
        ])
        ->and($encodedPayload)->not->toContain('SQLSTATE')
        ->and($encodedPayload)->not->toContain('users_email_unique')
        ->and($encodedPayload)->not->toContain('sensitive-apple-token');

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Social authentication account conflict.'
            && $context['provider'] === 'apple'
            && $context['exception'] === $exception
            && ! in_array('sensitive-apple-token', $context, true));
});

it('returns a safe server error for unexpected social authentication failures', function () {
    $exception = new RuntimeException('Internal connection details');
    $this->socialAuthService
        ->shouldReceive('loginOrRegisterFromGoogle')
        ->once()
        ->andThrow($exception);
    Log::spy();

    $controller = ($this->makeController)($this->firebaseResult);
    $request = GoogleCallbackRequest::create('/api/auth/google/callback', 'POST', [
        'idToken' => 'sensitive-google-token',
    ]);

    $response = $controller->googleCallback($request);
    $encodedPayload = json_encode($response->getData(true));

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toBe([
            'success' => false,
            'message' => __('labels.social_auth_failed'),
            'data' => [],
        ])
        ->and($encodedPayload)->not->toContain('Internal connection details')
        ->and($encodedPayload)->not->toContain('sensitive-google-token');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Social authentication failed.'
            && $context['provider'] === 'google'
            && $context['exception'] === $exception
            && ! in_array('sensitive-google-token', $context, true));
});

it('does not expose Firebase token verification details', function () {
    $controller = ($this->makeController)(new FailedToVerifyToken('Firebase project and credential details'));
    $request = GoogleCallbackRequest::create('/api/auth/google/callback', 'POST', [
        'idToken' => 'invalid-token',
    ]);

    $response = $controller->googleCallback($request);
    $encodedPayload = json_encode($response->getData(true));

    expect($response->getData(true))->toBe([
        'success' => false,
        'message' => __('labels.invalid_firebase_token'),
        'data' => [],
    ])->and($encodedPayload)->not->toContain('Firebase project and credential details');
});

it('does not expose authentication exception details', function () {
    $controller = ($this->makeController)(new AuthenticationException('Internal authentication details'));
    $request = GoogleCallbackRequest::create('/api/auth/google/callback', 'POST', [
        'idToken' => 'invalid-token',
    ]);

    $response = $controller->googleCallback($request);
    $encodedPayload = json_encode($response->getData(true));

    expect($response->getData(true))->toBe([
        'success' => false,
        'message' => __('labels.authentication_error'),
        'data' => [],
    ])->and($encodedPayload)->not->toContain('Internal authentication details');
});

it('does not expose Firebase bootstrap errors', function () {
    $controller = new class($this->settingService, $this->socialAuthService) extends AuthApiController
    {
        protected function getFirebaseAuth(): array
        {
            return [
                'success' => false,
                'message' => 'labels.something_went_wrong',
                'data' => ['error' => 'Service account path and credential details'],
            ];
        }
    };
    $request = GoogleCallbackRequest::create('/api/auth/google/callback', 'POST', [
        'idToken' => 'unverified-token',
    ]);

    $response = $controller->googleCallback($request);
    $encodedPayload = json_encode($response->getData(true));

    expect($response->getData(true)['data'])->toBe([])
        ->and($encodedPayload)->not->toContain('Service account path and credential details');
});
