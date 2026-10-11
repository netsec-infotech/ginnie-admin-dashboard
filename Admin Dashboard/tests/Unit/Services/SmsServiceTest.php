<?php

use App\Enums\SettingTypeEnum;
use App\Enums\SmsMobileFormatEnum;
use App\Libs\libphonenumber\PhoneNumberUtil;
use App\Services\SettingService;
use App\Services\SmsService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    $this->parsed = PhoneNumberUtil::getInstance()->parse('+919876543210');
});

/**
 * Wire a SmsService instance with a SettingService stub whose getRawSetting()
 * returns the supplied config under the AUTHENTICATION variable. Keeps the
 * tests free of DB hits while exercising the real placeholder/substitution
 * logic.
 */
function makeSmsService(array $authConfig): SmsService
{
    $settingService = Mockery::mock(SettingService::class);
    $rawSetting = new \stdClass();
    $rawSetting->value = $authConfig;
    $settingService->shouldReceive('getRawSetting')
        ->with(SettingTypeEnum::AUTHENTICATION())
        ->andReturn($rawSetting);

    return new SmsService($settingService);
}

it('refuses to send when custom SMS is disabled', function () {
    $service = makeSmsService(['customSms' => false]);

    $result = $service->sendSms($this->parsed, 'hello');

    expect($result['success'])->toBeFalse();
});

it('substitutes {mobile} using the E.164-with-plus format by default', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $service = makeSmsService([
        'customSms'             => true,
        'customSmsUrl'          => 'https://gateway.example.com/send',
        'customSmsMethod'       => 'POST',
        'customSmsBodyKey'      => ['to', 'body'],
        'customSmsBodyValue'    => ['{mobile}', '{message}'],
    ]);

    $result = $service->sendSms($this->parsed, 'Your OTP is 123456.');

    expect($result['success'])->toBeTrue();

    Http::assertSent(function (ClientRequest $request) {
        return $request->url() === 'https://gateway.example.com/send'
            && $request->method() === 'POST'
            && $request['to'] === '+919876543210'
            && $request['body'] === 'Your OTP is 123456.';
    });
});

it('substitutes {mobile} using the E.164-without-plus format when selected', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $service = makeSmsService([
        'customSms'             => true,
        'customSmsUrl'          => 'https://msg91.example.com/api',
        'customSmsMethod'       => 'POST',
        'customSmsMobileFormat' => SmsMobileFormatEnum::E164_WITHOUT_PLUS(),
        'customSmsBodyKey'      => ['mobiles', 'OTP'],
        'customSmsBodyValue'    => ['{mobile}', '{message}'],
    ]);

    $service->sendSms($this->parsed, '654321');

    Http::assertSent(function (ClientRequest $request) {
        return $request['mobiles'] === '919876543210'
            && $request['OTP'] === '654321';
    });
});

it('substitutes {mobile} using the national format when selected', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $service = makeSmsService([
        'customSms'             => true,
        'customSmsUrl'          => 'https://fast2sms.example.com/dev/bulkV2',
        'customSmsMethod'       => 'GET',
        'customSmsMobileFormat' => SmsMobileFormatEnum::NATIONAL(),
        'customSmsParamsKey'    => ['numbers', 'variables_values'],
        'customSmsParamsValue'  => ['{mobile}', '{message}'],
    ]);

    $service->sendSms($this->parsed, '999111');

    Http::assertSent(function (ClientRequest $request) {
        return str_contains($request->url(), 'numbers=9876543210')
            && str_contains($request->url(), 'variables_values=999111');
    });
});

it('builds headers and body together from the admin key/value arrays', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $service = makeSmsService([
        'customSms'           => true,
        'customSmsUrl'        => 'https://gateway.example.com/send',
        'customSmsMethod'     => 'POST',
        'customSmsHeaderKey'  => ['Authorization', 'X-API-Key'],
        'customSmsHeaderValue'=> ['Bearer abc123', 'key-xyz'],
        'customSmsBodyKey'    => ['to', 'msg'],
        'customSmsBodyValue'  => ['{mobile}', '{message}'],
    ]);

    $service->sendSms($this->parsed, 'hi');

    Http::assertSent(function (ClientRequest $request) {
        return $request->hasHeader('Authorization', 'Bearer abc123')
            && $request->hasHeader('X-API-Key', 'key-xyz')
            && $request['to'] === '+919876543210'
            && $request['msg'] === 'hi';
    });
});

it('reports failure with raw gateway response when the gateway returns a non-2xx', function () {
    $providerError = '{"error":"invalid api key","code":"AUTH_001"}';
    Http::fake(['*' => Http::response($providerError, 403)]);

    $service = makeSmsService([
        'customSms'        => true,
        'customSmsUrl'     => 'https://gateway.example.com/send',
        'customSmsMethod'  => 'POST',
        'customSmsBodyKey' => ['to'],
        'customSmsBodyValue' => ['{mobile}'],
    ]);

    $result = $service->sendSms($this->parsed, 'x');

    expect($result['success'])->toBeFalse()
        ->and($result['gateway_status'])->toBe(403)
        ->and($result['gateway_body'])->toBe($providerError);
});

it('returns the raw gateway response on success too', function () {
    $providerResponse = '{"id":"msg_42","status":"queued"}';
    Http::fake(['*' => Http::response($providerResponse, 201)]);

    $service = makeSmsService([
        'customSms'        => true,
        'customSmsUrl'     => 'https://gateway.example.com/send',
        'customSmsMethod'  => 'POST',
        'customSmsBodyKey' => ['to'],
        'customSmsBodyValue' => ['{mobile}'],
    ]);

    $result = $service->sendSms($this->parsed, 'x');

    expect($result['success'])->toBeTrue()
        ->and($result['gateway_status'])->toBe(201)
        ->and($result['gateway_body'])->toBe($providerResponse);
});

it('sends a JSON body when the admin sets Content-Type: application/json', function () {
    Http::fake(['*' => Http::response('{"status":"ok"}', 200)]);

    $service = makeSmsService([
        'customSms'           => true,
        'customSmsUrl'        => 'https://v3.api.termii.com/api/sms/send',
        'customSmsMethod'     => 'POST',
        'customSmsHeaderKey'  => ['Content-Type'],
        'customSmsHeaderValue'=> ['application/json'],
        'customSmsBodyKey'    => ['api_key', 'to', 'from', 'sms', 'type', 'channel'],
        'customSmsBodyValue'  => ['SECRET', '{mobile}', 'N-Alert', '{message}', 'plain', 'dnd'],
    ]);

    $service->sendSms($this->parsed, 'Your OTP is 123456.');

    Http::assertSent(function (ClientRequest $request) {
        // hasHeader confirms the encoding actually went out as JSON.
        $isJson = $request->hasHeader('Content-Type')
            && str_contains((string) $request->header('Content-Type')[0], 'application/json');

        // request->data() returns the parsed body — for JSON requests these are real properties.
        return $isJson
            && $request['api_key'] === 'SECRET'
            && $request['to'] === '+919876543210'
            && $request['sms'] === 'Your OTP is 123456.'
            && $request['channel'] === 'dnd';
    });
});

it('sendSmsWithConfig fires the request even when customSms is disabled in the saved setting', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    // The saved setting deliberately has customSms = false to prove the bypass.
    $service = makeSmsService(['customSms' => false]);

    $explicitConfig = [
        'customSms'         => true,
        'customSmsUrl'      => 'https://gateway.example.com/send',
        'customSmsMethod'   => 'POST',
        'customSmsBodyKey'  => ['to', 'body'],
        'customSmsBodyValue'=> ['{mobile}', '{message}'],
    ];

    $result = $service->sendSmsWithConfig($this->parsed, 'hello', $explicitConfig);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function (ClientRequest $request) {
        return $request->url() === 'https://gateway.example.com/send'
            && $request['to'] === '+919876543210'
            && $request['body'] === 'hello';
    });
});

it('skips empty header/body keys without breaking the request', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $service = makeSmsService([
        'customSms'           => true,
        'customSmsUrl'        => 'https://gateway.example.com/send',
        'customSmsMethod'     => 'POST',
        'customSmsHeaderKey'  => ['X-Real', ''],
        'customSmsHeaderValue'=> ['yes', 'should-not-be-sent'],
        'customSmsBodyKey'    => ['to', ''],
        'customSmsBodyValue'  => ['{mobile}', 'orphan'],
    ]);

    $service->sendSms($this->parsed, 'hi');

    Http::assertSent(function (ClientRequest $request) {
        // The empty-keyed pairs are dropped, the populated ones survive.
        return $request->hasHeader('X-Real', 'yes')
            && $request['to'] === '+919876543210'
            && ! array_key_exists('', $request->data());
    });
});
