<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SpatieMediaCollectionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreUpdateBlogCategoryRequest;
use App\Models\BlogCategory;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogCategoryController extends Controller
{
    /**
     * Display a listing of blog categories.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return $this->datatable($request);
        }

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'image', 'name' => 'image', 'title' => __('labels.image'), 'orderable' => false, 'searchable' => false],
            ['data' => 'name', 'name' => 'name', 'title' => __('labels.name')],
            ['data' => 'slug', 'name' => 'slug', 'title' => __('labels.slug')],
            ['data' => 'blogs_count', 'name' => 'blogs_count', 'title' => __('labels.blogs_count'), 'searchable' => false],
            ['data' => 'sort_order', 'name' => 'sort_order', 'title' => __('labels.sort_order')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.blog-categories.index', compact('columns'));
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

        $query = BlogCategory::query()->with('media');

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue): void {
                $q->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('slug', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (bool) $request->get('status'));
        }

        $recordsTotal = BlogCategory::query()->count();
        $recordsFiltered = $query->count();

        $categories = $query->orderBy('sort_order', 'asc')
            ->orderByDesc('id')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $categories->map(function (BlogCategory $category): array {
            $imageUrl = $category->image ?: asset('assets/images/default-image.png');
            $imageHtml = '<img src="' . e($imageUrl) . '" class="rounded" style="width: 48px; height: 48px; object-fit: cover;">';

            $statusBadge = $category->status
                ? '<span class="badge bg-success-lt">' . __('labels.active') . '</span>'
                : '<span class="badge bg-secondary-lt">' . __('labels.inactive') . '</span>';

            $toggleStatusUrl = route('admin.blog-categories.toggle-status', $category->id);
            $statusHtml = '
                <div class="d-inline-flex align-items-center gap-2">
                    <label class="form-check form-check-single form-switch mb-0">
                        <input class="form-check-input toggle-status cursor-pointer" type="checkbox" ' . ($category->status ? 'checked' : '') . ' data-url="' . e($toggleStatusUrl) . '">
                    </label>
                    ' . $statusBadge . '
                </div>';

            $editUrl = route('admin.blog-categories.edit', $category->id);
            $deleteUrl = route('admin.blog-categories.destroy', $category->id);

            $actionHtml = '
                <div class="d-flex justify-content-start align-items-center">
                    <button type="button" class="btn btn-outline-blue me-2 p-1 edit-category-btn" data-url="' . e($editUrl) . '" data-id="' . $category->id . '" title="' . __('labels.edit') . '">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-edit m-0">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" />
                            <path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" />
                            <path d="M16 5l3 3" />
                        </svg>
                    </button>
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
                'id' => $category->id,
                'image' => $imageHtml,
                'name' => e($category->name),
                'slug' => '<code>' . e($category->slug) . '</code>',
                'blogs_count' => '<span class="badge bg-blue-lt">' . (int) $category->blogs_count . ' ' . __('labels.blogs') . '</span>',
                'sort_order' => (int) $category->sort_order,
                'status' => $statusHtml,
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
     * Store a newly created blog category in storage.
     */
    public function store(StoreUpdateBlogCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $slug = ! empty($validated['slug'])
            ? generateUniqueSlug(BlogCategory::class, $validated['slug'])
            : generateUniqueSlug(BlogCategory::class, $validated['name']);

        $category = BlogCategory::query()->create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $category->addMediaFromRequest('image')
                ->toMediaCollection(SpatieMediaCollectionName::BLOG_CATEGORY_IMAGE());
        }

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blog_category_created_successfully'),
            $category
        );
    }

    /**
     * Return category data for edit modal.
     */
    public function edit(BlogCategory $blogCategory): JsonResponse
    {
        $blogCategory = $blogCategory->exists ? $blogCategory : BlogCategory::findOrFail(request()->route('blogCategory'));

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blog_category_retrieved_successfully'),
            [
                'id' => $blogCategory->id,
                'name' => $blogCategory->name,
                'slug' => $blogCategory->slug,
                'description' => $blogCategory->description,
                'status' => (bool) $blogCategory->status,
                'sort_order' => (int) $blogCategory->sort_order,
                'image' => $blogCategory->image,
                'metadata' => $blogCategory->metadata ?? [],
                'update_url' => route('admin.blog-categories.update', ['blogCategory' => $blogCategory->id]),
            ]
        );
    }

    /**
     * Update the specified blog category in storage.
     */
    public function update(StoreUpdateBlogCategoryRequest $request, BlogCategory $blogCategory): JsonResponse
    {
        $blogCategory = $blogCategory->exists ? $blogCategory : BlogCategory::findOrFail(request()->route('blogCategory'));
        $validated = $request->validated();
        $slug = ! empty($validated['slug']) && $validated['slug'] !== $blogCategory->slug
            ? generateUniqueSlug(BlogCategory::class, $validated['slug'], id: $blogCategory->id)
            : ($validated['name'] !== $blogCategory->name
                ? generateUniqueSlug(BlogCategory::class, $validated['name'], id: $blogCategory->id)
                : $blogCategory->slug);

        $blogCategory->update([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $blogCategory->clearMediaCollection(SpatieMediaCollectionName::BLOG_CATEGORY_IMAGE());
            $blogCategory->addMediaFromRequest('image')
                ->toMediaCollection(SpatieMediaCollectionName::BLOG_CATEGORY_IMAGE());
        }

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blog_category_updated_successfully'),
            $blogCategory
        );
    }

    /**
     * Remove the specified blog category from storage.
     */
    public function destroy(BlogCategory $blogCategory): JsonResponse
    {
        $blogCategory = $blogCategory->exists ? $blogCategory : BlogCategory::findOrFail(request()->route('blogCategory'));
        $blogCategory->delete();

        return ApiResponseType::sendJsonResponse(true, __('labels.blog_category_deleted_successfully'), []);
    }

    /**
     * Toggle active status of the category.
     */
    public function toggleStatus(BlogCategory $blogCategory): JsonResponse
    {
        $blogCategory = $blogCategory->exists ? $blogCategory : BlogCategory::findOrFail(request()->route('blogCategory'));
        $newStatus = ! $blogCategory->status;
        $blogCategory->update(['status' => $newStatus]);

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blog_category_status_updated_successfully'),
            ['status' => $newStatus]
        );
    }
}
