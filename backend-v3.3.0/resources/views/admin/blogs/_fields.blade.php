@php
    use App\Enums\Blog\BlogStatusEnum;
    $selectedProductIds = (array) old('product_ids', $blog->product_ids ?? []);
    $selectedCategoryIds = array_map('intval', (array) old('category_ids', $blog->category_ids ?? []));
    $currentTags = (array) old('tags', $blog->tags ?? []);
    $availableTags = array_values(array_unique(array_merge($allTags ?? [], $currentTags)));
@endphp

<div class="row g-3">
    <!-- Basic Information Card -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.general_information') }}</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required">{{ __('labels.title') }}</label>
                    <input type="text" class="form-control" name="title"
                           value="{{ old('title', $blog->title ?? '') }}"
                           placeholder="{{ __('labels.enter_title') }}" required>
                    @error('title')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('labels.short_description') }}</label>
                    <textarea class="form-control" name="short_description" rows="3"
                              placeholder="{{ __('labels.enter_short_description') }}">{{ old('short_description', $blog->short_description ?? '') }}</textarea>
                    @error('short_description')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label required">{{ __('labels.content') }}</label>
                    <textarea class="form-control hugerte-mytextarea" name="content" rows="12"
                              placeholder="{{ __('labels.enter_content') }}">{{ old('content', $blog->content ?? '') }}</textarea>
                    @error('content')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- SEO Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.seo_information') }}</h3>
            </div>
            <div class="card-body">
                <x-seo-fields :metadata="old('metadata', $blog->metadata ?? [])" />
            </div>
        </div>
    </div>

    <!-- Publishing & Media Sidebar Card -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.publishing') }}</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label required">{{ __('labels.status') }}</label>
                    <select class="form-select" name="status" required>
                        @foreach(BlogStatusEnum::cases() as $status)
                            <option value="{{ $status->value }}"
                                {{ old('status', $blog->status?->value ?? $blog->status) === $status->value ? 'selected' : '' }}>
                                {{ ucfirst($status->value) }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-check form-switch cursor-pointer mb-1">
                        <input class="form-check-input" type="checkbox" name="is_editors_pick" value="1"
                               {{ old('is_editors_pick', $blog->is_editors_pick) ? 'checked' : '' }}>
                        <span class="form-check-label fw-bold">{{ __('labels.editors_pick') }}</span>
                    </label>
                    <small class="form-hint text-muted">{{ __('labels.editors_pick_help') }}</small>
                    @error('is_editors_pick')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('labels.blog_categories') }}</label>
                    <select class="form-select" name="category_ids[]" id="blog-category-ids" multiple placeholder="{{ __('labels.select_categories') }}">
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}"
                                {{ in_array((int) $category->id, $selectedCategoryIds, true) ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_ids')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('labels.published_at') }}</label>
                    <input type="datetime-local" class="form-control" name="published_at"
                           value="{{ old('published_at', $blog->published_at ? $blog->published_at->format('Y-m-d\TH:i') : '') }}">
                    <small class="form-hint text-muted">{{ __('labels.leave_empty_for_instant_publish') }}</small>
                    @error('published_at')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label required">{{ __('labels.author_profile') }}</label>
                    <select class="form-select" name="author_profile_id" required>
                        <option value="">{{ __('labels.select_author_profile') }}</option>
                        @foreach($profiles as $profile)
                            <option value="{{ $profile->id }}"
                                {{ (int) old('author_profile_id', $blog->author_profile_id) === (int) $profile->id ? 'selected' : '' }}>
                                @ {{ $profile->username }} ({{ $profile->bio ? Str::limit($profile->bio, 30) : 'Platform Profile' }})
                            </option>
                        @endforeach
                    </select>
                    @error('author_profile_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Tags Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.tags') }}</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('labels.tags') }}</label>
                    <select class="form-select" id="blog-tags" name="tags[]" multiple placeholder="{{ __('labels.enter_or_select_tags') }}">
                        @foreach($availableTags as $tag)
                            <option value="{{ $tag }}" {{ in_array($tag, $currentTags) ? 'selected' : '' }}>
                                {{ $tag }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-hint text-muted">{{ __('labels.tags_input_hint') }}</small>
                    @error('tags')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.image') }}</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    @if(!empty($blog->image))
                        <div class="mb-2">
                            <img src="{{ $blog->image }}" class="rounded img-fluid border" style="max-height: 180px; width: 100%; object-fit: cover;">
                        </div>
                    @endif
                    <input type="file" class="form-control" name="image" accept="image/*">
                    <small class="form-hint text-muted">{{ __('labels.allowed_image_types') }}: jpg, png, webp (max 5MB)</small>
                    @error('image')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ __('labels.linked_products') }}</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('labels.select_linked_products') }}</label>
                    <select class="form-select tom-select" id="blog-product-ids" name="product_ids[]" multiple placeholder="{{ __('labels.select_linked_products') }}">
                        @foreach($products as $product)
                            <option value="{{ $product->id }}"
                                {{ in_array($product->id, $selectedProductIds) ? 'selected' : '' }}>
                                {{ $product->getTranslation('title', 'en', false) }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-hint text-muted">{{ __('labels.products_featured_in_article') }}</small>
                    @error('product_ids')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>
