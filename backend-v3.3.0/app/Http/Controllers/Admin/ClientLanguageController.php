<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Enums\LanguageSurfaceEnum;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Language;
use App\Services\ClientLanguageService;
use App\Services\LanguageService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientLanguageController extends Controller
{
    use ChecksPermissions;

    public function show(string $surface, ClientLanguageService $languages): View
    {
        $surface = $this->surface($surface);
        $source = collect($languages->source($surface));
        $installedLanguageCodes = Language::query()
            ->where('surface', $surface->value)
            ->pluck('code')
            ->all();
        $missingSourceKeys = $source
            ->filter(fn (string $value): bool => trim($value) === '')
            ->keys()
            ->values();

        return view('admin.languages.client', [
            'surface' => $surface,
            'sourceCount' => $source->count(),
            'missingSourceKeys' => $missingSourceKeys,
            'columns' => $this->languageColumns(),
            'quickAddLanguages' => $languages->quickAddOptions($surface, $installedLanguageCodes),
            'createPermission' => $this->hasPermission(AdminPermissionEnum::LANGUAGE_CREATE()),
        ]);
    }

    public function datatable(string $surface, Request $request, ClientLanguageService $languages): JsonResponse
    {
        $surface = $this->surface($surface);
        $draw = (int) $request->input('draw');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 10)));
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $status = (string) $request->input('status');
        $orderIndex = (int) data_get($request->input('order'), '0.column', 6);
        $orderDirection = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['native_name', 'code', 'direction', 'translations_count', 'is_active', 'is_default', 'sort_order'];
        $orderBy = $sortable[$orderIndex] ?? 'sort_order';
        $sourceKeys = array_keys($languages->source($surface));
        $query = Language::query()
            ->where('surface', $surface->value)
            ->withCount(['translations' => fn (Builder $query) => $query->whereIn('key', $sourceKeys)]);
        $recordsTotal = (clone $query)->count();

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
        if ($search !== '') {
            $query->where(fn (Builder $query) => $query
                ->where('languages.name', 'like', "%{$search}%")
                ->orWhere('languages.native_name', 'like', "%{$search}%")
                ->orWhere('languages.code', 'like', "%{$search}%"));
        }

        $recordsFiltered = (clone $query)->count();
        $sourceCount = count($sourceKeys);
        $editPermission = $this->hasPermission(AdminPermissionEnum::LANGUAGE_EDIT());
        $deletePermission = $this->hasPermission(AdminPermissionEnum::LANGUAGE_DELETE());
        $data = $query->orderBy($orderBy, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn (Language $language): array => [
                'language' => view('admin.languages.partials.language-cell', compact('language'))->render(),
                'code' => '<span class="badge bg-secondary-lt" dir="ltr">'.e($language->code).'</span>',
                'direction' => '<span dir="ltr">'.e(strtoupper($language->direction)).'</span>',
                'translation_progress' => e(($language->code === 'en' ? $sourceCount : $language->translations_count).' / '.$sourceCount),
                'status' => '<span class="badge '.($language->is_active ? 'bg-success-lt' : 'bg-secondary-lt').'">'.e($language->is_active ? __('labels.active') : __('labels.inactive')).'</span>',
                'default' => $language->is_default
                    ? '<span class="badge bg-primary-lt">'.e(__('labels.default')).'</span>'
                    : '<span class="text-muted">&mdash;</span>',
                'sort_order' => $language->sort_order,
                'action' => view('admin.languages.partials.client-actions', [
                    'surface' => $surface,
                    'language' => $language,
                    'editPermission' => $editPermission,
                    'deletePermission' => $deletePermission,
                ])->render(),
            ])
            ->all();

        return response()->json(compact('draw', 'recordsTotal', 'recordsFiltered', 'data'));
    }

    public function create(string $surface, Request $request): View
    {
        $surface = $this->surface($surface);
        $quickAdd = config('languages.quick_add.'.(string) $request->query('quick_add'));

        return view('admin.languages.client-form', [
            'surface' => $surface,
            'editing' => false,
            'language' => new Language([
                'surface' => $surface->value,
                'code' => $request->query('quick_add'),
                'name' => $quickAdd['name'] ?? '',
                'native_name' => $quickAdd['native_name'] ?? '',
                'country_code' => $quickAdd['country_code'] ?? null,
                'direction' => $quickAdd['direction'] ?? 'ltr',
                'sort_order' => (Language::query()->where('surface', $surface->value)->max('sort_order') ?? 0) + 10,
            ]),
            'countries' => Country::orderBy('name')->get(['id', 'name', 'iso2', 'emoji']),
        ]);
    }

    public function store(string $surface, Request $request, ClientLanguageService $languages, LanguageService $panelLanguages): JsonResponse|RedirectResponse
    {
        $surface = $this->surface($surface);
        $request->merge(['code' => $panelLanguages->normalizeCode((string) $request->input('code'))]);
        $data = $this->validateLanguage($request, $surface);
        $data['country_code'] = strtolower(trim((string) $data['country_code']));
        $data['surface'] = $surface->value;
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $data['is_default'];
        $data['is_recommended'] = array_key_exists($data['code'], config('languages.quick_add', []));
        $language = DB::transaction(function () use ($data, $surface): Language {
            if ($data['is_default']) {
                Language::query()->where('surface', $surface->value)->lockForUpdate()->get();
                Language::query()->where('surface', $surface->value)->where('is_default', true)->update(['is_default' => false]);
            }

            return Language::create($data);
        });
        $languages->forgetLanguages($surface);
        $redirectUrl = route('admin.languages.clients.edit', [$surface->value, $language]);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_created_successfully', ['redirect_url' => $redirectUrl], 201)
            : redirect($redirectUrl)->with('success', __('labels.language_created_successfully'));
    }

    public function toggle(string $surface, Language $language, Request $request, ClientLanguageService $languages): JsonResponse|RedirectResponse
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);

        if ($language->is_active && $language->is_default) {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, 'labels.language_default_deactivate_forbidden', null, 422)
                : back()->with('error', __('labels.language_default_deactivate_forbidden'));
        }

        $language->update(['is_active' => ! $language->is_active]);
        $languages->forgetLanguages($surface);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_updated_successfully')
            : back()->with('success', __('labels.language_updated_successfully'));
    }

    public function makeDefault(string $surface, Language $language, Request $request, ClientLanguageService $languages): JsonResponse|RedirectResponse
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);
        DB::transaction(function () use ($surface, $language): void {
            Language::query()->where('surface', $surface->value)->lockForUpdate()->get();
            Language::query()->where('surface', $surface->value)->where('is_default', true)->update(['is_default' => false]);
            $language->update(['is_active' => true, 'is_default' => true]);
        });
        $languages->forgetLanguages($surface);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.default_language_updated_successfully')
            : back()->with('success', __('labels.default_language_updated_successfully'));
    }

    public function edit(string $surface, Language $language, ClientLanguageService $languages): View
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);
        $source = collect($languages->source($surface));
        $translations = $languages->translations($surface, $language);
        $translatedCount = $translations->keys()->intersect($source->keys())->count();

        return view('admin.languages.client-form', [
            'surface' => $surface,
            'language' => $language,
            'countries' => Country::orderBy('name')->get(['id', 'name', 'iso2', 'emoji']),
            'translationColumns' => [
                ['data' => 'key', 'name' => 'key', 'label' => __('labels.key')],
                ['data' => 'source', 'name' => 'source', 'label' => __('labels.english_source')],
                ['data' => 'translation', 'name' => 'translation', 'label' => $language->native_name],
            ],
            'translatedCount' => $translatedCount,
            'missingCount' => $source->count() - $translatedCount,
            'sourceCount' => $source->count(),
            'editing' => true,
        ]);
    }

    public function update(string $surface, Language $language, Request $request, ClientLanguageService $languages): JsonResponse|RedirectResponse
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);
        $request->merge(['code' => $language->code]);
        $data = $this->validateLanguage($request, $surface, $language);
        $data['country_code'] = strtolower(trim((string) $data['country_code']));
        $data['is_default'] = $language->is_default || $request->boolean('is_default');
        if ($data['is_default']) {
            $data['is_active'] = true;
        }
        if ($language->code === 'en') {
            $data['direction'] = 'ltr';
        }
        DB::transaction(function () use ($data, $surface, $language): void {
            if ($data['is_default']) {
                Language::query()->where('surface', $surface->value)->lockForUpdate()->get();
                Language::query()
                    ->where('surface', $surface->value)
                    ->whereKeyNot($language->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $language->update($data);
        });
        $languages->forget($language);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_updated_successfully', ['redirect_url' => route('admin.languages.clients.edit', [$surface->value, $language])])
            : back()->with('success', __('labels.language_updated_successfully'));
    }

    public function destroy(string $surface, Language $language, ClientLanguageService $languages): JsonResponse
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);
        if ($language->code === 'en') {
            return ApiResponseType::sendJsonResponse(false, 'labels.language_english_delete_forbidden', null, 422);
        }
        if ($language->is_default) {
            return ApiResponseType::sendJsonResponse(false, 'labels.language_default_delete_forbidden', null, 422);
        }

        $languages->forget($language);
        $language->delete();

        return ApiResponseType::sendJsonResponse(true, 'labels.language_deleted_successfully');
    }

    public function translationsDatatable(string $surface, Language $language, Request $request, ClientLanguageService $languages): JsonResponse
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);
        abort_if($language->code === 'en', 404);
        $draw = (int) $request->input('draw');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 25)));
        $search = mb_strtolower(trim((string) data_get($request->input('search'), 'value', '')));
        $keySearch = mb_strtolower(trim((string) $request->input('key', '')));
        $status = (string) $request->input('status');
        $orderIndex = (int) data_get($request->input('order'), '0.column', 0);
        $orderDirection = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderBy = ['key', 'source', 'translation'][$orderIndex] ?? 'key';
        $source = collect($languages->source($surface));
        $translations = $languages->translations($surface, $language);
        $rows = $source->map(fn (string $value, string $key): array => [
            'key' => $key,
            'source' => $value,
            'translation' => (string) $translations->get($key, ''),
        ])->values();
        $recordsTotal = $rows->count();

        if ($status === 'missing') {
            $rows = $rows->where('translation', '');
        } elseif ($status === 'translated') {
            $rows = $rows->where('translation', '!=', '');
        }
        if ($keySearch !== '') {
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['key']), $keySearch));
        }
        if ($search !== '') {
            $rows = $rows->filter(fn (array $row): bool => str_contains(
                mb_strtolower($row['key'].' '.$row['source'].' '.$row['translation']),
                $search,
            ));
        }

        $recordsFiltered = $rows->count();
        $translatedCount = $translations->keys()->intersect($source->keys())->count();
        $rows = $rows->sortBy(
            fn (array $row): string => mb_strtolower($row[$orderBy]),
            SORT_NATURAL,
            $orderDirection === 'desc',
        )->slice($start, $length)->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'translatedCount' => $translatedCount,
            'missingCount' => $recordsTotal - $translatedCount,
            'sourceCount' => $recordsTotal,
            'data' => $rows->map(fn (array $row, int $index): array => [
                'key' => '<code dir="ltr">'.e($row['key']).'</code>',
                'source' => '<span dir="ltr">'.e($row['source']).'</span>',
                'translation' => view('admin.languages.partials.translation-input', [
                    'language' => $language,
                    'row' => $row,
                    'index' => $start + $index,
                ])->render(),
            ])->all(),
        ]);
    }

    public function saveTranslations(string $surface, Language $language, Request $request, ClientLanguageService $languages): JsonResponse|RedirectResponse
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);
        abort_if($language->code === 'en', 404);
        $data = $request->validate([
            'translations' => ['nullable', 'array', 'max:100'],
            'translations.*.key' => ['required', 'string', 'max:191'],
            'translations.*.value' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $languages->saveTranslations($surface, $language, $data['translations'] ?? []);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['translations' => [$exception->getMessage()]]);
        }

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_translations_saved', ['saved' => count($data['translations'] ?? [])])
            : back()->with('success', __('labels.language_translations_saved'));
    }

    public function import(string $surface, Language $language, Request $request, ClientLanguageService $languages): JsonResponse|RedirectResponse
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);
        if ($language->code === 'en') {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, 'labels.language_english_source_managed', null, 422)
                : back()->with('error', __('labels.language_english_source_managed'));
        }

        $request->validate([
            'labels_file' => ['bail', 'required', 'file', 'extensions:json', 'mimetypes:application/json,text/plain', 'max:5120'],
        ], [
            'labels_file.required' => __('labels.language_pack_upload_required'),
            'labels_file.file' => __('labels.language_pack_upload_required'),
            'labels_file.extensions' => __('labels.language_pack_upload_json_only'),
            'labels_file.mimetypes' => __('labels.language_pack_upload_json_only'),
            'labels_file.max' => __('labels.language_pack_upload_too_large'),
        ]);

        try {
            $payload = json_decode((string) $request->file('labels_file')->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ($payload !== [] && array_is_list($payload))) {
                throw new InvalidArgumentException(__('labels.language_pack_labels_invalid'));
            }
            $wrapped = is_array($payload['labels'] ?? null)
                && array_diff(array_keys($payload), ['labels', 'source_labels']) === [];
            $labels = $wrapped ? $payload['labels'] : $payload;
            if (! is_array($labels) || ($labels !== [] && array_is_list($labels))) {
                throw new InvalidArgumentException(__('labels.language_pack_labels_invalid'));
            }
            $count = DB::transaction(fn (): int => $languages->import($surface, $language, $labels));
        } catch (JsonException) {
            $message = __('labels.language_pack_invalid_json');
        } catch (\Throwable $exception) {
            $message = $exception->getMessage() ?: __('labels.language_pack_invalid');
        }

        if (isset($message)) {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, $message, null, 422)
                : back()->with('error', $message);
        }

        $message = __('labels.language_pack_imported', ['count' => $count]);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, $message, ['imported' => $count])
            : back()->with('success', $message);
    }

    public function downloadQuickAdd(string $surface, string $code): StreamedResponse
    {
        $surface = $this->surface($surface);
        $definition = config("languages.quick_add.{$code}");
        abort_unless(is_array($definition), 404);
        $path = resource_path("language-packs/{$surface->value}/{$code}.json");
        abort_unless(is_file($path), 404);
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $wrapped = is_array($payload['labels'] ?? null)
            && array_diff(array_keys($payload), ['labels', 'source_labels']) === [];
        $labels = $wrapped ? $payload['labels'] : $payload;

        return $this->jsonResponse($code, $labels);
    }

    public function export(string $surface, Language $language, ClientLanguageService $languages): StreamedResponse
    {
        $surface = $this->surface($surface);
        $this->ensureSurfaceLanguage($surface, $language);
        $source = collect($languages->source($surface));
        $translations = $languages->translations($surface, $language);
        $template = $source->mapWithKeys(fn (string $value, string $key): array => [
            $key => $language->code === 'en' ? $value : (string) $translations->get($key, ''),
        ]);

        return $this->jsonResponse($language->code, Arr::undot($template->all()));
    }

    private function surface(string $surface): LanguageSurfaceEnum
    {
        $surface = LanguageSurfaceEnum::tryFrom($surface);
        abort_unless($surface && in_array($surface->value, config('languages.enabled_client_surfaces', []), true), 404);

        return $surface;
    }

    private function ensureSurfaceLanguage(LanguageSurfaceEnum $surface, Language $language): void
    {
        abort_unless($language->surface === $surface->value, 404);
    }

    private function validateLanguage(Request $request, LanguageSurfaceEnum $surface, ?Language $language = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[a-z]{2,3}(?:-[A-Z][A-Za-z0-9]{1,7})*$/', Rule::unique('languages', 'code')->where('surface', $surface->value)->ignore($language?->id)],
            'name' => ['required', 'string', 'max:80'],
            'native_name' => ['required', 'string', 'max:80'],
            'country_code' => ['required', 'string', 'max:10'],
            'direction' => ['required', Rule::in(['ltr', 'rtl'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    private function jsonResponse(string $code, array $labels): StreamedResponse
    {
        $payload = ['labels' => $labels];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return response()->streamDownload(fn () => print $json, "{$code}-labels.json", [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    private function languageColumns(): array
    {
        return [
            ['data' => 'language', 'name' => 'native_name', 'label' => __('labels.language')],
            ['data' => 'code', 'name' => 'code', 'label' => __('labels.code')],
            ['data' => 'direction', 'name' => 'direction', 'label' => __('labels.direction')],
            ['data' => 'translation_progress', 'name' => 'translations_count', 'label' => __('labels.translation_progress'), 'searchable' => false],
            ['data' => 'status', 'name' => 'is_active', 'label' => __('labels.status'), 'searchable' => false],
            ['data' => 'default', 'name' => 'is_default', 'label' => __('labels.default_language'), 'searchable' => false],
            ['data' => 'sort_order', 'name' => 'sort_order', 'label' => __('labels.sort_order'), 'searchable' => false],
            ['data' => 'action', 'name' => 'action', 'label' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
    }
}
