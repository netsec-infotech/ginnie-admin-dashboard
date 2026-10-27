<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Http\Requests\Seller\StoreSellerRequest;
use App\Models\Setting;
use App\Services\AccountDeletionService;
use App\Services\SellerService;
use App\Traits\AuthTrait;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Seller Authentication')]
class SellerAuthApiController
{
    use AuthTrait;

    protected string $role = 'seller';

    protected $sellerService;

    public function __construct(SellerService $sellerService)
    {
        $this->sellerService = $sellerService;
    }

    /**
     * creating sellers API
     */
    public function createSeller(StoreSellerRequest $request): JsonResponse
    {
        // Restrict seller self-registration in Single Vendor mode
        if (Setting::isSystemVendorTypeSingle()) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.seller_registration_disabled'),
                status: 403
            );
        }

        try {
            $validated = $request->validated();
            $validated['verification_status'] = SellerVerificationStatusEnum::NotApproved();
            $validated['visibility_status'] = SellerVisibilityStatusEnum::Draft();
            $seller = $this->sellerService->createSeller(
                $validated,
                $request->allFiles()
            );

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.seller_created_successfully',
                $seller,
                201
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.validation_failed' . $e->getMessage(),
                data: $e->errors(),
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage() ?: 'labels.error_occurred',
                status: 500
            );
        }
    }

    /**
     * Delete Seller Account
     */
    public function deleteAccount(Request $request, AccountDeletionService $accountDeletionService): JsonResponse
    {
        try {
            if (Setting::isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.change_password_not_allowed_in_demo_mode', []);
            }
            $user = $request->user();
            if (! $user || ! $accountDeletionService->hasSellerAccount($user)) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            $accountDeletionService->delete($user);

            return ApiResponseType::sendJsonResponse(true, __('labels.account_deleted_successfully'), []);
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(false, $e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.account_deletion_failed', ['error' => $e->getMessage()]), []);
        }
    }
}
