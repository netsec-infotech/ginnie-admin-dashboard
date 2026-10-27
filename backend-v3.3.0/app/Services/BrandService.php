<?php

namespace App\Services;

use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Helpers\TranslationHelper;
use App\Models\Brand;
use App\Traits\HasZoneAvailability;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class BrandService
{
    use HasZoneAvailability;

    /**
     * Build base brand query constrained by active status and availability in given stores.
     *
     * A brand is considered available if it has at least one approved and active product
     * that has variants available in any of the specified stores.
     */
    protected function baseAvailabilityQuery(array $storeIds): Builder
    {
        return Brand::query()
            ->where('status', BrandStatusEnum::ACTIVE())
            ->distinct()
            ->whereHas('products', function ($productQuery) use ($storeIds) {
                $productQuery->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                    ->whereHas('variants.storeProductVariants', function ($variantQuery) use ($storeIds) {
                        $variantQuery->whereIn('store_id', $storeIds);
                    });
            });
    }

    /**
     * Canonical zone-aware brand query.
     *
     * Returns active brands with a `products_count` attribute reflecting how many
     * active+approved products with variants in the given stores they have, and
     * filters out brands with zero such products. Ordering, scope filtering and
     * pagination are intentionally left to the caller.
     *
     * Used by BrandApiController (customer) + ZonePreviewService (admin) so any
     * tweak to the base shape automatically reflects in both.
     */
    public function availableInStoresQuery(array $storeIds): Builder
    {
        return Brand::query()
            ->where('status', BrandStatusEnum::ACTIVE())
            ->withCount([
                'products as products_count' => function (Builder $q) use ($storeIds) {
                    if (empty($storeIds)) {
                        $q->whereRaw('1 = 0');
                        return;
                    }
                    $q->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                        ->where('status', ProductStatusEnum::ACTIVE())
                        ->whereHas(
                            'variants.storeProductVariants',
                            fn(Builder $sq) => $sq->whereIn('store_id', $storeIds),
                        );
                },
            ])
            ->having('products_count', '>', 0);
    }

    /**
     * Persist a new brand. Single source of truth for create writes — used by
     * BrandController and BrandBulkImportService alike.
     *
     * $logo may be:
     *   - null              → no logo attach
     *   - UploadedFile      → file upload path (web/admin form)
     *   - string (URL)      → download via Guzzle and attach (bulk CSV path)
     *
     * Returns the persisted Brand. The caller is responsible for any try/catch
     * + response envelope translation; this service throws on persistence
     * failure so the caller can surface a meaningful error.
     */
    public function createBrand(array $attributes, mixed $logo = null): Brand
    {
        $attributes = $this->normaliseDefaults($attributes);
        $brand = Brand::create($attributes);
        $this->attachLogo($brand, $logo);

        return $brand->refresh();
    }

    /**
     * Persist changes to an existing brand. Single source of truth for update
     * writes. The $attributes array should already contain ONLY the fields
     * the caller wants written (bulk update mode passes a diff; web update
     * passes the full validated payload). Pass an empty array to only swap
     * the logo.
     */
    public function updateBrand(Brand $brand, array $attributes, mixed $logo = null): Brand
    {
        if (! empty($attributes)) {
            if (isset($attributes['title'])) {
                $this->forgetBlankTranslations($brand, 'title', $attributes['title']);
                $attributes['title'] = $this->mergeTranslatableAttribute(
                    $brand->getTranslations('title') ?: [],
                    $attributes['title']
                );
            }
            if (isset($attributes['description'])) {
                $this->forgetBlankTranslations($brand, 'description', $attributes['description']);
                $attributes['description'] = $this->mergeTranslatableAttribute(
                    $brand->getTranslations('description') ?: [],
                    $attributes['description']
                );
            }

            $brand->update($this->normaliseDefaults($attributes));
        }
        $this->attachLogo($brand, $logo);

        return $brand->refresh();
    }

    /**
     * Merge incoming translations with existing ones.
     * Empty values unset that locale while preserving canonical English.
     */
    protected function mergeTranslatableAttribute(array $existing, mixed $incoming): array
    {
        $defaultLocale = TranslationHelper::getDefaultLocale();

        if (is_string($incoming)) {
            $trimmed = trim($incoming);
            if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $incoming = $decoded;
                }
            } else {
                if ($trimmed !== '') {
                    $existing[$defaultLocale] = $trimmed;
                }


                return $existing;
            }
        }

        if (is_array($incoming)) {
            foreach ($incoming as $locale => $val) {
                $locale = (string) $locale;
                $valStr = $val !== null ? trim((string) $val) : '';
                if ($valStr === '') {
                    if ($locale !== $defaultLocale) {
                        unset($existing[$locale]);
                    }
                } else {
                    $existing[$locale] = $valStr;
                }
            }
        }

        return $existing;
    }

    /**
     * Spatie merges arrays passed to setTranslations(), so explicitly forget
     * submitted blank non-English values before applying the merged payload.
     */
    protected function forgetBlankTranslations(Brand $brand, string $attribute, mixed $incoming): void
    {
        if (! is_array($incoming)) {
            return;
        }

        $defaultLocale = TranslationHelper::getDefaultLocale();

        foreach ($incoming as $locale => $value) {
            if ((string) $locale !== $defaultLocale && trim((string) ($value ?? '')) === '') {
                $brand->forgetTranslation($attribute, (string) $locale);
            }
        }
    }

    /**
     * Override HasZoneAvailability applySearch for translatable title and description.
     */
    protected function applySearch(Builder $query, string $search): void
    {
        $requestedLocale = app(LanguageService::class)->normalizeCode(app()->getLocale());
        $locale = app(CatalogLanguageService::class)->isValidCatalogLocale($requestedLocale)
            ? $requestedLocale
            : 'en';
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search));
        $normalized = mb_strtolower($escaped);
        $lowerLikeExpr = "%{$normalized}%";
        $driver = $query->getConnection()->getDriverName();

        $query->where(function (Builder $q) use ($driver, $locale, $lowerLikeExpr) {
            match ($driver) {
                'sqlite' => $q->whereRaw("LOWER(json_extract(title, '$.{$locale}')) LIKE ? OR LOWER(json_extract(title, '$.en')) LIKE ? OR LOWER(json_extract(description, '$.{$locale}')) LIKE ? OR LOWER(json_extract(description, '$.en')) LIKE ?", [$lowerLikeExpr, $lowerLikeExpr, $lowerLikeExpr, $lowerLikeExpr]),
                'pgsql' => $q->whereRaw("LOWER(title->>'{$locale}') LIKE ? OR LOWER(title->>'en') LIKE ? OR LOWER(description->>'{$locale}') LIKE ? OR LOWER(description->>'en') LIKE ?", [$lowerLikeExpr, $lowerLikeExpr, $lowerLikeExpr, $lowerLikeExpr]),
                default => $q->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"{$locale}\"'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(title, '$.\"en\"'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(description, '$.\"{$locale}\"'))) LIKE ? OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(description, '$.\"en\"'))) LIKE ?", [$lowerLikeExpr, $lowerLikeExpr, $lowerLikeExpr, $lowerLikeExpr]),
            };
        });
    }

    /**
     * Apply the project-wide brand defaults in one place: empty/missing status
     * falls back to INACTIVE; empty/missing scope_type falls back to GLOBAL;
     * scope_id is forced null when scope_type is global. Idempotent — safe to
     * call on a partial diff payload.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normaliseDefaults(array $attributes): array
    {
        if (array_key_exists('status', $attributes) && empty($attributes['status'])) {
            $attributes['status'] = BrandStatusEnum::INACTIVE();
        }
        if (array_key_exists('scope_type', $attributes) && empty($attributes['scope_type'])) {
            $attributes['scope_type'] = HomePageScopeEnum::GLOBAL();
        }
        if (isset($attributes['scope_type']) && $attributes['scope_type'] === HomePageScopeEnum::GLOBAL()) {
            $attributes['scope_id'] = null;
        }

        if (isset($attributes['title'])) {
            $attributes['title'] = TranslationHelper::normalize($attributes['title']);
        }
        if (isset($attributes['description'])) {
            $attributes['description'] = TranslationHelper::normalize($attributes['description']);
        }

        return $attributes;
    }

    /**
     * Branch on the logo source and attach to the canonical media collection.
     * UploadedFile → direct attach; string URL → Guzzle download with size +
     * timeout caps then attach; null → no-op. Errors during URL download are
     * soft-failed (logged + non-throwing) so they don't break the parent
     * create/update transaction — the brand row stays valid without a logo.
     */
    protected function attachLogo(Brand $brand, mixed $logo): void
    {
        if ($logo === null || $logo === '') {
            return;
        }

        if ($logo instanceof UploadedFile) {
            $brand->clearMediaCollection(SpatieMediaCollectionName::BRAND_LOGO());
            $brand->addMedia($logo)
                ->toMediaCollection(SpatieMediaCollectionName::BRAND_LOGO());

            return;
        }

        if (is_string($logo)) {
            $this->downloadAndAttachLogoFromUrl($brand, $logo);
        }
    }

    /**
     * Download a public logo URL and attach it to the brand's media collection.
     * Bounded by `bulk_upload.logo_download_timeout` and
     * `bulk_upload.logo_max_bytes`. Returns null on success or a
     * translation-key-friendly reason string on soft failure — callers
     * (bulk importer) can surface this in their per-row log line.
     */
    public function downloadAndAttachLogoFromUrl(Brand $brand, string $url): ?string
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return __('labels.bulk_upload_logo_invalid_url');
        }

        $timeout = (int) config('bulk_upload.logo_download_timeout', 10);
        $maxBytes = (int) config('bulk_upload.logo_max_bytes', 5 * 1024 * 1024);

        try {
            $client = new Client(['timeout' => $timeout]);
            $response = $client->request('GET', $url, [
                'stream' => true,
                'http_errors' => false,
            ]);
            if ($response->getStatusCode() !== 200) {
                return __('labels.bulk_upload_logo_http_error', ['code' => $response->getStatusCode()]);
            }

            $body = $response->getBody();
            $contents = '';
            while (! $body->eof()) {
                $contents .= $body->read(8192);
                if (strlen($contents) > $maxBytes) {
                    return __('labels.bulk_upload_logo_too_large');
                }
            }
            if ($contents === '') {
                return __('labels.bulk_upload_logo_empty');
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'brand_logo_');
            file_put_contents($tempPath, $contents);

            $filename = basename(parse_url($url, PHP_URL_PATH) ?: 'logo.jpg') ?: 'logo.jpg';
            $brand->clearMediaCollection(SpatieMediaCollectionName::BRAND_LOGO());
            $brand->addMedia($tempPath)
                ->usingFileName($filename)
                ->toMediaCollection(SpatieMediaCollectionName::BRAND_LOGO());

            return null;
        } catch (GuzzleException) {
            return __('labels.bulk_upload_logo_network_error');
        } catch (\Throwable $e) {
            Log::warning('BrandService::downloadAndAttachLogoFromUrl failed', [
                'brand_id' => $brand->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return __('labels.bulk_upload_logo_attach_failed');
        }
    }
}
