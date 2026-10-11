<?php

namespace App\Http\Resources\Setting;

use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSettingResource extends JsonResource
{
    use PanelAware;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'variable' => $this->variable,
            'value' => [
                'stripePayment' => $this->value['stripePayment'] ?? false,
                'stripePaymentMode' => $this->value['stripePaymentMode'] ?? '',
                'stripePublishableKey' => $this->value['stripePublishableKey'] ?? '',
                'stripeCurrencyCode' => $this->value['stripeCurrencyCode'] ?? '',
                'razorpayPayment' => $this->value['razorpayPayment'] ?? false,
                'razorpayPaymentMode' => $this->value['razorpayPaymentMode'] ?? '',
                'razorpayKeyId' => $this->value['razorpayKeyId'] ?? '',
                'paystackPayment' => $this->value['paystackPayment'] ?? false,
                'paystackPaymentMode' => $this->value['paystackPaymentMode'] ?? '',
                'paystackPublicKey' => $this->value['paystackPublicKey'] ?? '',
                'wallet' => $this->value['walletPayment'] ?? false,
                'walletOptionalNotes' => array_values(array_filter($this->value['walletOptionalNotes'] ?? (isset($this->value['optional_note']) && $this->value['optional_note'] !== '' ? [$this->value['optional_note']] : []), fn ($note) => ! is_null($note) && trim($note) !== '')),
                'optional_note' => $this->value['optional_note'] ?? (($this->value['walletOptionalNotes'][0] ?? '')),
                'directBankTransfer' => $this->value['directBankTransfer'] ?? false,
                'bankAccountName' => $this->value['bankAccountName'] ?? '',
                'bankAccountNumber' => $this->value['bankAccountNumber'] ?? '',
                'bankName' => $this->value['bankName'] ?? '',
                'bankCode' => $this->value['bankCode'] ?? '',
                'bankExtraNote' => $this->value['bankExtraNote'] ?? '',
                'flutterwavePayment' => $this->value['flutterwavePayment'] ?? false,
                'flutterwavePaymentMode' => $this->value['flutterwavePaymentMode'] ?? '',
                'flutterwavePublicKey' => $this->value['flutterwavePublicKey'] ?? '',
                'flutterwaveCurrencyCode' => $this->value['flutterwaveCurrencyCode'] ?? '',
                'xenditPayment' => $this->value['xenditPayment'] ?? false,
                'xenditPaymentMode' => $this->value['xenditPaymentMode'] ?? '',
                'xenditCurrencyCode' => $this->value['xenditCurrencyCode'] ?? '',
                'cod' => $this->value['cod'] ?? false,
                'codCharges' => $this->value['codCharges'] ?? '',
            ],
        ];

        // Only include critical/sensitive data for admin panel
        if ($this->getPanel() === 'admin') {
            $isDemoMode = \App\Models\Setting::isDemoModeEnabled();

            $stripeSecretKey = $this->value['stripeSecretKey'] ?? '';
            $stripeWebhookSecretKey = $this->value['stripeWebhookSecretKey'] ?? '';
            $razorpaySecretKey = $this->value['razorpaySecretKey'] ?? '';
            $razorpayWebhookSecret = $this->value['razorpayWebhookSecret'] ?? '';
            $paystackSecretKey = $this->value['paystackSecretKey'] ?? '';
            $paystackWebhookSecret = $this->value['paystackWebhookSecret'] ?? '';
            $flutterwaveSecretKey = $this->value['flutterwaveSecretKey'] ?? '';
            $flutterwaveEncryptionKey = $this->value['flutterwaveEncryptionKey'] ?? '';
            $flutterwaveWebhookSecret = $this->value['flutterwaveWebhookSecret'] ?? '';
            $xenditSecretKey = $this->value['xenditSecretKey'] ?? '';
            $xenditWebhookVerificationToken = $this->value['xenditWebhookVerificationToken'] ?? '';

            if ($isDemoMode) {
                $stripeSecretKey = ! empty($stripeSecretKey) ? '****************' : '';
                $stripeWebhookSecretKey = ! empty($stripeWebhookSecretKey) ? '****************' : '';
                $razorpaySecretKey = ! empty($razorpaySecretKey) ? '****************' : '';
                $razorpayWebhookSecret = ! empty($razorpayWebhookSecret) ? '****************' : '';
                $paystackSecretKey = ! empty($paystackSecretKey) ? '****************' : '';
                $paystackWebhookSecret = ! empty($paystackWebhookSecret) ? '****************' : '';
                $flutterwaveSecretKey = ! empty($flutterwaveSecretKey) ? '****************' : '';
                $flutterwaveEncryptionKey = ! empty($flutterwaveEncryptionKey) ? '****************' : '';
                $flutterwaveWebhookSecret = ! empty($flutterwaveWebhookSecret) ? '****************' : '';
                $xenditSecretKey = ! empty($xenditSecretKey) ? '****************' : '';
                $xenditWebhookVerificationToken = ! empty($xenditWebhookVerificationToken) ? '****************' : '';
            }

            $data['value'] = array_merge($data['value'], [
                'stripeSecretKey' => $stripeSecretKey,
                'stripePaymentEndpointUrl' => $this->value['stripePaymentEndpointUrl'] ?? '',
                'stripeWebhookSecretKey' => $stripeWebhookSecretKey,
                'razorpaySecretKey' => $razorpaySecretKey,
                'razorpayWebhookSecret' => $razorpayWebhookSecret,
                'paystackSecretKey' => $paystackSecretKey,
                'paystackCurrencyCode' => $this->value['paystackCurrencyCode'] ?? '',
                'paystackWebhookSecret' => $paystackWebhookSecret,
                'flutterwaveSecretKey' => $flutterwaveSecretKey,
                'flutterwaveEncryptionKey' => $flutterwaveEncryptionKey,
                'flutterwaveWebhookSecret' => $flutterwaveWebhookSecret,
                'xenditSecretKey' => $xenditSecretKey,
                'xenditWebhookVerificationToken' => $xenditWebhookVerificationToken,
            ]);
        }

        return $data;
    }
}
