<?php

namespace App\Observers;

use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Models\Seller;

class SellerObserver
{
    /**
     * Handle the Seller "saving" event.
     * Enforces that non-approved sellers are always kept in draft visibility status.
     */
    public function saving(Seller $seller): void
    {
        $verificationStatus = $seller->verification_status instanceof SellerVerificationStatusEnum
            ? $seller->verification_status->value
            : (string) $seller->verification_status;

        if ($verificationStatus !== SellerVerificationStatusEnum::Approved()) {
            $seller->visibility_status = SellerVisibilityStatusEnum::Draft();
        }
    }
}
