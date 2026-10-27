<?php

namespace App\Http\Requests\OnboardingScreen;

use App\Enums\OnboardingAppTypeEnum;
use App\Models\OnboardingScreen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreUpdateOnboardingScreenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdating = $this->route('onboardingScreen') !== null;
        $hexColor = 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/';

        return [
            'app_type' => ['required', Rule::in(OnboardingAppTypeEnum::values())],
            'title' => ['nullable', 'string', 'max:30'],
            'subtitle' => ['nullable', 'string', 'max:70'],
            'background_type' => ['nullable', Rule::in(['solid', 'gradient', 'image'])],
            'background_color' => ['nullable', $hexColor],
            'gradient_start_color' => ['nullable', $hexColor],
            'gradient_end_color' => ['nullable', $hexColor],
            'gradient_angle' => ['nullable', 'integer', 'between:0,360'],
            'background_image_fit' => ['nullable', Rule::in(['cover', 'contain', 'fill'])],
            'background_image_position' => ['nullable', Rule::in(['center', 'top', 'bottom', 'left', 'right'])],
            'background_image' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml',
                'mimes:jpg,jpeg,png,webp,gif,svg',
                'max:10240',
            ],
            'overlay_enabled' => ['nullable', 'boolean'],
            'overlay_type' => ['nullable', Rule::in(['color', 'image'])],
            'overlay_color' => ['nullable', $hexColor],
            'overlay_opacity' => ['nullable', 'numeric', 'between:0,1'],
            'overlay_image_fit' => ['nullable', Rule::in(['cover', 'contain', 'fill'])],
            'overlay_image_position' => ['nullable', Rule::in(['center', 'top', 'bottom', 'left', 'right'])],
            'overlay_image_size' => ['nullable', 'integer', 'between:20,100'],
            'overlay_image' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml',
                'mimes:jpg,jpeg,png,webp,gif,svg',
                'max:10240',
            ],
            'title_color' => ['nullable', $hexColor],
            'subtitle_color' => ['nullable', $hexColor],
            'content_alignment' => ['nullable', Rule::in(['center', 'left', 'right'])],
            'media_fit' => ['nullable', Rule::in(['contain', 'cover'])],
            'button_background_color' => ['nullable', $hexColor],
            'button_foreground_color' => ['nullable', $hexColor],
            'indicator_active_color' => ['nullable', $hexColor],
            'indicator_inactive_color' => ['nullable', $hexColor],
            'image' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/svg+xml,application/json,text/plain,application/octet-stream,video/mp4,video/webm,video/quicktime',
                'mimes:jpg,jpeg,png,webp,gif,svg,json,txt,mp4,webm,mov',
                'max:10240',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('onboardingScreen') !== null || ! $this->filled('app_type')) {
                return;
            }

            $screensCount = OnboardingScreen::query()
                ->forApp((string) $this->input('app_type'))
                ->count();

            if ($screensCount >= OnboardingScreen::MAX_PER_APP) {
                $validator->errors()->add('app_type', __('labels.onboarding_screen_limit_reached', [
                    'count' => OnboardingScreen::MAX_PER_APP,
                ]));
            }
        });
    }
}
