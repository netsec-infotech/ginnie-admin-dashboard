<?php

use App\Http\Middleware\VerifyLicense;
use App\Services\LicenseActivationStore;
use App\Services\LicenseHostPolicy;
use App\Services\LicenseValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

function licenseValidatorWithResponses(array $responses): LicenseValidator
{
    $handler = new MockHandler($responses);

    return new LicenseValidator(new Client(['handler' => HandlerStack::create($handler)]));
}

it('marks connection failures as unavailable instead of successful', function () {
    $validator = licenseValidatorWithResponses([
        new ConnectException('offline', new GuzzleRequest('GET', 'https://validator.invalid')),
    ]);

    $result = $validator->validate('sample-code', 'https://shop.example');

    expect($result['success'])->toBeFalse()
        ->and($result['unavailable'])->toBeTrue()
        ->and($result['data'])->toBe([]);
});

it('rejects unusable validator responses', function (Response $response) {
    $result = licenseValidatorWithResponses([$response])
        ->validate('sample-code', 'https://shop.example');

    expect($result['success'])->toBeFalse()
        ->and($result['unavailable'])->toBeTrue();
})->with([
    'server error' => new Response(500, [], 'server error'),
    'malformed json' => new Response(200, [], 'not json'),
    'missing schema' => new Response(200, [], '{}'),
    'missing token' => new Response(200, [], '{"error":false,"message":"ok"}'),
]);

it('accepts only a valid response carrying a token', function () {
    $result = licenseValidatorWithResponses([
        new Response(200, [], '{"error":false,"message":"ok","token":"remote-token"}'),
    ])->validate('sample-code', 'https://shop.example');

    expect($result['success'])->toBeTrue()
        ->and($result['unavailable'])->toBeFalse()
        ->and($result['data']['token'])->toBe('remote-token');
});

it('keeps Hyperlocal license deregistration compatible with the validator', function () {
    config(['license.item_id' => '61119699']);
    $result = licenseValidatorWithResponses([
        new Response(200, [], '{"error":false,"message":"deregistered"}'),
    ])->deregister('sample-code', 'https://shop.example');

    expect($result['success'])->toBeTrue()
        ->and($result['unavailable'])->toBeFalse()
        ->and($result['message'])->toBe('deregistered');
});

it('persists signed activation outside cached configuration and mirrors it safely', function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('e', 32))]);
    $suffix = bin2hex(random_bytes(6));
    $activationPath = storage_path('framework/testing/license-'.$suffix.'.json');
    $environmentPath = storage_path('framework/testing/license-'.$suffix.'.env');
    file_put_contents($environmentPath, "APP_NAME=Hyperlocal\n");
    $store = new LicenseActivationStore($activationPath, $environmentPath);

    $activation = $store->persist(
        'sample-code',
        'https://shop.example.com',
        'remote-token',
        'remote',
        now()
    );
    config(['app.license_purchase' => 'stale-config']);
    $reloaded = (new LicenseActivationStore($activationPath, $environmentPath))->current();

    expect($reloaded['purchase_code'])->toBe('sample-code')
        ->and($reloaded['signature'])->toBe($activation['signature'])
        ->and(file_get_contents($environmentPath))->toContain('LICENSE_PURCHASE_CODE="sample-code"')
        ->and(file_get_contents($environmentPath))->toContain('APP_NAME=Hyperlocal');

    $store->clear();

    expect(file_exists($activationPath))->toBeTrue()
        ->and($store->current()['purchase_code'])->toBe('')
        ->and(file_get_contents($environmentPath))->not->toContain('LICENSE_PURCHASE_CODE')
        ->and(file_get_contents($environmentPath))->toContain('APP_NAME=Hyperlocal')
        ->and(config('app.license_purchase'))->toBe('');

    @unlink($activationPath);
    @unlink($activationPath.'.backup');
    @unlink($environmentPath);
    @unlink($environmentPath.'.backup');
});

it('classifies only local and non-public addresses as local development', function (string $url, bool $local) {
    expect(app(LicenseHostPolicy::class)->isLocalDevelopment($url))->toBe($local);
})->with([
    ['http://localhost', true],
    ['http://store.localhost:8000', true],
    ['http://127.0.0.1', true],
    ['http://192.168.1.20', true],
    ['http://10.0.0.10', true],
    ['http://[::1]', true],
    ['https://8.8.8.8', false],
    ['https://shop.example.com', false],
]);

it('never calls the remote validator for a signed localhost activation', function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    $domain = 'http://localhost';
    $activation = [
        'purchase_code' => 'local-code',
        'token' => 'local-development',
        'signature' => LicenseValidator::signature('local-code', $domain, 'local-development'),
        'last_validated_at' => null,
    ];
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('current')->once()->andReturn($activation);
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldNotReceive('validate');
    $middleware = new VerifyLicense($validator, new LicenseHostPolicy, $store);

    $response = $middleware->handle(Request::create('/api/products', 'GET'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('accepts an entered code on localhost without trusting the submitted domain field', function () {
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('persist')
        ->once()
        ->withArgs(fn ($code, $url, $token, $mode) => $code === 'local-code'
            && app(LicenseHostPolicy::class)->isLocalDevelopment($url)
            && ! str_contains($url, 'attacker.example')
            && $token === 'local-development'
            && $mode === 'local')
        ->andReturn([]);
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldNotReceive('validate');
    app()->instance(LicenseActivationStore::class, $store);
    app()->instance(LicenseValidator::class, $validator);

    $response = $this->withoutMiddleware()->post('/install/license/verify', [
        'purchase_code' => 'local-code',
        'domain_url' => "https://attacker.example\nAPP_DEBUG=true",
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('action', 'redirect')
        ->assertJsonPath('url', route('LaravelInstaller::environment'));
});

it('continues public installation with a signed offline activation when the validator is unavailable', function () {
    config(['license.allow_offline_activation' => true]);
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('persistOffline')
        ->once()
        ->with('public-code', 'https://shop.example.com')
        ->andReturn([]);
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldReceive('validate')
        ->once()
        ->with('public-code', 'https://shop.example.com')
        ->andReturn([
            'success' => false,
            'message' => 'Unable to reach license server.',
            'data' => [],
            'unavailable' => true,
        ]);
    app()->instance(LicenseActivationStore::class, $store);
    app()->instance(LicenseValidator::class, $validator);

    $response = $this->withoutMiddleware()->post('https://shop.example.com/install/license/verify', [
        'purchase_code' => 'public-code',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('action', 'redirect')
        ->assertJsonPath('url', route('LaravelInstaller::environment'));
});

it('continues public revalidation with a signed offline activation when the validator is unavailable', function () {
    config(['license.allow_offline_activation' => true]);
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('persistOffline')
        ->once()
        ->with('public-code', 'https://shop.example.com')
        ->andReturn([]);
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldReceive('validate')
        ->once()
        ->with('public-code', 'https://shop.example.com')
        ->andReturn([
            'success' => false,
            'message' => 'Unable to reach license server.',
            'data' => [],
            'unavailable' => true,
        ]);
    app()->instance(LicenseActivationStore::class, $store);
    app()->instance(LicenseValidator::class, $validator);

    $response = $this->withoutMiddleware()->post('https://shop.example.com/license/revalidate/verify', [
        'purchase_code' => 'public-code',
        'intended' => 'https://shop.example.com/admin',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.redirect_url', 'https://shop.example.com/admin');
});

it('still rejects a public purchase code when the validator responds with an explicit failure', function () {
    config(['license.allow_offline_activation' => true]);
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldNotReceive('persistOffline');
    $store->shouldNotReceive('persist');
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldReceive('validate')->once()->andReturn([
        'success' => false,
        'message' => 'Invalid purchase code.',
        'data' => ['error' => true],
        'unavailable' => false,
    ]);
    app()->instance(LicenseActivationStore::class, $store);
    app()->instance(LicenseValidator::class, $validator);

    $response = $this->withoutMiddleware()->post('https://shop.example.com/license/revalidate/verify', [
        'purchase_code' => 'invalid-code',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid purchase code.');
});

it('keeps customer requests out of the periodic remote recheck path', function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
    $domain = 'https://shop.example.com';
    $activation = [
        'purchase_code' => 'remote-code',
        'token' => 'remote-token',
        'signature' => LicenseValidator::signature('remote-code', $domain, 'remote-token'),
        'last_validated_at' => now()->subDays(2)->toIso8601String(),
    ];
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('current')->once()->andReturn($activation);
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldNotReceive('validate');
    $middleware = new VerifyLicense($validator, new LicenseHostPolicy, $store);
    $request = Request::create($domain.'/api/products', 'GET');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('allows a signed public installation when the remote validator is unavailable', function () {
    Cache::flush();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('c', 32))]);
    $domain = 'https://shop.example.com';
    $activation = [
        'purchase_code' => 'remote-code',
        'token' => 'remote-token',
        'signature' => LicenseValidator::signature('remote-code', $domain, 'remote-token'),
        'last_validated_at' => now()->subDays(2)->toIso8601String(),
    ];
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('current')->once()->andReturn($activation);
    $store->shouldNotReceive('persist');
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldReceive('validate')->once()->andReturn([
        'success' => false,
        'message' => 'Unable to reach license server.',
        'data' => [],
        'unavailable' => true,
    ]);
    $middleware = new VerifyLicense($validator, new LicenseHostPolicy, $store);
    $request = Request::create($domain.'/admin', 'GET');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(Cache::has('license_recheck_retry_after'))->toBeTrue();
});

it('persists a rotated token and matching activation after a successful admin recheck', function () {
    Cache::flush();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('d', 32))]);
    $domain = 'https://shop.example.com';
    $activation = [
        'purchase_code' => 'remote-code',
        'token' => 'old-token',
        'signature' => LicenseValidator::signature('remote-code', $domain, 'old-token'),
        'last_validated_at' => now()->subDays(2)->toIso8601String(),
    ];
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('current')->once()->andReturn($activation);
    $store->shouldReceive('persist')
        ->once()
        ->withArgs(fn ($code, $url, $token, $mode, $date) => $code === 'remote-code'
            && $url === $domain
            && $token === 'new-token'
            && $mode === 'remote'
            && $date instanceof Carbon)
        ->andReturn([]);
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldReceive('validate')->once()->andReturn([
        'success' => true,
        'message' => 'ok',
        'data' => ['token' => 'new-token'],
        'unavailable' => false,
    ]);
    $middleware = new VerifyLicense($validator, new LicenseHostPolicy, $store);
    $request = Request::create($domain.'/admin', 'GET');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('blocks an admin only when the validator explicitly rejects a signed public activation', function () {
    Cache::flush();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('f', 32))]);
    $domain = 'https://shop.example.com';
    $activation = [
        'purchase_code' => 'remote-code',
        'token' => 'remote-token',
        'signature' => LicenseValidator::signature('remote-code', $domain, 'remote-token'),
        'last_validated_at' => now()->subDays(2)->toIso8601String(),
    ];
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('current')->once()->andReturn($activation);
    $validator = Mockery::mock(LicenseValidator::class);
    $validator->shouldReceive('validate')->once()->andReturn([
        'success' => false,
        'message' => 'License revoked',
        'data' => ['error' => true],
        'unavailable' => false,
    ]);
    $middleware = new VerifyLicense($validator, new LicenseHostPolicy, $store);
    $request = Request::create($domain.'/admin', 'GET');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->isRedirect())->toBeTrue()
        ->and($response->headers->get('Location'))->toContain('/license/revalidate');
});

it('returns JSON instead of a browser redirect for an unlicensed API', function () {
    $store = Mockery::mock(LicenseActivationStore::class);
    $store->shouldReceive('current')->once()->andReturn([
        'purchase_code' => '',
        'token' => '',
        'signature' => '',
    ]);
    $middleware = new VerifyLicense(
        Mockery::mock(LicenseValidator::class),
        new LicenseHostPolicy,
        $store
    );
    $request = Request::create('/api/products', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['success'])->toBeFalse();
});
