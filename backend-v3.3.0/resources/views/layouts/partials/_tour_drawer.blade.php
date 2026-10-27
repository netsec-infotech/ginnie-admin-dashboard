@php
    $currentPanel = request()->segment(1) ?: 'admin';
    $panelTours = $currentPanel === 'seller'
        ? app(\App\Services\SellerTourCatalog::class)->forUser(auth()->user())
        : config('tours.' . $currentPanel, []);

    $resolveTourText = static function ($value): string {
        if (! is_string($value) || $value === '') {
            return '';
        }
        $translated = __($value);

        return is_string($translated) ? $translated : $value;
    };

    // Group tours by their group key (fallback to 'General')
    $groupedTours = [];
    foreach ($panelTours as $key => $tour) {
        $groupName = $resolveTourText($tour['group'] ?? 'General') ?: 'General';
        $groupedTours[$groupName][$key] = $tour;
    }
@endphp

<!-- Offcanvas Tour Drawer -->
<div class="offcanvas offcanvas-end m-0 end-0 rounded-0" tabindex="-1" id="offcanvasTourDrawer"
    aria-labelledby="offcanvasTourDrawerLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="offcanvasTourDrawerLabel">
            <i class="ti ti-info-circle text-primary fs-2"></i> {{ __('labels.feature_walkthroughs') }}
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="{{ __('labels.close') }}"></button>
    </div>
    <div class="offcanvas-body p-3">
        <p class="text-muted small mb-3">{{ __('labels.feature_walkthroughs_description') }}</p>

        @if (count($panelTours) > 0)
            <div class="mb-3">
                <div class="input-icon">
                    <span class="input-icon-addon">
                        <i class="ti ti-search fs-2"></i>
                    </span>
                    <input type="text" id="tourSearchInput" class="form-control"
                        placeholder="{{ __('labels.search_walkthroughs') }}" autocomplete="off">
                </div>
            </div>
        @endif

        <div id="tourCardsContainer">
            @if (count($groupedTours) > 0)
                <div class="accordion accordion-flush" id="tourAccordion">
                    @php $groupIndex = 0; @endphp
                    @foreach ($groupedTours as $groupTitle => $toursInGroup)
                        @php
                            $groupIndex++;
                            $collapseId = 'tourGroupCollapse_' . $groupIndex;
                            $headingId = 'tourGroupHeading_' . $groupIndex;
                            // All groups collapsed by default
                            $isOpenByDefault = false;
                        @endphp
                        <div class="accordion-item border rounded-3 mb-3 overflow-hidden tour-group-item"
                            data-group-name="{{ strtolower((string) $groupTitle) }}">
                            <div class="accordion-header" id="{{ $headingId }}">
                                <button
                                    class="accordion-button py-3 shadow-none {{ $isOpenByDefault ? '' : 'collapsed' }}"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                                    aria-expanded="{{ $isOpenByDefault ? 'true' : 'false' }}"
                                    aria-controls="{{ $collapseId }}">
                                    <span class="badge bg-primary-lt me-2 tour-group-number">{{ $groupIndex }}</span>
                                    <span class="tour-group-title">{{ $groupTitle }}</span>
                                    <span class="badge bg-secondary-lt ms-2 tour-group-count">{{ count($toursInGroup) }}</span>
                                </button>
                            </div>
                            <div id="{{ $collapseId }}"
                                class="accordion-collapse collapse {{ $isOpenByDefault ? 'show' : '' }}"
                                data-bs-parent="#tourAccordion"
                                aria-labelledby="{{ $headingId }}">
                                <div class="accordion-body p-2">
                                    @foreach ($toursInGroup as $key => $tour)
                                        @php
                                            $tourTitle = $resolveTourText($tour['title'] ?? '');
                                            $tourDesc = $resolveTourText($tour['description'] ?? '');
                                        @endphp
                                        <div class="card mb-2 border tour-card shadow-none bg-body"
                                            data-tour-title="{{ strtolower((string) $tourTitle) }}"
                                            data-tour-description="{{ strtolower((string) $tourDesc) }}">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="avatar avatar-sm bg-primary-lt text-primary flex-shrink-0">
                                                        <i class="{{ $tour['icon'] ?? 'ti ti-info-circle' }}"></i>
                                                    </div>
                                                    <div class="flex-fill">
                                                        <h4 class="mb-1 fs-4">{{ $tourTitle }}
                                                        </h4>
                                                        <p class="text-secondary small mb-2">{{ $tourDesc }}
                                                        </p>
                                                        <button type="button" data-tour-start="{{ $tour['action'] ?? $key }}"
                                                            class="btn btn-primary btn-sm"
                                                            onclick="TourManager.startTour('{{ $tour['action'] ?? $key }}')">
                                                            <i class="ti ti-player-play me-1"></i> {{ __('labels.start_tour') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center text-muted py-4">
                    <i class="ti ti-compass fs-1 d-block mb-2 text-secondary"></i>
                    <p class="mb-0 small">{{ __('labels.no_walkthroughs_available') }}</p>
                </div>
            @endif

            <div id="tourNoResults" class="text-center text-muted py-4 d-none">
                <i class="ti ti-search-off fs-1 d-block mb-2 text-secondary"></i>
                <p class="mb-0 small">{{ __('labels.no_walkthroughs_found') }}</p>
            </div>
        </div>
    </div>
</div>

@if ($currentPanel === 'seller')
    <script type="application/json" id="seller-tour-catalog">{!! json_encode([
        'userId' => (string) auth()->id(),
        'route' => request()->route()?->getName(),
        'tours' => $panelTours,
        'text' => collect(['next', 'back', 'finish', 'close', 'continue', 'select_record', 'select_edit', 'select_configuration', 'select_permissions', 'pos_refund', 'unavailable', 'missing', 'readonly', 'waiting', 'empty', 'progress', 'offer', 'profile_required'])
            ->mapWithKeys(fn ($key) => [$key => __('labels.seller_tours.'.$key)])->all(),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const drawer = document.getElementById('offcanvasTourDrawer');
        if (drawer && drawer.parentElement !== document.body) {
            document.body.appendChild(drawer);
        }
        const components = window.bootstrap || window.tabler;
        const searchInput = document.getElementById('tourSearchInput');
        const tourCards = document.querySelectorAll('.tour-card');
        const tourGroupItems = document.querySelectorAll('.tour-group-item');
        const noResultsEl = document.getElementById('tourNoResults');

        // Helper function to update header button state & icon
        function setGroupState(groupItem, isOpen) {
            const headerBtn = groupItem.querySelector('.accordion-button');
            if (headerBtn) {
                if (isOpen) {
                    headerBtn.classList.remove('collapsed');
                    headerBtn.setAttribute('aria-expanded', 'true');
                } else {
                    headerBtn.classList.add('collapsed');
                    headerBtn.setAttribute('aria-expanded', 'false');
                }
            }
        }

        // Toggle plus / minus icon state on standard Bootstrap collapse show/hide
        const collapseElements = document.querySelectorAll('#tourAccordion .accordion-collapse');
        collapseElements.forEach(function(collapseEl) {
            const groupItem = collapseEl.closest('.tour-group-item');
            collapseEl.addEventListener('show.bs.collapse', function() {
                if (groupItem) setGroupState(groupItem, true);
            });

            collapseEl.addEventListener('hide.bs.collapse', function() {
                if (groupItem) setGroupState(groupItem, false);
            });
        });

        // Search functionality
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                let totalVisibleCount = 0;
                let expandedMatch = false;

                tourGroupItems.forEach(function(groupItem) {
                    const groupName = groupItem.getAttribute('data-group-name') || '';
                    const groupMatches = query && groupName.includes(query);
                    const cardsInGroup = groupItem.querySelectorAll('.tour-card');
                    let groupVisibleCards = 0;

                    cardsInGroup.forEach(function(card) {
                        const title = card.getAttribute('data-tour-title') || '';
                        const desc = card.getAttribute('data-tour-description') || '';

                        if (!query || groupMatches || title.includes(query) || desc.includes(query)) {
                            card.classList.remove('d-none');
                            groupVisibleCards++;
                            totalVisibleCount++;
                        } else {
                            card.classList.add('d-none');
                        }
                    });

                    const collapseEl = groupItem.querySelector('.accordion-collapse');
                    const countBadge = groupItem.querySelector('.tour-group-count');
                    if (countBadge) countBadge.textContent = groupVisibleCards;

                    if (groupVisibleCards > 0) {
                        groupItem.classList.remove('d-none');

                        if (collapseEl) {
                            if (query && !expandedMatch) {
                                expandedMatch = true;
                                // Expand when searching
                                if (components?.Collapse) {
                                    const bsCollapse = components.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                                    bsCollapse.show();
                                } else {
                                    collapseEl.classList.add('show');
                                }
                                setGroupState(groupItem, true);
                            } else {
                                // Collapse back when search is cleared
                                if (components?.Collapse) {
                                    const bsCollapse = components.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                                    bsCollapse.hide();
                                } else {
                                    collapseEl.classList.remove('show');
                                }
                                setGroupState(groupItem, false);
                            }
                        }
                    } else {
                        groupItem.classList.add('d-none');
                        if (collapseEl) {
                            components?.Collapse?.getOrCreateInstance(collapseEl, { toggle: false }).hide();
                            collapseEl.classList.remove('show');
                            setGroupState(groupItem, false);
                        }
                    }
                });

                if (noResultsEl) {
                    if (totalVisibleCount === 0 && tourCards.length > 0) {
                        noResultsEl.classList.remove('d-none');
                    } else {
                        noResultsEl.classList.add('d-none');
                    }
                }
            });
        }
    });
</script>
