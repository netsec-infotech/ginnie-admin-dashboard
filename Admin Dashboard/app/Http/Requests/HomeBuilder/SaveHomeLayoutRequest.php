<?php

namespace App\Http\Requests\HomeBuilder;

use App\Enums\HomePage\HeroLayoutEnum;
use App\Enums\HomePage\HomePagePlatformEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePage\ItemPurposeEnum;
use App\Enums\HomePage\SourceModeEnum;
use App\Enums\HomePageScopeEnum;
use App\Models\HomeSection;
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
                'required_if:scope_type,'.HomePageScopeEnum::CATEGORY(),
                Rule::exists('categories', 'id'),
            ],
            'zone_id' => [
                'nullable',
                'required_if:scope_type,'.HomePageScopeEnum::ZONE(),
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
            'sections.*.items.*.sort_order' => ['nullable', 'integer', 'min:0'],

            'media' => ['nullable', 'array'],
            'media.*' => ['array'],
            'media.*.background' => ['nullable', 'file', 'image', 'max:10240'],
            'media.*.card_background' => ['nullable', 'file', 'image', 'max:10240'],
            'media.*.hero_media' => ['nullable', 'file', 'max:10240', 'extensions:jpg,jpeg,png,webp,gif,json'],
            'item_media' => ['nullable', 'array'],
            'item_media.*' => ['array'],
            'item_media.*.*' => ['nullable', 'file', 'image', 'max:10240'],
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
                $this->validateItems($validator, "sections.$i", $type, $config, $items);

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
    }

    private function validateItems(Validator $v, string $path, ?string $type, array $config, array $items): void
    {
        $withTarget = array_filter($items, static fn ($it) => ! empty($it['itemable_id'] ?? null));

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

        if ($type === HomeSectionTypeEnum::PRODUCTS() && count($withTarget) < 1) {
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

                if ($hasLink && ! $this->bannerItemImageProvided($i, $j, $item['id'] ?? null, $item)) {
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
            $existingItem = \App\Models\HomeSectionItem::find($itemId);
            if ($existingItem && $existingItem->image) {
                return true;
            }
        }

        return false;
    }
}
