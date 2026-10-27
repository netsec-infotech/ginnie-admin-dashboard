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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LanguageController extends Controller
{
    use ChecksPermissions;

    public function index(): View
    {
        return view('admin.languages.index', [
            'areas' => [
                [
                    'name' => __('labels.panel_languages'),
                    'description' => __('labels.panel_languages_description'),
                    'icon' => 'ti-layout-dashboard',
                    'available' => true,
                    'url' => route('admin.languages.panel'),
                ],
                [
                    'name' => __('labels.website_languages'),
                    'description' => __('labels.website_languages_description'),
                    'icon' => 'ti-world-www',
                    'available' => true,
                    'url' => route('admin.languages.clients.show', LanguageSurfaceEnum::WEB->value),
                ],
                [
                    'name' => __('labels.customer_app_languages'),
                    'description' => __('labels.customer_app_languages_description'),
                    'icon' => 'ti-device-mobile',
                    'available' => true,
                    'url' => route('admin.languages.clients.show', LanguageSurfaceEnum::CUSTOMER_APP->value),
                ],
                [
                    'name' => __('labels.seller_app_languages'),
                    'description' => __('labels.seller_app_languages_description'),
                    'icon' => 'ti-building-store',
                    'available' => true,
                    'url' => route('admin.languages.clients.show', LanguageSurfaceEnum::SELLER_APP->value),
                ],
                [
                    'name' => __('labels.rider_app_languages'),
                    'description' => __('labels.rider_app_languages_description'),
                    'icon' => 'ti-motorbike',
                    'available' => true,
                    'url' => route('admin.languages.clients.show', LanguageSurfaceEnum::RIDER_APP->value),
                ],
            ],
        ]);
    }

    public function panel(ClientLanguageService $clientLanguages): View
    {
        $columns = [
            ['data' => 'language', 'name' => 'native_name', 'label' => __('labels.language')],
            ['data' => 'code', 'name' => 'code', 'label' => __('labels.code')],
            ['data' => 'direction', 'name' => 'direction', 'label' => __('labels.direction')],
            ['data' => 'translation_progress', 'name' => 'translations_count', 'label' => __('labels.translation_progress'), 'searchable' => false],
            ['data' => 'status', 'name' => 'is_active', 'label' => __('labels.status'), 'searchable' => false],
            ['data' => 'default', 'name' => 'is_default', 'label' => __('labels.default_language'), 'searchable' => false],
            ['data' => 'sort_order', 'name' => 'sort_order', 'label' => __('labels.sort_order'), 'searchable' => false],
            ['data' => 'action', 'name' => 'action', 'label' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $installedLanguageCodes = Language::query()
            ->where('surface', LanguageSurfaceEnum::PANEL->value)
            ->pluck('code')
            ->all();

        return view('admin.languages.panel', [
            'columns' => $columns,
            'quickAddLanguages' => $clientLanguages->quickAddOptions(LanguageSurfaceEnum::PANEL, $installedLanguageCodes),
            'createPermission' => $this->hasPermission(AdminPermissionEnum::LANGUAGE_CREATE()),
        ]);
    }

    public function datatable(Request $request, LanguageService $languages): JsonResponse
    {
        $draw = (int) $request->input('draw');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 10)));
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $status = (string) $request->input('status');
        $orderIndex = (int) data_get($request->input('order'), '0.column', 6);
        $orderDirection = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['native_name', 'code', 'direction', 'translations_count', 'is_active', 'is_default', 'sort_order'];
        $orderBy = $sortable[$orderIndex] ?? 'sort_order';
        $query = Language::query()
            ->where('surface', LanguageSurfaceEnum::PANEL->value)
            ->with('translations:id,language_id,key')
            ->withCount('translations');
        $recordsTotal = (clone $query)->count();

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('native_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('direction', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();
        $sourceKeys = array_keys($languages->source());
        $sourceCount = count($sourceKeys);
        $editPermission = $this->hasPermission(AdminPermissionEnum::LANGUAGE_EDIT());
        $deletePermission = $this->hasPermission(AdminPermissionEnum::LANGUAGE_DELETE());
        $data = $query
            ->orderBy($orderBy, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn (Language $language) => [
                'language' => view('admin.languages.partials.language-cell', compact('language'))->render(),
                'code' => '<span class="badge bg-secondary-lt" dir="ltr">'.e($language->code).'</span>',
                'direction' => '<span dir="ltr">'.e(strtoupper($language->direction)).'</span>',
                'translation_progress' => e(($language->code === 'en'
                    ? $sourceCount
                    : $language->translations->pluck('key')->intersect($sourceKeys)->unique()->count()).' / '.$sourceCount),
                'status' => '<span class="badge '.($language->is_active ? 'bg-success-lt' : 'bg-secondary-lt').'">'.e($language->is_active ? __('labels.active') : __('labels.draft')).'</span>',
                'default' => $language->is_default
                    ? '<span class="badge bg-primary-lt">'.e(__('labels.default')).'</span>'
                    : '<span class="text-muted">&mdash;</span>',
                'sort_order' => $language->sort_order,
                'action' => view('admin.languages.partials.actions', [
                    'language' => $language,
                    'editPermission' => $editPermission,
                    'deletePermission' => $deletePermission,
                ])->render(),
            ])
            ->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function create(Request $request): View
    {
        $quickAdd = config('languages.quick_add.'.(string) $request->query('quick_add'));

        return view('admin.languages.form', [
            'language' => new Language([
                'code' => $request->query('quick_add'),
                'name' => $quickAdd['name'] ?? '',
                'native_name' => $quickAdd['native_name'] ?? '',
                'country_code' => $quickAdd['country_code'] ?? null,
                'direction' => $quickAdd['direction'] ?? 'ltr',
                'sort_order' => (Language::query()->where('surface', LanguageSurfaceEnum::PANEL->value)->max('sort_order') ?? 0) + 10,
            ]),
            'countries' => Country::orderBy('name')->get(['id', 'name', 'iso2', 'emoji']),
        ]);
    }

    public function store(Request $request, LanguageService $languages): JsonResponse|RedirectResponse
    {
        $request->merge(['code' => $languages->normalizeCode((string) $request->input('code'))]);
        $data = $this->validateLanguage($request);
        $data['country_code'] = strtolower(trim((string) $data['country_code']));
        $data['surface'] = LanguageSurfaceEnum::PANEL->value;
        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $data['is_default'];
        $data['is_recommended'] = array_key_exists($data['code'], config('languages.quick_add', []));
        $language = DB::transaction(function () use ($data) {
            if ($data['is_default']) {
                Language::query()->where('surface', LanguageSurfaceEnum::PANEL->value)->lockForUpdate()->get();
                Language::query()
                    ->where('surface', LanguageSurfaceEnum::PANEL->value)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return Language::create($data);
        });
        $languages->forgetLanguages();
        $redirectUrl = route('admin.languages.edit', $language);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_created_successfully', ['redirect_url' => $redirectUrl], 201)
            : redirect($redirectUrl)->with('success', __('labels.language_created_successfully'));
    }

    public function edit(Language $language, LanguageService $languages): View
    {
        $this->ensurePanelLanguage($language);
        $source = collect($languages->source());
        $translations = $language->translations()->pluck('value', 'key');
        $translatedCount = $translations->filter(fn (string $value) => trim($value) !== '')
            ->keys()
            ->intersect($source->keys())
            ->count();

        return view('admin.languages.form', [
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
        ]);
    }

    public function translationsDatatable(Language $language, Request $request, LanguageService $languages): JsonResponse
    {
        $this->ensurePanelLanguage($language);
        abort_if($language->code === 'en', 404);

        $draw = (int) $request->input('draw');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 25)));
        $search = mb_strtolower(trim((string) data_get($request->input('search'), 'value', '')));
        $keySearch = mb_strtolower(trim((string) $request->input('key', '')));
        $status = (string) $request->input('status');
        $orderIndex = (int) data_get($request->input('order'), '0.column', 0);
        $orderDirection = strtolower((string) data_get($request->input('order'), '0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['key', 'source', 'translation'];
        $orderBy = $sortable[$orderIndex] ?? 'key';
        $source = collect($languages->source());
        $translations = $language->translations()->pluck('value', 'key');
        $rows = $source->map(fn (string $value, string $key) => [
            'key' => $key,
            'source' => $value,
            'translation' => (string) $translations->get($key, ''),
        ])->values();
        $recordsTotal = $rows->count();

        if ($status === 'missing') {
            $rows = $rows->filter(fn (array $row) => $row['translation'] === '');
        } elseif ($status === 'translated') {
            $rows = $rows->filter(fn (array $row) => $row['translation'] !== '');
        }

        if ($keySearch !== '') {
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['key']), $keySearch));
        }

        if ($search !== '') {
            $rows = $rows->filter(fn (array $row) => str_contains(
                mb_strtolower($row['key'].' '.$row['source'].' '.$row['translation']),
                $search,
            ));
        }

        $recordsFiltered = $rows->count();
        $translatedCount = $translations->filter(fn (string $value) => trim($value) !== '')
            ->keys()
            ->intersect($source->keys())
            ->count();
        $rows = $rows->sortBy(
            fn (array $row) => mb_strtolower($row[$orderBy]),
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
            'data' => $rows->map(fn (array $row, int $index) => [
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

    public function update(Language $language, Request $request, LanguageService $languages): JsonResponse|RedirectResponse
    {
        $this->ensurePanelLanguage($language);
        $request->merge(['code' => $language->code]);
        $data = $this->validateLanguage($request, $language);
        $data['country_code'] = strtolower(trim((string) $data['country_code']));
        $data['is_default'] = $language->is_default || $request->boolean('is_default');
        if ($data['is_default']) {
            $data['is_active'] = true;
        }
        if ($language->code === 'en') {
            $data['direction'] = 'ltr';
        }
        DB::transaction(function () use ($data, $language) {
            if ($data['is_default']) {
                Language::query()->where('surface', LanguageSurfaceEnum::PANEL->value)->lockForUpdate()->get();
                Language::query()
                    ->where('surface', LanguageSurfaceEnum::PANEL->value)
                    ->whereKeyNot($language->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $language->update($data);
        });
        $languages->forgetLanguages();

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_updated_successfully', ['redirect_url' => route('admin.languages.edit', $language)])
            : back()->with('success', __('labels.language_updated_successfully'));
    }

    public function saveTranslations(Language $language, Request $request, LanguageService $languages): JsonResponse|RedirectResponse
    {
        $this->ensurePanelLanguage($language);
        if ($language->code === 'en') {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, 'labels.language_english_file_managed', null, 422)
                : back()->with('error', __('labels.language_english_file_managed'));
        }

        $data = $request->validate([
            'translations' => ['nullable', 'array', 'max:100'],
            'translations.*.key' => ['required', 'string', 'max:191'],
            'translations.*.value' => ['nullable', 'string', 'max:10000'],
        ]);
        try {
            $languages->saveTranslations($language, $data['translations'] ?? []);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['translations' => [$exception->getMessage()]]);
        }

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_translations_saved', ['saved' => count($data['translations'] ?? [])])
            : back()->with('success', __('labels.language_translations_saved'));
    }

    public function toggle(Language $language, Request $request, LanguageService $languages): JsonResponse|RedirectResponse
    {
        $this->ensurePanelLanguage($language);
        if ($language->code === 'en' && $language->is_active) {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, 'labels.language_english_required', null, 422)
                : back()->with('error', __('labels.language_english_required'));
        }
        if ($language->is_default && $language->is_active) {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, 'labels.language_default_deactivate_forbidden', null, 422)
                : back()->with('error', __('labels.language_default_deactivate_forbidden'));
        }
        $language->update(['is_active' => ! $language->is_active]);
        $languages->forgetLanguages();

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_updated_successfully')
            : back()->with('success', __('labels.language_updated_successfully'));
    }

    public function destroy(Language $language, Request $request, LanguageService $languages): JsonResponse|RedirectResponse
    {
        $this->ensurePanelLanguage($language);
        if ($language->code === 'en') {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, 'labels.language_english_delete_forbidden', null, 422)
                : back()->with('error', __('labels.language_english_delete_forbidden'));
        }
        if ($language->is_default) {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, 'labels.language_default_delete_forbidden', null, 422)
                : back()->with('error', __('labels.language_default_delete_forbidden'));
        }
        $languages->forget($language);
        $language->delete();

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.language_deleted_successfully')
            : redirect()->route('admin.languages.panel')->with('success', __('labels.language_deleted_successfully'));
    }

    public function import(Language $language, Request $request, LanguageService $languages): JsonResponse|RedirectResponse
    {
        $this->ensurePanelLanguage($language);
        if ($language->code === 'en') {
            return $request->expectsJson()
                ? ApiResponseType::sendJsonResponse(false, 'labels.language_english_file_managed', null, 422)
                : back()->with('error', __('labels.language_english_file_managed'));
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
            if ($labels !== [] && array_is_list($labels)) {
                throw new InvalidArgumentException(__('labels.language_pack_labels_invalid'));
            }
            $count = DB::transaction(fn () => $languages->import($language, $labels));
        } catch (JsonException) {
            $message = __('labels.language_pack_invalid_json');
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        } catch (\Throwable) {
            $message = __('labels.language_pack_invalid');
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

    public function downloadQuickAdd(string $code): StreamedResponse
    {
        $definition = config("languages.quick_add.{$code}");
        abort_unless(is_array($definition), 404);

        $payload = json_decode((string) file_get_contents(resource_path("language-packs/panel/{$code}.json")), true, 512, JSON_THROW_ON_ERROR);

        return $this->jsonResponse($code, $payload['labels'] ?? []);
    }

    public function export(Language $language, LanguageService $languages): StreamedResponse
    {
        $this->ensurePanelLanguage($language);

        return $this->jsonResponse($language->code, $languages->exportTree($language));
    }

    private function validateLanguage(Request $request, ?Language $language = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', Rule::unique('languages', 'code')->where('surface', $language?->surface ?? LanguageSurfaceEnum::PANEL->value)->ignore($language?->id)],
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

    private function ensurePanelLanguage(Language $language): void
    {
        abort_unless($language->surface === LanguageSurfaceEnum::PANEL->value, 404);
    }
}

