<?php

namespace App\Http\Requests\Advertisement;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopUpAdWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if (!$this->isAdsFeatureEnabled()) {
            return false;
        }

        if ($user->hasRole(DefaultSystemRolesEnum::SELLER())) {
            return true;
        }

        try {
            return $user->hasPermissionTo(SellerPermissionEnum::AD_WALLET_TOPUP());
        } catch (\Throwable) {
            return false;
        }
    }

    public function rules(): array
    {
        $minTopUp = $this->getMinTopUp();

        return [
            'amount' => ['required', 'numeric', 'min:' . ($minTopUp ?? '0.01')],
            'payment_method' => [
                'required',
                Rule::in(
                    Setting::getEnabledPaymentGateway(onlyGateway: true)
                ),
            ],
            'description' => ['nullable', 'string', 'max:255']
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => __('labels.ad_wallet_amount_required'),
            'amount.numeric' => __('labels.ad_wallet_amount_numeric'),
            'amount.min' => __('labels.ad_minimum_topup_required', ['min' => $this->getMinTopUp() ?? '0.01']),
            'payment_method.required' => __('labels.payment_method_required'),
            'payment_method.in' => __('labels.payment_method_invalid'),
        ];
    }

    protected function isAdsFeatureEnabled(): bool
    {
        $settings = $this->loadAdsSettings();
        return (bool) ($settings['featureEnabled'] ?? false);
    }

    protected function getMinTopUp(): ?float
    {
        $value = $this->loadAdsSettings()['walletMinTopup'] ?? null;
        return $value === null ? null : (float) $value;
    }

    protected function loadAdsSettings(): array
    {
        $resource = app(SettingService::class)
            ->getSettingByVariable(SettingTypeEnum::ADVERTISEMENT());

        return $resource ? ($resource->toArray($this) ['value'] ?? []) : [];
    }
}
