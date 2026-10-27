<?php

use App\Http\Requests\SplashScreen\StoreSplashScreenRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

function splashValidator(array $data, array $files = []): \Illuminate\Validation\Validator
{
    $requestWithFiles = StoreSplashScreenRequest::create('/admin/splash-screens', 'POST', $data, [], $files);
    $requestWithFiles->setContainer(app());
    $formRequest = $requestWithFiles;
    $payload = array_merge($requestWithFiles->all(), $requestWithFiles->allFiles());
    $validator = Validator::make($payload, $formRequest->rules());
    $formRequest->withValidator($validator);

    return $validator;
}

it('rejects invalid background type', function (): void {
    $validator = splashValidator([
        'app_type' => 'customer',
        'slot' => 'current',
        'bg_type' => 'gradient',
        'overlay_enabled' => '0',
        'overlay_x_percent' => 50,
        'overlay_y_percent' => 45,
        'overlay_width_percent' => 60,
        'min_display_seconds' => 2,
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('bg_type'))->toBeTrue();
});

it('rejects out of range overlay positions', function (): void {
    $validator = splashValidator([
        'app_type' => 'customer',
        'slot' => 'current',
        'bg_type' => 'color',
        'bg_color' => '#1B5E20',
        'overlay_enabled' => '0',
        'overlay_x_percent' => 5,
        'overlay_y_percent' => 95,
        'overlay_width_percent' => 10,
        'min_display_seconds' => 2,
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('overlay_x_percent'))->toBeTrue()
        ->and($validator->errors()->has('overlay_y_percent'))->toBeTrue()
        ->and($validator->errors()->has('overlay_width_percent'))->toBeTrue();
});

it('rejects invalid lottie json', function (): void {
    $file = UploadedFile::fake()->createWithContent('bad.json', '{"hello":"world"}');

    $validator = splashValidator([
        'app_type' => 'customer',
        'slot' => 'current',
        'bg_type' => 'color',
        'bg_color' => '#1B5E20',
        'overlay_enabled' => '1',
        'overlay_type' => 'lottie',
        'overlay_x_percent' => 50,
        'overlay_y_percent' => 45,
        'overlay_width_percent' => 60,
        'min_display_seconds' => 2,
    ], [
        'overlay_lottie' => $file,
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('overlay_lottie'))->toBeTrue();
});
