<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogCategoryResource;
use App\Http\Resources\BlogDetailResource;
use App\Http\Resources\BlogResource;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Blogs')]
class BlogApiController extends Controller
{
    /**
     * List all active blog categories.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = BlogCategory::query()
            ->active()
            ->with('media')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blog_categories_retrieved_successfully'),
            BlogCategoryResource::collection($categories)
        );
    }

    /**
     * List all published blogs with optional filtering by category, tag, editor's pick, or keyword.
     */
    #[QueryParameter('search', description: 'Search term for blog title or summary.', type: 'string', example: 'recipes')]
    #[QueryParameter('category_id', description: 'Filter blogs by category ID.', type: 'integer', example: 1)]
    #[QueryParameter('category_slug', description: 'Filter blogs by category slug.', type: 'string', example: 'healthy-recipes')]
    #[QueryParameter('editors_pick', description: 'Filter only Editor\'s Pick spotlight blogs (1 or true).', type: 'boolean', example: true)]
    #[QueryParameter('tag', description: 'Filter blogs by tag.', type: 'string', example: 'vegan')]
    #[QueryParameter('per_page', description: 'Number of blogs per page.', type: 'integer', example: 12)]
    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->input('per_page', 12)));
        $search = trim((string) $request->input('search', ''));

        $query = Blog::published()
            ->with(['authorProfile.media', 'media']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        $isEditorsPickOnly = $request->boolean('editors_pick') || $request->input('editors_pick') === '1' || $request->boolean('is_editors_pick');
        if ($isEditorsPickOnly) {
            $query->editorsPick();
        }

        $category = null;
        if ($request->filled('category_slug')) {
            $categorySlug = trim((string) $request->input('category_slug'));
            $category = BlogCategory::where('slug', $categorySlug)->first();
            if ($category) {
                $query->whereJsonContains('category_ids', $category->id);
            } else {
                $query->whereRaw('0 = 1');
            }
        }

        if ($request->filled('category_id')) {
            $query->whereJsonContains('category_ids', (int) $request->input('category_id'));
        }

        if ($request->filled('tag')) {
            $tag = trim((string) $request->input('tag'));
            $query->whereJsonContains('tags', $tag);
        }

        $editorPickBlogs = collect();
        if (!$request->filled('search') && !$isEditorsPickOnly) {
            $editorPickQuery = Blog::published()
                ->editorsPick()
                ->with(['authorProfile.media', 'media']);

            if ($request->filled('category_slug')) {
                if ($category) {
                    $editorPickQuery->whereJsonContains('category_ids', $category->id);
                } else {
                    $editorPickQuery->whereRaw('0 = 1');
                }
            } elseif ($request->filled('category_id')) {
                $editorPickQuery->whereJsonContains('category_ids', (int) $request->input('category_id'));
            }

            if ($request->filled('tag')) {
                $editorPickQuery->whereJsonContains('tags', trim((string) $request->input('tag')));
            }

            $editorPickBlogs = $editorPickQuery->orderByDesc('published_at')
                ->orderByDesc('id')
                ->take(5)
                ->get();

            if ($editorPickBlogs->isNotEmpty()) {
                $query->whereNotIn('id', $editorPickBlogs->pluck('id'));
            }
        }

        $blogs = $query->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blogs_retrieved_successfully'),
            [
                'current_page' => $blogs->currentPage(),
                'editors_pick' => BlogResource::collection((int) $request->input('page', 1) === 1 ? $editorPickBlogs : collect()),
                'data' => BlogResource::collection($blogs->items()),
                'total' => $blogs->total(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
            ]
        );
    }

    /**
     * Get single blog details with SEO metadata, category, tags, related articles, and location-aware linked products.
     */
    #[PathParameter('slug', description: 'Unique slug of the published blog.', type: 'string', example: '10-essential-healthy-breakfast-recipes')]
    #[QueryParameter('latitude', description: 'User latitude for checking deliverability of linked products.', type: 'float', example: 23.1168454)]
    #[QueryParameter('longitude', description: 'User longitude for checking deliverability of linked products.', type: 'float', example: 70.0280567)]
    #[QueryParameter('address_id', description: 'User address ID for checking deliverability of linked products.', type: 'integer', example: 1)]
    public function show(Request $request, string $slug): JsonResponse
    {
        $blog = Blog::published()
            ->with(['authorProfile.media', 'media'])
            ->where('slug', $slug)
            ->first();

        if (! $blog) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.blog_not_found'),
                null,
                404
            );
        }

        $blog->increment('views_count');

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.blog_details_retrieved_successfully'),
            new BlogDetailResource($blog)
        );
    }
}
