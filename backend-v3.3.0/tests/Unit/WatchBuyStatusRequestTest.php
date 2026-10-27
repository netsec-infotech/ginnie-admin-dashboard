<?php

use App\Http\Requests\WatchBuy\WatchBuyStatusRequest;
use App\Services\WatchBuyMediaInspector;
use App\Services\WatchBuyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

it('accepts iphone quicktime videos for status media validation', function () {
    $this->mock(WatchBuyService::class)
        ->shouldReceive('config')
        ->once()
        ->andReturn([
            'effectiveMaxUploadMb' => 50,
            'maxProductsPerStatus' => 5,
        ]);

    $file = UploadedFile::fake()->create('iphone-status.mov', 1024, 'video/quicktime');

    expect(Validator::make([
        'text' => '',
        'media' => $file,
    ], (new WatchBuyStatusRequest)->rules())->passes())->toBeTrue();
});

it('accepts iphone hevc codec for watch buy videos', function () {
    $method = new ReflectionMethod(WatchBuyMediaInspector::class, 'isAllowedVideoCodec');
    $inspector = app(WatchBuyMediaInspector::class);

    expect($method->invoke($inspector, 'hevc hvc1', 'status'))->toBeTrue()
        ->and($method->invoke($inspector, 'hevc hvc1', 'reel'))->toBeTrue()
        ->and($method->invoke($inspector, 'h.264 avc1', 'reel'))->toBeTrue()
        ->and($method->invoke($inspector, 'h264 avc1', 'reel'))->toBeTrue();
});
