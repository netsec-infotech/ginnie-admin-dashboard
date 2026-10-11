<?php

namespace App\Services;

use App\Enums\HomePage\ItemPurposeEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomeLayout;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class HomeBuilderService
{
    /** Client-facing itemable keys mapped to their model classes. */
    public const ITEMABLE_MAP = [
        'product'  => Product::class,
        'category' => Category::class,
        'brand'    => Brand::class,
    ];

    /** Load (lazily creating) the layout for a platform + scope, eager-loading sections + items. */
    public function loadForScope(string $scopeType, ?int $scopeId, string $platform = 'app', ?int $zoneId = null): HomeLayout
    {
        $layout = HomeLayout::firstOrCreate(
            ['platform' => $platform, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'zone_id' => $zoneId],
            ['is_active' => true],
        );

        return $layout->load([
            'sections' => fn($q) => $q->orderBy('sort_order'),
            'sections.items' => fn($q) => $q->orderBy('sort_order'),
            'sections.items.itemable',
            'sections.zones:id',
        ]);
    }

    /**
     * Persist the whole layout in one transaction: upsert the layout, sync its
     * sections (preserving ids so attached media survives), each section's items,
     * and its zone pivot. Sections/items absent from the payload are deleted.
     */
    public function save(array $data): HomeLayout
    {
        return DB::transaction(function () use ($data) {
            $platform = $data['platform'] ?? 'app';

            $layout = HomeLayout::updateOrCreate(
                [
                    'platform' => $platform,
                    'scope_type' => $data['scope_type'],
                    'scope_id' => $data['scope_id'] ?? null,
                    'zone_id' => $data['zone_id'] ?? null,
                ],
                ['is_active' => $data['is_active'] ?? true],
            );

            if (!empty($data['excluded_global_section_ids']) && !empty($data['zone_id'])) {
                $zoneId = (int) $data['zone_id'];
                HomeSection::whereIn('id', $data['excluded_global_section_ids'])->get()->each(function (HomeSection $section) use ($zoneId) {
                    $section->zones()->detach($zoneId);
                });
            }

            $keptSectionIds = [];

            foreach ($data['sections'] ?? [] as $index => $sectionData) {
                $section = $this->upsertSection($layout, $sectionData, $index);
                $keptSectionIds[] = $section->id;

                $section->zones()->sync($sectionData['zone_ids'] ?? []);
                $this->syncItems($section, $sectionData['items'] ?? []);
            }

            $layout->sections()->whereNotIn('id', $keptSectionIds ?: [0])->get()
                ->each(fn(HomeSection $section) => $section->delete());

            return $this->loadForScope($layout->scope_type, $layout->scope_id, $layout->platform, $layout->zone_id);
        });
    }

    private function upsertSection(HomeLayout $layout, array $data, int $index): HomeSection
    {
        $attributes = [
            'type'       => $data['type'],
            'title'      => $data['title'] ?? null,
            'style'      => $data['style'] ?? null,
            'config'     => $data['config'] ?? [],
            'sort_order' => $data['sort_order'] ?? $index,
            'is_active'  => $data['is_active'] ?? true,
            'start_at'   => $data['start_at'] ?? null,
            'end_at'     => $data['end_at'] ?? null,
        ];

        if (! empty($data['id'])) {
            $section = $layout->sections()->whereKey($data['id'])->first();
            if ($section) {
                $section->update($attributes);

                return $section;
            }
        }

        return $layout->sections()->create($attributes);
    }

    private function syncItems(HomeSection $section, array $items): void
    {
        $keptItemIds = [];

        foreach ($items as $index => $itemData) {
            $attributes = [
                'purpose'       => $itemData['purpose'] ?? ItemPurposeEnum::DISPLAY(),
                'itemable_type' => $this->resolveItemableClass($itemData['itemable_type'] ?? null),
                'itemable_id'   => $itemData['itemable_id'] ?? null,
                'title'         => $itemData['title'] ?? null,
                'link_url'      => $itemData['link_url'] ?? null,
                'sort_order'    => $itemData['sort_order'] ?? $index,
            ];

            if (! empty($itemData['id'])) {
                $item = $section->items()->whereKey($itemData['id'])->first();
                if ($item) {
                    $item->update($attributes);
                    $keptItemIds[] = $item->id;

                    continue;
                }
            }

            $keptItemIds[] = $section->items()->create($attributes)->id;
        }

        $section->items()->whereNotIn('id', $keptItemIds ?: [0])->get()
            ->each(fn(HomeSectionItem $item) => $item->delete());
    }

    private function resolveItemableClass(?string $key): ?string
    {
        return $key ? (self::ITEMABLE_MAP[$key] ?? null) : null;
    }
}
