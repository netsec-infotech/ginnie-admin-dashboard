<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Blog\BlogStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\WatchBuy\WatchBuyProfileTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreUpdateBlogRequest;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Product;
use App\Models\WatchBuyProfile;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    /**
     * Display a listing of blogs.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->datatable($request);
        }

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'image', 'name' => 'image', 'title' => __('labels.image'), 'orderable' => false, 'searchable' => false],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title')],
            ['data' => 'category', 'name' => 'category', 'title' => __('labels.category'), 'orderable' => false, 'searchable' => false],
            ['data' => 'author', 'name' => 'authorProfile.username', 'title' => __('labels.author_profile')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'is_editors_pick', 'name' => 'is_editors_pick', 'title' => __('labels.editors_pick')],
            ['data' => 'published_at', 'name' => 'published_at', 'title' => __('labels.published_at')],
            ['data' => 'views_count', 'name' => 'views_count', 'title' => __('labels.views_count')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $categories = BlogCategory::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.blogs.index', compact('columns', 'categories'));
    }

    /**
     * Handle DataTables AJAX request.
     */
    protected function datatable(Request $request): JsonResponse
    {
        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start');
        $length = (int) ($request->get('length') ?: 10);
        $searchValue = trim((string) ($request->get('search')['value'] ?? ''));

        $query = Blog::query()->with(['authorProfile.media', 'media']);

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue): void {
                $q->where('title', 'like', "%{$searchValue}%")
                    ->orWhere('short_description', 'like', "%{$searchValue}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('category_id')) {
            $query->whereJsonContains('category_ids', (int) $request->get('category_id'));
        }

        if ($request->filled('is_editors_pick')) {
            $query->where('is_editors_pick', (bool) $request->get('is_editors_pick'));
        }

        $recordsTotal = Blog::query()->count();
        $recordsFiltered = $query->count();

        $blogs = $query->orderByDesc('id')
            ->skip($start)
            ->take($length)
            ->get();

        $allCategories = BlogCategory::query()->get(['id', 'name'])->keyBy('id');

        $data = $blogs->map(function (Blog $blog) use ($allCategories): array {
            $imageUrl = $blog->image ?: asset('assets/images/default-image.png');
            $imageHtml = '<img src="' . e($imageUrl) . '" class="rounded" style="width: 48px; height: 48px; object-fit: cover;">';

            $catIds = array_values(array_filter(array_map('intval', (array) ($blog->category_ids ?? []))));
            $catBadges = [];
            foreach ($catIds as $cId) {
                if (isset($allCategories[$cId])) {
                    $catBadges[] = '<span class="badge bg-purple-lt me-1 mb-1">' . e($allCategories[$cId]->name) . '</span>';
                }
            }
            $categoryHtml = ! empty($catBadges) ? implode('', $catBadges) : '<span class="text-muted">—</span>';

            $author = $blog->authorProfile;
            $authorHtml = $author
                ? '<span class="badge bg-blue-lt">@' . e($author->username) . '</span>'
                : '<span class="text-muted">—</span>';

            $isPublished = $blog->status === BlogStatusEnum::PUBLISHED;
            $statusBadge = $isPublished
                ? '<span class="badge bg-success-lt">' . __('labels.published') . '</span>'
                : '<span class="badge bg-secondary-lt">' . __('labels.draft') . '</span>';

            $toggleStatusUrl = route('admin.blogs.toggle-status', $blog->id);
            $statusHtml = '
                <div class="d-inline-flex align-items-center gap-2">
                    <label class="form-check form-check-single form-switch mb-0">
                        <input class="form-check-input toggle-status cursor-pointer" type="checkbox" ' . ($isPublished ? 'checked' : '') . ' data-url="' . e($toggleStatusUrl) . '">
                    </label>
                    ' . $statusBadge . '
                </div>';

            $isEditorsPick = (bool) $blog->is_editors_pick;
            $editorsPickBadge = $isEditorsPick
                ? '<span class="badge bg-yellow-lt"><i class="ti ti-star me-1"></i>' . __('labels.yes') . '</span>'
                : '<span class="badge bg-secondary-lt">' . __('labels.no') . '</span>';

            $toggleEditorsPickUrl = route('admin.blogs.toggle-editors-pick', $blog->id);
            $editorsPickHtml = '
                <div class="d-inline-flex align-items-center gap-2">
                    <label class="form-check form-check-single form-switch mb-0">
                        <input class="form-check-input toggle-editors-pick cursor-pointer" type="checkbox" ' . ($isEditorsPick ? 'checked' : '') . ' data-url="' . e($toggleEditorsPickUrl) . '">
                    </label>
                    ' . $editorsPickBadge . '
                </div>';

            $publishedAt = $blog->published_at ? $blog->published_at->format('Y-m-d H:i') : '—';

            $editUrl = route('admin.blogs.edit', $blog->id);
            $deleteUrl = route('admin.blogs.destroy', $blog->id);

            $actionHtml = '
                <div class="d-flex justify-content-start align-items-center">
                    <a href="' . e($editUrl) . '" class="btn btn-outline-blue me-2 p-1" title="' . __('labels.edit') . '">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit m-0">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                            <path d="M16 5l3 3" />
                        </svg>
                    </a>
                    <button type="button" class="btn btn-outline-danger p-1 delete-record" data-url="' . e($deleteUrl) . '" title="' . __('labels.delete') . '">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-trash m-0">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M4 7l16 0" />
                            <path d="M10 11l0 6" />
                            <path d="M14 11l0 6" />
                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />
                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />
                        </svg>
                    </button>
                </div>';

            return [
                'id' => $blog->id,
                'image' => $imageHtml,
                'title' => e($blog->title),
                'category' => $categoryHtml,
                'author' => $authorHtml,
                'status' => $statusHtml,
                'is_editors_pick' => $editorsPickHtml,
                'published_at' => $publishedAt,
                'views_count' => (int) $blog->views_count,
                'action' => $actionHtml,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Show form for creating a new blog.
     */
    public function create(): View
    {
        $profiles = WatchBuyProfile::query()
            ->where('profile_type', WatchBuyProfileTypeEnum::PLATFORM)
            ->where('status', 'active')
            ->get();

        $categories = BlogCategory::query()->active()->orderBy('sort_order', 'asc')->orderBy('name')->get();

        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('title')
            ->take(200)
            ->get(['id', 'title']);

        $allTags = Blog::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        $blog = new Blog();

        return view('admin.blogs.create', compact('profiles', 'products', 'blog', 'categories', 'allTags'));
    }

    /**
     * Store a newly created blog in storage.
     */
    public function store(StoreUpdateBlogRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $slug = generateUniqueSlug(Blog::class, $validated['title']);

        $blog = Blog::query()->create([
            'author_profile_id' => $validated['author_profile_id'] ?? null,
            'category_ids' => $validated['category_ids'] ?? [],
            'title' => $validated['title'],
            'slug' => $slug,
            'short_description' => $validated['short_description'] ?? null,
            'content' => $validated['content'],
            'product_ids' => $validated['product_ids'] ?? [],
            'tags' => $validated['tags'] ?? [],
            'status' => $validated['status'],
            'is_editors_pick' => (bool) ($validated['is_editors_pick'] ?? false),
            'published_at' => $validated['published_at'] ?? ($validated['status'] === BlogStatusEnum::PUBLISHED->value ? now() : null),
            'metadata' => $validated['metadata'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $blog->addMediaFromRequest('image')
                ->toMediaCollection(SpatieMediaCollectionName::BLOG_IMAGE());
        }

        if ($request->ajax() || $request->expectsJson() || $request->wantsJson()) {
            return ApiResponseType::sendJsonResponse(
                true,
                __('labels.blog_created_successfully'),
                ['redirect_url' => route('admin.blogs.index')]
            );
        }

        return redirect()->route('admin.blogs.index')
            ->with('success', __('labels.blog_created_successfully'));
    }

    /**
     * Show form for editing the specified blog.
     */
    public function edit(Blog $blog): View
    {
        $blog = $blog->exists ? $blog : Blog::findOrFail(request()->route('blog'));
        $profiles = WatchBuyProfile::query()
            ->where('profile_type', WatchBuyProfileTypeEnum::PLATFORM)
            ->where('status', 'active')
            ->get();

        $assignedCatIds = array_values(array_filter(array_map('intval', (array) ($blog->category_ids ?? []))));
        $categories = BlogCategory::query()->where(function ($q) use ($assignedCatIds): void {
            $q->where('status', true);
            if (! empty($assignedCatIds)) {
                $q->orWhereIn('id', $assignedCatIds);
            }
        })->orderBy('sort_order', 'asc')->orderBy('name')->get();

        $selectedProductIds = (array) ($blog->product_ids ?? []);
        $products = Product::query()
            ->where('status', 'active')
            ->orWhereIn('id', $selectedProductIds)
            ->orderBy('title')
            ->take(200)
            ->get(['id', 'title']);

        $allTags = Blog::query()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('admin.blogs.edit', compact('blog', 'profiles', 'products', 'categories', 'allTags'));
    }

    /**
     * Update the specified blog in storage.
     */
    public function update(StoreUpdateBlogRequest $request, Blog $blog): RedirectResponse|JsonResponse
    {
        $blog = $blog->exists ? $blog : Blog::findOrFail(request()->route('blog'));
        $validated = $request->validated();
        $slug = $blog->title !== $validated['title']
            ? generateUniqueSlug(Blog::class, $validated['title'], id: $blog->id)
            : $blog->slug;

        $blog->update([
            'author_profile_id' => $validated['author_profile_id'] ?? null,
            'category_ids' => $validated['category_ids'] ?? [],
            'title' => $validated['title'],
            'slug' => $slug,
            'short_description' => $validated['short_description'] ?? null,
            'content' => $validated['content'],
            'product_ids' => $validated['product_ids'] ?? [],
            'tags' => $validated['tags'] ?? [],
            'status' => $validated['status'],
            'is_editors_pick' => (bool) ($validated['is_editors_pick'] ?? false),
            'published_at' => $validated['published_at'] ?? ($validated['status'] === BlogStatusEnum::PUBLISHED->value ? ($blog->published_at ?? now()) : null),
            'metadata' => $validated['metadata'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $blog->clearMediaCollection(SpatieMediaCollectionName::BLOG_IMAGE());
            $blog->addMediaFromRequest('image')
                ->toMediaCollection(SpatieMediaCollectionName::BLOG_IMAGE());
        }

        if ($request->ajax() || $request->expectsJson() || $request->wantsJson()) {
            return ApiResponseType::sendJsonResponse(
                true,
                __('labels.blog_updated_successfully'),
                ['redirect_url' => route('admin.blogs.index')]
            );
        }

        return redirect()->route('admin.blogs.index')
            ->with('success', __('labels.blog_updated_successfully'));
    }

    /**
     * Remove the specified blog from storage.
     */
    public function destroy(Blog $blog): JsonResponse
    {
        $blog = $blog->exists ? $blog : Blog::findOrFail(request()->route('blog'));
        $blog->delete();

        return ApiResponseType::sendJsonResponse(true, __('labels.blog_deleted_successfully'), []);
    }

    /**
     * Toggle draft / published status of the blog.
     */
    public function toggleStatus(Blog $blog): JsonResponse
    {
        $blog = $blog->exists ? $blog : Blog::findOrFail(request()->route('blog'));
        $newStatus = $blog->status === BlogStatusEnum::PUBLISHED
            ? BlogStatusEnum::DRAFT
            : BlogStatusEnum::PUBLISHED;

        $blog->update([
            'status' => $newStatus,
            'published_at' => $newStatus === BlogStatusEnum::PUBLISHED ? ($blog->published_at ?? now()) : $blog->published_at,
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blog_status_updated_successfully'),
            ['status' => $newStatus->value]
        );
    }

    /**
     * Toggle Editor's Pick flag of the blog.
     */
    public function toggleEditorsPick(Blog $blog): JsonResponse
    {
        $blog = $blog->exists ? $blog : Blog::findOrFail(request()->route('blog'));
        $newVal = ! (bool) $blog->is_editors_pick;
        $blog->update(['is_editors_pick' => $newVal]);

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blog_editors_pick_status_updated'),
            ['is_editors_pick' => $newVal]
        );
    }
}
