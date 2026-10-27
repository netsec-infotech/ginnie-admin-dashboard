<div class="modal modal-blur fade" id="blog-category-modal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="blog-category-form" action="{{ route('admin.blog-categories.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="blog-category-method" value="POST">
                <input type="hidden" name="category_id" id="blog-category-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="blog-category-modal-title">{{ __('labels.create_blog_category') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label required">{{ __('labels.name') }}</label>
                                <input type="text" class="form-control" name="name" id="blog-category-name"
                                       placeholder="{{ __('labels.enter_category_name') }}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">{{ __('labels.sort_order') }}</label>
                                <input type="number" class="form-control" name="sort_order" id="blog-category-sort-order"
                                       value="0" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.slug') }}</label>
                        <input type="text" class="form-control" name="slug" id="blog-category-slug"
                               placeholder="{{ __('labels.leave_empty_for_auto_slug') }}">
                        <small class="form-hint text-muted">{{ __('labels.leave_empty_for_auto_slug') }}</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.description') }}</label>
                        <textarea class="form-control" name="description" id="blog-category-description" rows="3"
                                  placeholder="{{ __('labels.enter_description') }}"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('labels.image') }}</label>
                        <div id="blog-category-image-preview-container" class="mb-2 d-none">
                            <img id="blog-category-image-preview" src="" class="rounded border"
                                 style="max-height: 120px; object-fit: cover;">
                        </div>
                        <input type="file" class="form-control" name="image" id="blog-category-image" accept="image/*">
                        <small class="form-hint text-muted">{{ __('labels.allowed_image_types') }}: jpg, png, webp (max 5MB)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status" id="blog-category-status" value="1" checked>
                            <span class="form-check-label">{{ __('labels.active') }}</span>
                        </label>
                    </div>

                    <!-- SEO Accordion / Section -->
                    <div class="accordion" id="blog-category-seo-accordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-blog-category-seo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#collapse-blog-category-seo" aria-expanded="false" aria-controls="collapse-blog-category-seo">
                                    <i class="ti ti-seo me-2"></i> {{ __('labels.seo_information') }}
                                </button>
                            </h2>
                            <div id="collapse-blog-category-seo" class="accordion-collapse collapse" aria-labelledby="heading-blog-category-seo">
                                <div class="accordion-body">
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('labels.seo_title') }}</label>
                                        <input type="text" class="form-control" name="metadata[seo_title]" id="blog-category-seo-title"
                                               placeholder="{{ __('labels.enter_seo_title') }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('labels.seo_keywords') }}</label>
                                        <input type="text" class="form-control" name="metadata[seo_keywords][]" id="blog-category-seo-keywords"
                                               placeholder="{{ __('labels.comma_separated_keywords') }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('labels.seo_description') }}</label>
                                        <textarea class="form-control" name="metadata[seo_description]" id="blog-category-seo-description"
                                                  rows="2" placeholder="{{ __('labels.enter_seo_description') }}"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('labels.cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="blog-category-submit-btn">
                        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                        <span id="blog-category-submit-label">{{ __('labels.save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
