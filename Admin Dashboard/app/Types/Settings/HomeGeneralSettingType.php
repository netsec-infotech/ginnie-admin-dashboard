<?php

namespace App\Types\Settings;

use App\Enums\Category\CategoryBackgroundTypeEnum;
use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;
use Illuminate\Validation\Rules\Enum;
use phpDocumentor\Reflection\Types\Nullable;

class HomeGeneralSettingType implements SettingInterface
{
    use SettingTrait;

    public string $title = "";
    public array $searchLabels = [];
    public string $backgroundType = "";
    public string $backgroundColor = "#ffffff";
    public string $backgroundColorWeb = "#ffffff";
    public string $backgroundColorApp = "#ffffff";
    public string $backgroundImage = '';
    public string $backgroundImageWeb = '';
    public string $backgroundImageApp = '';
    public string $backgroundAnimation = '';
    public string $backgroundAnimationWeb = '';
    public string $backgroundAnimationApp = '';
    public string $fontColor = "#000000";
    public string $icon = '';
    public string $activeIcon = '';

    /**
     * Get Laravel validation rules for the properties
     *
     * @return array<string, array<string>>
     */
    protected static function getValidationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'searchLabels' => ['nullable', 'array'],
            'backgroundType' => ['nullable', 'string', 'in:color,image,animation'],
            'backgroundColor' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'backgroundColorWeb' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'backgroundColorApp' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'backgroundImage' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'backgroundImageWeb' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'backgroundImageApp' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'backgroundAnimation' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:8100',
            'backgroundAnimationWeb' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:8100',
            'backgroundAnimationApp' => 'nullable|file|mimetypes:application/json,text/plain,application/octet-stream|mimes:json,txt|max:8100',
            'icon'       => 'nullable|mimes:jpeg,png,jpg,webp,svg',
            'activeIcon' => 'nullable|mimes:jpeg,png,jpg,webp,svg',
            'fontColor' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ];
    }
}
