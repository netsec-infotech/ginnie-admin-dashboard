<?php

namespace Database\Seeders;

use App\Models\ReturnReason;
use App\Models\ReturnRejectionReason;
use Illuminate\Database\Seeder;

class ReturnReasonSeeder extends Seeder
{
    /**
     * Seed default global return reasons and rejection reasons.
     */
    public function run(): void
    {
        // Seed default Global Admin Return Reasons if none exist
        if (ReturnReason::global()->count() === 0) {
            $defaultReasons = [
                [
                    'title' => 'Damaged / Defective Product Received',
                    'proof_required' => true,
                    'otp_required' => true,
                    'status' => 'active',
                ],
                [
                    'title' => 'Wrong Item / Size Delivered',
                    'proof_required' => true,
                    'otp_required' => false,
                    'status' => 'active',
                ],
                [
                    'title' => 'Quality Not as Expected',
                    'proof_required' => false,
                    'otp_required' => false,
                    'status' => 'active',
                ],
                [
                    'title' => 'Missing Items / Accessories',
                    'proof_required' => true,
                    'otp_required' => false,
                    'status' => 'active',
                ],
                [
                    'title' => 'Expired / Near Expiry Product',
                    'proof_required' => true,
                    'otp_required' => false,
                    'status' => 'active',
                ],
            ];

            foreach ($defaultReasons as $reason) {
                ReturnReason::create(array_merge($reason, ['seller_id' => null]));
            }
        }

        // Seed default Return Rejection Reasons if none exist
        if (ReturnRejectionReason::count() === 0) {
            $defaultRejectionReasons = [
                [
                    'title' => 'Item Damaged by Customer',
                    'comment_required' => true,
                    'status' => 'active',
                ],
                [
                    'title' => 'Item Damaged in Transit by Delivery Boy',
                    'comment_required' => true,
                    'status' => 'active',
                ],
                [
                    'title' => 'Missing Tags / Original Packaging',
                    'comment_required' => false,
                    'status' => 'active',
                ],
                [
                    'title' => 'Used or Altered Item',
                    'comment_required' => true,
                    'status' => 'active',
                ],
                [
                    'title' => 'Return Policy Window Expired',
                    'comment_required' => false,
                    'status' => 'active',
                ],
            ];

            foreach ($defaultRejectionReasons as $rejectionReason) {
                ReturnRejectionReason::create($rejectionReason);
            }
        }
    }
}
