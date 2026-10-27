<?php

namespace App\Http\Requests\WatchBuy;

use App\Models\WatchBuyProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WatchBuyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeProfile = $this->route('profile');
        $isOfficial = $this->routeIs('admin.watch-and-buy.official-profile.*');
        $sellerId = $this->user()?->seller()?->id;

        $profileId = null;
        if ($routeProfile instanceof WatchBuyProfile) {
            $profileId = $routeProfile->getKey();
        } elseif ($isOfficial) {
            $profileId = WatchBuyProfile::where('profile_type', 'platform')->value('id');
        } elseif ($sellerId) {
            $profileId = WatchBuyProfile::where('seller_id', $sellerId)->value('id');
        }

        return [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._]+$/', Rule::unique('watch_buy_profiles', 'username')->ignore($profileId)],
            'bio' => ['required', 'string', 'max:160'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['username' => strtolower(trim((string) $this->input('username')))]);
    }
}
