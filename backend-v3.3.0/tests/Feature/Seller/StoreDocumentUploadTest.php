<?php

use App\Enums\SpatieMediaCollectionName;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Services\SpatieMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(\App\Http\Middleware\VerifyLicense::class);
    Storage::fake('public');
});

it('registers address_proof and voided_check in store media collections', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['user_id' => $user->id]);
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    $addressProof = UploadedFile::fake()->image('address_proof.jpg');
    $voidedCheck = UploadedFile::fake()->image('voided_check.png');

    $store->addMedia($addressProof)->toMediaCollection(SpatieMediaCollectionName::ADDRESS_PROOF());
    $store->addMedia($voidedCheck)->toMediaCollection(SpatieMediaCollectionName::VOIDED_CHECK());

    expect($store->address_proof)->not->toBeEmpty();
    expect($store->voided_check)->not->toBeEmpty();
});

it('updates store address_proof and voided_check when new files are provided', function () {
    $user = User::factory()->create();
    $seller = Seller::factory()->create(['user_id' => $user->id]);
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    $initialAddressProof = UploadedFile::fake()->image('initial_proof.jpg');
    $store->addMedia($initialAddressProof)->toMediaCollection(SpatieMediaCollectionName::ADDRESS_PROOF());

    expect($store->address_proof)->not->toBeEmpty();

    $newAddressProof = UploadedFile::fake()->image('new_proof.png');
    $newVoidedCheck = UploadedFile::fake()->image('new_voided.png');

    $request = new \Illuminate\Http\Request();
    $request->files->set('address_proof', $newAddressProof);
    $request->files->set('voided_check', $newVoidedCheck);

    SpatieMediaService::update($request, $store, SpatieMediaCollectionName::ADDRESS_PROOF());
    SpatieMediaService::update($request, $store, SpatieMediaCollectionName::VOIDED_CHECK());

    $store->refresh();

    expect($store->getFirstMedia(SpatieMediaCollectionName::ADDRESS_PROOF())->file_name)->toBe('new-proof.png');
    expect($store->getFirstMedia(SpatieMediaCollectionName::VOIDED_CHECK())->file_name)->toBe('new-voided.png');
});
