<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\DeliveryFeedback;
use App\Models\Review;
use App\Models\SellerFeedback;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReviewManagementController extends Controller
{
    use ChecksPermissions;

    /**
     * @return array<string, mixed>
     */
    private function getContextConfig(string $context): array
    {
        return match ($context) {
            'products' => [
                'context' => 'products',
                'title' => __('labels.product_reviews'),
                'subtitle' => __('labels.product_reviews_subtitle'),
                'entity_label' => __('labels.product'),
                'page' => 'products',
                'sub_page' => 'product_reviews',
                'permission' => AdminPermissionEnum::PRODUCT_VIEW(),
                'delete_permission' => AdminPermissionEnum::PRODUCT_VIEW(),
                'route_prefix' => 'admin.products.reviews',
                'query' => Review::query()
                    ->whereNotNull('product_id')
                    ->with(['user', 'product', 'store', 'order'])
                    ->withCount([
                        'media as review_images_count' => fn ($query) => $query->where('collection_name', 'review_images'),
                    ]),
            ],
            'stores' => [
                'context' => 'stores',
                'title' => __('labels.store_reviews'),
                'subtitle' => __('labels.store_reviews_subtitle'),
                'entity_label' => __('labels.store'),
                'page' => 'stores',
                'sub_page' => 'store_reviews',
                'permission' => AdminPermissionEnum::STORE_VIEW(),
                'delete_permission' => AdminPermissionEnum::STORE_VIEW(),
                'route_prefix' => 'admin.sellers.store.reviews',
                'query' => SellerFeedback::query()
                    ->whereNotNull('store_id')
                    ->with(['user', 'store.seller.user', 'seller.user', 'order']),
            ],
            'sellers' => [
                'context' => 'sellers',
                'title' => __('labels.seller_reviews'),
                'subtitle' => __('labels.seller_reviews_subtitle'),
                'entity_label' => __('labels.seller'),
                'page' => 'sellers',
                'sub_page' => 'seller_reviews',
                'permission' => AdminPermissionEnum::SELLER_VIEW(),
                'delete_permission' => AdminPermissionEnum::SELLER_VIEW(),
                'route_prefix' => 'admin.sellers.reviews',
                'query' => SellerFeedback::query()->with(['user', 'seller.user', 'store', 'order']),
            ],
            'delivery-boys' => [
                'context' => 'delivery-boys',
                'title' => __('labels.delivery_boy_reviews'),
                'subtitle' => __('labels.delivery_boy_reviews_subtitle'),
                'entity_label' => __('labels.delivery_boy'),
                'page' => 'delivery_boy_management',
                'sub_page' => 'delivery_boy_reviews',
                'permission' => AdminPermissionEnum::DELIVERY_BOY_VIEW(),
                'delete_permission' => AdminPermissionEnum::DELIVERY_BOY_VIEW(),
                'route_prefix' => 'admin.delivery-boys.reviews',
                'query' => DeliveryFeedback::query()->with(['user', 'deliveryBoy.user', 'order']),
            ],
            default => abort(404),
        };
    }

    public function index(Request $request): View
    {
        $context = (string) $request->route('context');
        $config = $this->getContextConfig($context);
        $this->ensureAuthorized($config['permission']);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id'), 'label' => __('labels.id')],
            ['data' => 'reviewer', 'name' => 'reviewer', 'title' => __('labels.customer'), 'label' => __('labels.customer')],
            ['data' => 'subject', 'name' => 'subject', 'title' => $config['entity_label'], 'label' => $config['entity_label']],
            ['data' => 'order', 'name' => 'order', 'title' => __('labels.order'), 'label' => __('labels.order')],
            ['data' => 'rating', 'name' => 'rating', 'title' => __('labels.rating'), 'label' => __('labels.rating')],
            ['data' => 'review', 'name' => 'review', 'title' => __('labels.review'), 'label' => __('labels.review')],
        ];

        if ($context === 'products') {
            $columns[] = ['data' => 'images', 'name' => 'images', 'title' => __('labels.images'), 'label' => __('labels.images')];
        }

        $columns[] = ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at'), 'label' => __('labels.created_at')];
        $columns[] = ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'label' => __('labels.action'), 'orderable' => false, 'searchable' => false];

        $ratingOptions = collect(range(5, 1))
            ->mapWithKeys(fn (int $rating) => [$rating => $rating.' '.__('labels.star')])
            ->all();

        $dateRangeOptions = [
            'all' => __('labels.all_time'),
            '7_days' => __('labels.last_7_days'),
            '30_days' => __('labels.last_30_days'),
        ];

        $imageOptions = [
            'all' => __('labels.all'),
            'with_images' => __('labels.with_images'),
            'without_images' => __('labels.without_images'),
        ];

        return view('admin.reviews.index', compact('config', 'columns', 'ratingOptions', 'dateRangeOptions', 'imageOptions'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $context = (string) $request->route('context');
        $config = $this->getContextConfig($context);
        $this->ensureAuthorized($config['permission']);

        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = trim((string) $request->input('search.value', ''));
        $orderColumnIndex = (int) ($request->input('order.0.column', 7));
        $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = clone $config['query'];
        $totalRecords = (clone $query)->count();

        $this->applyFilters(
            $query,
            $context,
            (string) $request->input('rating_filter', ''),
            (string) $request->input('image_filter', 'all'),
            (string) $request->input('date_range_filter', 'all')
        );

        if ($searchValue !== '') {
            $this->applySearch($query, $context, $searchValue);
        }

        $filteredRecords = (clone $query)->count();
        $columnName = (string) $request->input("columns.{$orderColumnIndex}.name", 'created_at');
        $orderBy = in_array($columnName, ['id', 'rating', 'created_at'], true) ? $columnName : 'created_at';
        $query->orderBy($orderBy, $orderDirection);

        $rows = $query
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn ($review) => $this->transformRow($review, $context, $config['route_prefix'], $config['delete_permission']))
            ->values();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $rows,
        ]);
    }

    public function destroy(Request $request, int $review): JsonResponse
    {
        $context = (string) $request->route('context');
        $config = $this->getContextConfig($context);
        $this->ensureAuthorized($config['delete_permission']);

        try {
            DB::beginTransaction();

            $reviewModel = $this->findReviewForContext($context, $review);
            if (! $reviewModel) {
                DB::rollBack();

                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.review_not_found',
                    data: [],
                    status: 404
                );
            }

            $reviewModel->delete();
            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.review_deleted_successfully',
                data: []
            );
        } catch (\Throwable $exception) {
            DB::rollBack();
            Log::error('Admin review delete failed', [
                'context' => $context,
                'review_id' => $review,
                'message' => $exception->getMessage(),
            ]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.something_went_wrong',
                data: ['error' => $exception->getMessage()],
                status: 500
            );
        }
    }

    private function ensureAuthorized(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403);
    }

    private function applyFilters(
        Builder $query,
        string $context,
        string $ratingFilter,
        string $imageFilter,
        string $dateRangeFilter
    ): void {
        if ($ratingFilter !== '' && is_numeric($ratingFilter)) {
            $query->where('rating', (int) $ratingFilter);
        }

        if ($dateRangeFilter === '7_days') {
            $query->where('created_at', '>=', now()->subDays(7));
        }

        if ($dateRangeFilter === '30_days') {
            $query->where('created_at', '>=', now()->subDays(30));
        }

        if ($context !== 'products') {
            return;
        }

        if ($imageFilter === 'with_images') {
            $query->whereHas('media', fn ($mediaQuery) => $mediaQuery->where('collection_name', 'review_images'));
        }

        if ($imageFilter === 'without_images') {
            $query->whereDoesntHave('media', fn ($mediaQuery) => $mediaQuery->where('collection_name', 'review_images'));
        }
    }

    private function applySearch(Builder $query, string $context, string $searchValue): void
    {
        $query->where(function (Builder $searchQuery) use ($context, $searchValue): void {
            $searchQuery
                ->where('title', 'like', "%{$searchValue}%")
                ->orWhere('rating', 'like', "%{$searchValue}%");

            if ($context === 'products') {
                $searchQuery
                    ->orWhere('comment', 'like', "%{$searchValue}%")
                    ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->whereEnglishTextLike($searchValue))
                    ->orWhereHas('store', fn (Builder $storeQuery) => $storeQuery->where('name', 'like', "%{$searchValue}%"));
            } else {
                $searchQuery->orWhere('description', 'like', "%{$searchValue}%");
                if ($context === 'stores') {
                    $searchQuery->orWhereHas('store', fn (Builder $storeQuery) => $storeQuery->where('name', 'like', "%{$searchValue}%"));
                }
            }

            if ($context === 'sellers') {
                $searchQuery->orWhereHas('seller.user', fn (Builder $sellerQuery) => $sellerQuery->where('name', 'like', "%{$searchValue}%"));
            }

            if ($context === 'delivery-boys') {
                $searchQuery->orWhereHas('deliveryBoy.user', fn (Builder $deliveryBoyQuery) => $deliveryBoyQuery->where('name', 'like', "%{$searchValue}%"));
            }

            $searchQuery->orWhereHas('user', function (Builder $userQuery) use ($searchValue): void {
                $userQuery
                    ->where('name', 'like', "%{$searchValue}%")
                    ->orWhere('email', 'like', "%{$searchValue}%")
                    ->orWhere('mobile', 'like', "%{$searchValue}%");
            });
        });
    }

    /**
     * @return array<string, string>
     */
    private function transformRow(object $review, string $context, string $routePrefix, string $deletePermission): array
    {
        $body = in_array($context, ['stores', 'delivery-boys', 'sellers'], true)
            ? ($review->description ?? '')
            : ($review->comment ?? '');

        $subject = match ($context) {
            'products' => [
                'title' => $review->product?->getTranslation('title', 'en', false) ?? '-',
                'meta' => $review->store?->name ? __('labels.store').': '.$review->store->name : null,
                'link' => $review->product ? route('admin.products.show', $review->product->id) : null,
            ],
            'stores' => [
                'title' => $review->store?->name ?? '-',
                'meta' => __('labels.seller').': '.($review->seller?->user?->name ?? $review->store?->seller?->user?->name ?? '-'),
                'link' => $review->store ? route('admin.sellers.store.show', ['id' => $review->store->id]) : null,
            ],
            'sellers' => [
                'title' => $review->seller?->user?->name ?? '-',
                'meta' => $review->store?->name ? __('labels.store').': '.$review->store->name : null,
                'link' => $review->seller ? route('admin.sellers.show', ['id' => $review->seller->id]) : null,
            ],
            'delivery-boys' => [
                'title' => $review->deliveryBoy?->user?->name ?? '-',
                'meta' => $review->deliveryBoy?->user?->mobile ? __('labels.phone').': '.$review->deliveryBoy->user->mobile : null,
                'link' => $review->deliveryBoy ? route('admin.delivery-boys.show', ['id' => $review->deliveryBoy->id]) : null,
            ],
        };

        $images = [];
        if (method_exists($review, 'getAttribute')) {
            $images = (array) ($review->getAttribute('review_images') ?? []);
        }

        return [
            'id' => '<span class="fw-semibold">#'.e((string) $review->id).'</span>',
            'reviewer' => view('admin.reviews.partials.reviewer-cell', ['review' => $review])->render(),
            'subject' => view('admin.reviews.partials.subject-cell', ['subject' => $subject])->render(),
            'order' => $review->order
                ? '<a href="'.e(route('admin.orders.show', ['id' => $review->order->id])).'" class="text-reset fw-semibold">#'.e($review->order->uuid ?? $review->order->id).'</a>'
                : '<span class="text-secondary">-</span>',
            'rating' => view('admin.reviews.partials.rating-cell', ['rating' => (int) $review->rating])->render(),
            'review' => view('admin.reviews.partials.review-cell', [
                'title' => $review->title,
                'body' => $body,
            ])->render(),
            'images' => view('admin.reviews.partials.image-cell', ['images' => $images])->render(),
            'created_at' => '<div class="text-nowrap fw-semibold">'.e(optional($review->created_at)->format('d M Y')).'</div><div class="text-secondary small">'.e(optional($review->created_at)->format('h:i A')).'</div>',
            'action' => view('admin.reviews.partials.action-cell', [
                'canDelete' => $this->hasPermission($deletePermission),
                'deleteUrl' => route($routePrefix.'.destroy', ['review' => $review->id]),
            ])->render(),
        ];
    }

    private function findReviewForContext(string $context, int $reviewId): Review|SellerFeedback|DeliveryFeedback|null
    {
        return match ($context) {
            'products' => Review::query()->whereNotNull('product_id')->find($reviewId),
            'stores' => SellerFeedback::query()->whereNotNull('store_id')->find($reviewId),
            'sellers' => SellerFeedback::query()->find($reviewId),
            'delivery-boys' => DeliveryFeedback::query()->find($reviewId),
            default => null,
        };
    }
}
