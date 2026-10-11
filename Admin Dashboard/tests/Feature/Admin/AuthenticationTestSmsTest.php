<?php

use App\Enums\SmsMobileFormatEnum;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    // Bypass the SettingPolicy::updateSetting check so the suite doesn't need the
    // full role/permission seeders running for every assertion.
    Gate::before(fn () => true);

    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

it('rejects when test_mobile is missing', function () {
    $this->postJson(route('admin.settings.authentication.test-sms'), [
        'customSmsUrl'    => 'https://gateway.example.com/send',
        'customSmsMethod' => 'POST',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['test_mobile']);
});

it('rejects when the gateway URL is not a URL', function () {
    $this->postJson(route('admin.settings.authentication.test-sms'), [
        'test_mobile'     => '+919876543210',
        'customSmsUrl'    => 'not-a-url',
        'customSmsMethod' => 'POST',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['customSmsUrl']);
});

it('sends the request using the form-supplied config, not the saved setting', function () {
    Http::fake([
        'gateway.example.com/*' => Http::response('ok', 200),
    ]);

    $this->postJson(route('admin.settings.authentication.test-sms'), [
        'test_mobile'           => '+919876543210',
        'customSmsUrl'          => 'https://gateway.example.com/send',
        'customSmsMethod'       => 'POST',
        'customSmsMobileFormat' => SmsMobileFormatEnum::E164_WITHOUT_PLUS(),
        'customSmsHeaderKey'    => ['Authorization'],
        'customSmsHeaderValue'  => ['Bearer abc123'],
        'customSmsBodyKey'      => ['to', 'msg'],
        'customSmsBodyValue'    => ['{mobile}', '{message}'],
    ])->assertOk()
        ->assertJsonPath('success', true);

    Http::assertSent(function (ClientRequest $request) {
        return $request->url() === 'https://gateway.example.com/send'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer abc123')
            && $request['to'] === '919876543210'
            && is_string($request['msg'])
            && str_contains($request['msg'], '123456');
    });
});

it('returns the failure envelope with the raw gateway response when the gateway responds non-2xx', function () {
    $errorBody = '{"error":"unauthorized","code":"AUTH_002"}';
    Http::fake([
        'gateway.example.com/*' => Http::response($errorBody, 401),
    ]);

    $this->postJson(route('admin.settings.authentication.test-sms'), [
        'test_mobile'        => '+919876543210',
        'customSmsUrl'       => 'https://gateway.example.com/send',
        'customSmsMethod'    => 'POST',
        'customSmsBodyKey'   => ['to'],
        'customSmsBodyValue' => ['{mobile}'],
    ])->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.gateway_status', 401)
        ->assertJsonPath('data.gateway_body', $errorBody);
});

it('returns the invalid_mobile_number envelope for an unparseable number', function () {
    Http::fake();

    $this->postJson(route('admin.settings.authentication.test-sms'), [
        'test_mobile'        => 'not-a-number',
        'customSmsUrl'       => 'https://gateway.example.com/send',
        'customSmsMethod'    => 'POST',
        'customSmsBodyKey'   => ['to'],
        'customSmsBodyValue' => ['{mobile}'],
    ])->assertOk()
        ->assertJsonPath('success', false);

    Http::assertNothingSent();
});
