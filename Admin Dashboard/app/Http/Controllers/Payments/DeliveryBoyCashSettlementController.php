<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\DeliveryBoyOnlineSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliveryBoyCashSettlementController extends Controller
{
    /**
     * Show the online payment page for a cash settlement.
     * 
     * [CHANGE] Added controller method to render the payment checkout screen
     */
    public function showPaymentPage(string $uuid)
    {
        $settlement = DeliveryBoyOnlineSettlement::with(['deliveryBoy.user', 'assignments'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($settlement->status !== 'pending') {
            return redirect()->route('cash-collection-payment.status', ['uuid' => $uuid]);
        }

        $settingService = app(\App\Services\SettingService::class);
        $paymentSetting = $settingService->getSettingByVariable(\App\Enums\SettingTypeEnum::PAYMENT());
        
        $razorpayKeyId = $paymentSetting->value['razorpayKeyId'] ?? "";
        $amountPaise = (int) round(((float) $settlement->amount) * 100);
        
        // Generate Razorpay order ID if not already generated
        $orderId = $settlement->transaction_reference;
        if (empty($orderId) && !empty($razorpayKeyId)) {
            try {
                $razorpayController = app(\App\Http\Controllers\Payments\RazorpayController::class);
                
                // [CHANGE] Use new createOnlineSettlementOrder to pass type notes as delivery_boy_settlement
                $orderRes = $razorpayController->createOnlineSettlementOrder([
                    'amount' => (float) $settlement->amount,
                    'currency' => 'INR',
                    'description' => 'Rider COD Cash Settlement',
                    'settlement_id' => (string) $settlement->id,
                ]);
                
                if ($orderRes['success'] ?? false) {
                    $orderId = $orderRes['data']['id'] ?? null;
                    if ($orderId) {
                        $settlement->transaction_reference = $orderId;
                        $settlement->payment_gateway = 'razorpay';
                        $settlement->save();
                    }
                }
            } catch (\Exception $e) {
                Log::error('Razorpay order creation for cash settlement failed: ' . $e->getMessage());
            }
        }

        return view('payments.delivery_boy_settlement', [
            'settlement' => $settlement,
            'gateway' => 'razorpay',
            'keyId' => $razorpayKeyId,
            'orderId' => $orderId,
            'amount' => (float) $settlement->amount,
            'amountPaise' => $amountPaise,
            'currency' => 'INR',
            'transactionId' => $settlement->id,
            'transactionUuid' => $settlement->uuid,
            'statusRoute' => route('cash-collection-payment.status', ['uuid' => $settlement->uuid]),
            'payButtonLabel' => 'Pay Settle Balance',
            'paymentContext' => [
                'title' => 'Rider Cash Settle Summary',
                'lineItems' => [
                    ['label' => 'Rider Name', 'value' => $settlement->deliveryBoy->full_name ?? 'Rider', 'bold' => true],
                    ['label' => 'Total Orders Included', 'value' => $settlement->assignments->count(), 'bold' => false],
                ],
            ],
        ]);
    }

    /**
     * Show the cash settlement payment status.
     * 
     * [CHANGE] Added controller method to render the payment status success/fail screen
     */
    public function status(string $uuid)
    {
        $settlement = DeliveryBoyOnlineSettlement::where('uuid', $uuid)->firstOrFail();
        
        return view('payments.delivery_boy_settlement_status', [
            'settlement' => $settlement,
            'completedStatus' => 'completed',
            'failedStatus' => 'failed',
            'paymentContext' => [
                'title' => 'Settlement Payment Summary',
                'lineItems' => [
                    ['label' => 'Settlement ID', 'value' => '#' . $settlement->id, 'bold' => false],
                    ['label' => 'Status', 'value' => strtoupper($settlement->status), 'bold' => true],
                    ['label' => 'Amount', 'value' => number_format((float) $settlement->amount, 2), 'bold' => true],
                ],
                'backUrl' => '#',
                'backLabel' => 'Back to App',
            ],
        ]);
    }
}
