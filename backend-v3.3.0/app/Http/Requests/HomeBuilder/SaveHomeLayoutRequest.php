<?php

namespace App\Http\Requests\HomeBuilder;

use App\Enums\HomePage\HeroLayoutEnum;
use App\Enums\HomePage\HomePagePlatformEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\ItemPurposeEnum;
use App\Enums\HomePage\ProductSourceEnum;
use App\Enums\HomePage\SourceModeEnum;
use App\Enums\HomePage\StoryStyleEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Services\HomeBuilderOptions;
use App\Services\HomeBuilderService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SaveHomeLayoutRequest extends FormRequest
{
    private const HERO_MAX_ITEMS = 6;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sections = (array) $this->input('sections', []);

        foreach ($sections as &$section) {
            $type = $section['type'] ?? null;
            $config = (array) ($section['config'] ?? []);

            if ($type === HomeSectionTypeEnum::STORIES()) {
                $section['style'] = StoryStyleEnum::AVATAR_CIRCLE();
                if (array_key_exists('show_profile_name', $config)) {
                    $config['show_profile_name'] = filter_var($config['show_profile_name'], FILTER_VALIDATE_BOOLEAN);
                }
            }

            if ($type === HomeSectionTypeEnum::REELS()) {
                unset($config['show_views']);
                if (array_key_exists('show_title', $config)) {
                    $config['show_title'] = filter_var($config['show_title'], FILTER_VALIDATE_BOOLEAN);
                }
                if (array_key_exists('show_likes', $config)) {
                    $config['show_likes'] = filter_var($config['show_likes'], FILTER_VALIDATE_BOOLEAN);
                }
            }

            if ($type === HomeSectionTypeEnum::PLAY_IMAGE()) {
                if (isset($config['rows']) && is_array($config['rows'])) {
                    foreach ($config['rows'] as &$row) {
                        if (is_array($row)) {
                            if (array_key_exists('show_scroll_buttons', $row)) {
                                $row['show_scroll_buttons'] = filter_var($row['show_scroll_buttons'], FILTER_VALIDATE_BOOLEAN);
                            }
                            if (array_key_exists('show_bullets', $row)) {
                                $row['show_bullets'] = filter_var($row['show_bullets'], FILTER_VALIDATE_BOOLEAN);
                            }
                            if (array_key_exists('auto_scroll', $row)) {
                                $row['auto_scroll'] = filter_var($row['auto_scroll'], FILTER_VALIDATE_BOOLEAN);
                            }
                        }
                    }
                    unset($row);
                }
            }

            $section['config'] = $config;
        }
        unset($section);

        $this->merge(['sections' => $sections]);
    }

    /** The platform this save targets (app | web); drives which section types/styles are allowed. */
    public function platform(): string
    {
        return (string) $this->input('platform', HomePagePlatformEnum::APP());
    }

    public function rules(): array
    {
        return [
            'platform' => ['nullable', new Enum(HomePagePlatformEnum::class)],
            'scope_type' => ['required', new Enum(HomePageScopeEnum::class)],
            'scope_id' => [
                'nullable',
                'required_if:scope_type,' . HomePageScopeEnum::CATEGORY(),
                Rule::exists('categories', 'id'),
            ],
            'zone_id' => [
                'nullable',
                'required_if:scope_type,' . HomePageScopeEnum::ZONE(),
                Rule::exists('delivery_zones', 'id'),
            ],
            'is_active' => ['boolean'],
            'excluded_global_section_ids' => ['nullable', 'array'],
            'excluded_global_section_ids.*' => ['integer', Rule::exists('home_sections', 'id')],

            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.type' => ['required', Rule::in(app(HomeBuilderOptions::class)->sectionTypes($this->platform()))],
            'sections.*.title' => ['nullable', 'string', 'max:191'],
            'sections.*.style' => ['nullable', 'string', 'max:64'],
            'sections.*.config' => ['nullable', 'array'],
            'sections.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'sections.*.is_active' => ['boolean'],
            'sections.*.start_at' => ['nullable', 'date'],
            'sections.*.end_at' => ['nullable', 'date', 'after_or_equal:sections.*.start_at'],
            'sections.*.zone_ids' => ['nullable', 'array'],
            'sections.*.zone_ids.*' => ['integer', Rule::exists('delivery_zones', 'id')],

            'sections.*.items' => ['nullable', 'array'],
            'sections.*.items.*.id' => ['nullable', 'integer'],
            'sections.*.items.*.purpose' => ['nullable', new Enum(ItemPurposeEnum::class)],
            'sections.*.items.*.itemable_type' => ['nullable', Rule::in(array_keys(HomeBuilderService::ITEMABLE_MAP))],
            'sections.*.items.*.itemable_id' => ['nullable', 'integer'],
            'sections.*.items.*.title' => ['nullable', 'string', 'max:191'],
            'sections.*.items.*.link_url' => ['nullable', 'url', 'max:2048'],
            'sections.*.items.*.config' => ['nullable', 'array'],
            'sections.*.items.*.image' => ['nullable', 'string', 'max:2048'],
            'sections.*.items.*.image_web_mobile' => ['nullable', 'string', 'max:2048'],
            'sections.*.items.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'media' => ['nullable', 'array'],
            'media.*' => ['array'],
            'media.*.background' => ['nullable', 'file', 'image', 'max:10240'],
            'media.*.card_background' => ['nullable', 'file', 'image', 'max:10240'],
            'media.*.hero_media' => ['nullable', 'file', 'max:10240', 'extensions:jpg,jpeg,png,webp,gif,json'],
            'media.*.hero_media_web_mobile' => ['nullable', 'file', 'max:10240', 'extensions:jpg,jpeg,png,webp,gif,json'],
            'item_media' => ['nullable', 'array'],
            'item_media.*' => ['array'],
            'item_media.*.*' => ['nullable', 'file', 'image', 'max:10240'],
            'item_media_mobile' => ['nullable', 'array'],
            'item_media_mobile.*' => ['array'],
            'item_media_mobile.*.*' => ['nullable', 'file', 'image', 'max:10240'],
            'row_media' => ['nullable', 'array'],
            'row_media.*' => ['array'],
            'row_media.*.*' => ['nullable', 'file', 'image', 'max:10240'],
            'remove_item_media' => ['nullable', 'array'],
            'remove_item_media.*' => ['array'],
            'remove_item_media.*.*' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('sections', []) as $i => $section) {
                $type = $section['type'] ?? null;
                $config = (array) ($section['config'] ?? []);
                $items = (array) ($section['items'] ?? []);

                $this->validateConfig($validator, "sections.$i", $type, $config, $section);
                $this->validateItems($validator, "sections.$i", (int) $i, $type, $config, $items);

                if ($type === HomeSectionTypeEnum::HERO() && ! $this->heroBackgroundProvided($i, $section['id'] ?? null)) {
                    $validator->errors()->add("media.$i.hero_media", __('validation.home_builder_hero_background_required'));
                }
            }
        });
    }

    /**
     * Validate the section's style + config values against the platform's allowed
     * options (sourced from HomeBuilderOptions, the same provider that feeds the
     * builder UI — so the two never drift apart).
     */
    private function validateConfig(Validator $v, string $path, ?string $type, array $config, array $section): void
    {
        if (! $type) {
            return;
        }

        $options = app(HomeBuilderOptions::class);
        $platform = $this->platform();

        $allowedStyles = $options->stylesFor($platform, $type);
        $style = $section['style'] ?? null;
        if ($allowedStyles !== null && $style !== null && ! in_array($style, $allowedStyles, true)) {
            $v->errors()->add("$path.style", __('validation.home_builder_invalid_option'));
        }

        foreach ($options->specsFor($platform)[$type]['config'] ?? [] as $field => $allowed) {
            $value = $config[$field] ?? null;
            if ($value !== null && ! in_array($value, $allowed, true)) {
                $v->errors()->add("$path.config.$field", __('validation.home_builder_invalid_option'));
            }
        }

        if ($type === HomeSectionTypeEnum::STORIES()) {
            $this->validateLimit($v, $path, $config);
            $this->validateBooleanConfig($v, $path, $config, 'show_profile_name');

            if (
                array_key_exists('ring_color', $config)
                && (! is_string($config['ring_color']) || preg_match('/^#[0-9A-Fa-f]{6}$/', $config['ring_color']) !== 1)
            ) {
                $v->errors()->add("$path.config.ring_color", __('validation.home_builder_invalid_option'));
            }
        }

        if ($type === HomeSectionTypeEnum::REELS()) {
            $this->validateLimit($v, $path, $config);
            $this->validateBooleanConfig($v, $path, $config, 'show_likes');
            $this->validateBooleanConfig($v, $path, $config, 'show_title');
        }
    }

    private function validateLimit(Validator $validator, string $path, array $config): void
    {
        if (! array_key_exists('limit', $config)) {
            return;
        }

        $limit = filter_var($config['limit'], FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 50) {
            $validator->errors()->add("$path.config.limit", __('validation.home_builder_invalid_option'));
        }
    }

    private function validateBooleanConfig(Validator $validator, string $path, array $config, string $field): void
    {
        if (
            ! array_key_exists($field, $config)
            || in_array($config[$field], [true, false, 0, 1, '0', '1'], true)
        ) {
            return;
        }

        $validator->errors()->add("$path.config.$field", __('validation.home_builder_invalid_option'));
    }

    private function validateItems(Validator $v, string $path, int $sectionIndex, ?string $type, array $config, array $items): void
    {
        $withTarget = array_filter($items, static fn($it) => ! empty($it['itemable_id'] ?? null));

        if ($type === HomeSectionTypeEnum::HERO() && count($items) > self::HERO_MAX_ITEMS) {
            $v->errors()->add("$path.items", __('validation.home_builder_hero_max_items'));
        }

        if (
            $type === HomeSectionTypeEnum::HERO()
            && ($config['layout'] ?? null) === HeroLayoutEnum::WITH_ITEMS()
            && count($withTarget) < 1
        ) {
            $v->errors()->add("$path.items", __('validation.home_builder_hero_items_required'));
        }

        if (
            $type === HomeSectionTypeEnum::PRODUCTS()
            && ($config['source'] ?? null) === ProductSourceEnum::CUSTOM()
            && count($withTarget) < 1
        ) {
            $v->errors()->add("$path.items", __('validation.home_builder_products_target_required'));
        }

        if (
            in_array($type, [HomeSectionTypeEnum::CATEGORIES(), HomeSectionTypeEnum::BRANDS()], true)
            && ($config['source'] ?? null) === SourceModeEnum::CUSTOM()
            && count($withTarget) < 1
        ) {
            $v->errors()->add("$path.items", __('validation.home_builder_custom_items_required'));
        }

        if ($type === HomeSectionTypeEnum::BANNERS()) {
            if (count($items) < 1) {
                $v->errors()->add("$path.items", __('validation.home_builder_banner_items_required'));
            }

            foreach ($items as $j => $item) {
                $hasItemable = ! empty($item['itemable_type']) && ! empty($item['itemable_id']);
                $hasLink = ! empty($item['link_url']);

                if ($hasItemable === $hasLink) {
                    $v->errors()->add("$path.items.$j", __('validation.home_builder_banner_link_target'));
                }

                if ($hasLink && ! $this->bannerItemImageProvided($sectionIndex, $j, $item['id'] ?? null, $item)) {
                    $v->errors()->add("$path.items.$j.image", __('validation.home_builder_banner_image_required'));
                }
            }
        } elseif ($type === HomeSectionTypeEnum::PLAY_IMAGE()) {
            foreach ($items as $j => $item) {
                if (! $this->playImageItemImageProvided($sectionIndex, $j, $item['id'] ?? null, $item)) {
                    $v->errors()->add("$path.items.$j.image", __('validation.home_builder_banner_image_required'));
                }
            }
        } else {
            foreach ($items as $j => $item) {
                if (! empty($item['itemable_type']) && empty($item['itemable_id'])) {
                    $v->errors()->add("$path.items.$j.itemable_id", __('validation.home_builder_itemable_id_required'));
                }
            }
        }
    }

    /** A hero section needs a background — a new upload, or one already attached to an existing section. */
    private function heroBackgroundProvided($index, $sectionId): bool
    {
        if ($this->hasFile("media.$index.hero_media")) {
            return true;
        }

        return $sectionId && (bool) HomeSection::find($sectionId)?->hero_media;
    }

    private function bannerItemImageProvided(int $sectionIndex, int $itemIndex, ?int $itemId, array $item): bool
    {
        if ($this->hasFile("item_media.$sectionIndex.$itemIndex")) {
            return true;
        }

        if (! empty($item['image'])) {
            return true;
        }

        if ($itemId) {
            $existingItem = HomeSectionItem::find($itemId);
            if ($existingItem && $existingItem->image) {
                return true;
            }
        }

        return false;
    }

    private function playImageItemImageProvided(int $sectionIndex, int $itemIndex, ?int $itemId, array $item): bool
    {
        if ($this->hasFile("item_media.$sectionIndex.$itemIndex") || $this->hasFile("item_media_mobile.$sectionIndex.$itemIndex")) {
            return true;
        }

        if (! empty($item['image']) || ! empty($item['image_web_mobile'])) {
            return true;
        }

        if ($itemId) {
            $existingItem = HomeSectionItem::find($itemId);

            return (bool) ($existingItem?->image || $existingItem?->image_web_mobile);
        }

        return false;
    }
}
