<?php

namespace App\Http\Requests\SplashScreen;

use App\Enums\SplashAppTypeEnum;
use App\Enums\SplashSlotEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Models\SplashScreen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSplashScreenRequest extends FormRequest
{
    private ?SplashScreen $existingScreen = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_type' => ['required', Rule::in(SplashAppTypeEnum::values())],
            'slot' => ['required', Rule::in([SplashSlotEnum::CURRENT(), SplashSlotEnum::STAGED_NEXT()])],
            'bg_type' => ['required', Rule::in(['color', 'image', 'video'])],
            'bg_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', 'required_if:bg_type,color'],
            'bg_image' => ['nullable', Rule::requiredIf(fn(): bool => $this->requiresBackgroundImage()), 'file', 'mimes:jpg,jpeg,png,webp', 'max:1536', 'dimensions:min_width=1080,min_height=1920'],
            'bg_video' => ['nullable', Rule::requiredIf(fn(): bool => $this->requiresBackgroundVideo()), 'file', 'mimes:mp4', 'max:6144'],
            'overlay_enabled' => ['required', 'boolean'],
            'overlay_type' => ['nullable', 'required_if:overlay_enabled,1,true,on', Rule::in(['image', 'lottie'])],
            'overlay_image' => ['nullable', Rule::requiredIf(fn(): bool => $this->requiresOverlayImage()), 'exclude_unless:overlay_type,image', 'file', 'mimes:png,webp', 'max:512'],
            'overlay_lottie' => ['nullable', Rule::requiredIf(fn(): bool => $this->requiresOverlayLottie()), 'exclude_unless:overlay_type,lottie', 'file', 'mimes:json,txt', 'max:512'],
            'overlay_x_percent' => ['required', 'numeric', 'between:10,90'],
            'overlay_y_percent' => ['required', 'numeric', 'between:10,90'],
            'overlay_width_percent' => ['required', 'numeric', 'between:20,85'],
            'min_display_seconds' => ['required', 'integer', 'between:1,6'],
            'activate_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->hasFile('bg_image')) {
                [$width, $height] = getimagesize($this->file('bg_image')->getRealPath()) ?: [0, 0];
                if ($height <= $width) {
                    $validator->errors()->add('bg_image', __('labels.splash_background_image_portrait_invalid'));
                }
            }

            if ($this->hasFile('overlay_lottie')) {
                $payload = json_decode((string) file_get_contents($this->file('overlay_lottie')->getRealPath()), true);
                if (! is_array($payload) || empty($payload['v']) || empty($payload['layers']) || ! is_array($payload['layers'])) {
                    $validator->errors()->add('overlay_lottie', __('labels.splash_lottie_invalid'));
                }
            }
        });
    }

    private function requiresBackgroundImage(): bool
    {
        return $this->input('bg_type') === 'image'
            && ! $this->hasFile('bg_image')
            && ! $this->existingScreen()?->hasMedia(SpatieMediaCollectionName::SPLASH_BACKGROUND_IMAGE());
    }

    private function requiresBackgroundVideo(): bool
    {
        return $this->input('bg_type') === 'video'
            && ! $this->hasFile('bg_video')
            && ! $this->existingScreen()?->hasMedia(SpatieMediaCollectionName::SPLASH_BACKGROUND_VIDEO());
    }

    private function requiresOverlayImage(): bool
    {
        return $this->boolean('overlay_enabled')
            && $this->input('overlay_type') === 'image'
            && ! $this->hasFile('overlay_image')
            && ! $this->existingScreen()?->hasMedia(SpatieMediaCollectionName::SPLASH_OVERLAY_IMAGE());
    }

    private function requiresOverlayLottie(): bool
    {
        return $this->boolean('overlay_enabled')
            && $this->input('overlay_type') === 'lottie'
            && ! $this->hasFile('overlay_lottie')
            && ! $this->existingScreen()?->hasMedia(SpatieMediaCollectionName::SPLASH_OVERLAY_LOTTIE());
    }

    private function existingScreen(): ?SplashScreen
    {
        if ($this->existingScreen !== null) {
            return $this->existingScreen;
        }

        if (
            ! in_array($this->input('app_type'), SplashAppTypeEnum::values(), true)
            || ! in_array($this->input('slot'), [SplashSlotEnum::CURRENT(), SplashSlotEnum::STAGED_NEXT()], true)
        ) {
            return null;
        }

        return $this->existingScreen = SplashScreen::query()
            ->where('app_type', $this->input('app_type'))
            ->where('slot', $this->input('slot'))
            ->with('media')
            ->first();
    }
}
