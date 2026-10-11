@php
    $currentPanel = request()->segment(1) ?: 'admin';
    $panelTours = config('tours.' . $currentPanel, []);

    // Group tours by their group key (fallback to 'General')
    $groupedTours = [];
    foreach ($panelTours as $key => $tour) {
        $groupName = $tour['group'] ?? 'General';
        $groupedTours[$groupName][$key] = $tour;
    }
@endphp

<!-- Offcanvas Tour Drawer -->
<div class="offcanvas offcanvas-end m-0 end-0 rounded-0" tabindex="-1" id="offcanvasTourDrawer"
    aria-labelledby="offcanvasTourDrawerLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="offcanvasTourDrawerLabel">
            <i class="ti ti-info-circle text-primary fs-2"></i> Feature Walkthroughs
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3">
        <p class="text-muted small mb-3">Interactive step-by-step guides to help you navigate and master platform
            features.</p>

        @if (count($panelTours) > 0)
            <div class="mb-3">
                <div class="input-icon">
                    <span class="input-icon-addon">
                        <i class="ti ti-search fs-2"></i>
                    </span>
                    <input type="text" id="tourSearchInput" class="form-control"
                        placeholder="{{ __('labels.search') }} walkthroughs..." autocomplete="off">
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
                            data-group-name="{{ strtolower($groupTitle) }}">
                            <div class="accordion-header" id="{{ $headingId }}">
                                <button
                                    class="accordion-button bg-light-subtle py-2 px-3 d-flex align-items-center justify-content-between shadow-none {{ $isOpenByDefault ? '' : 'collapsed' }} after-none"
                                    type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                                    aria-expanded="{{ $isOpenByDefault ? 'true' : 'false' }}"
                                    aria-controls="{{ $collapseId }}"
                                    style="background-image: none !important; font-size: 0.875rem;">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-body fw-semibold">{{ $groupTitle }}</span>
                                    </div>
                                    <i
                                        class="ti {{ $isOpenByDefault ? 'ti-minus' : 'ti-plus' }} fs-4 ms-auto text-muted tour-toggle-icon"></i>
                                </button>
                            </div>
                            <div id="{{ $collapseId }}"
                                class="accordion-collapse collapse {{ $isOpenByDefault ? 'show' : '' }}"
                                data-bs-parent="#tourAccordion"
                                aria-labelledby="{{ $headingId }}">
                                <div class="accordion-body p-2 pt-3">
                                    @foreach ($toursInGroup as $key => $tour)
                                        @php
                                            $tourTitle = __($tour['title']);
                                            $tourDesc = __($tour['description']);
                                        @endphp
                                        <div class="card mb-2 border tour-card shadow-none bg-body"
                                            data-tour-title="{{ strtolower($tourTitle) }}"
                                            data-tour-description="{{ strtolower($tourDesc) }}">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start gap-3">
                                                    <div class="avatar bg-primary-lt rounded w-8 flex-shrink-0">
                                                        <i class="{{ $tour['icon'] ?? 'ti ti-info-circle' }}"></i>
                                                    </div>
                                                    <div class="flex-fill">
                                                        <h4 class="card-title mb-1 fw-bold fs-4">{{ $tourTitle }}
                                                        </h4>
                                                        <p class="card-text text-muted small mb-2">{{ $tourDesc }}
                                                        </p>
                                                        <button type="button"
                                                            class="btn btn-primary btn-sm rounded-pill px-3"
                                                            onclick="TourManager.startTour('{{ $tour['action'] ?? $key }}')">
                                                            <i class="ti ti-player-play me-1"></i> Start Tour
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
                    <p class="mb-0 small">No walkthroughs available for this panel yet.</p>
                </div>
            @endif

            <div id="tourNoResults" class="text-center text-muted py-4 d-none">
                <i class="ti ti-search-off fs-1 d-block mb-2 text-secondary"></i>
                <p class="mb-0 small">No walkthroughs found matching your search.</p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('tourSearchInput');
        const tourCards = document.querySelectorAll('.tour-card');
        const tourGroupItems = document.querySelectorAll('.tour-group-item');
        const noResultsEl = document.getElementById('tourNoResults');

        // Helper function to update header button state & icon
        function setGroupState(groupItem, isOpen) {
            const headerBtn = groupItem.querySelector('.accordion-button');
            const icon = groupItem.querySelector('.tour-toggle-icon');
            if (headerBtn) {
                if (isOpen) {
                    headerBtn.classList.remove('collapsed');
                    headerBtn.setAttribute('aria-expanded', 'true');
                } else {
                    headerBtn.classList.add('collapsed');
                    headerBtn.setAttribute('aria-expanded', 'false');
                }
            }
            if (icon) {
                if (isOpen) {
                    icon.classList.remove('ti-plus');
                    icon.classList.add('ti-minus');
                } else {
                    icon.classList.remove('ti-minus');
                    icon.classList.add('ti-plus');
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

                    if (groupVisibleCards > 0) {
                        groupItem.classList.remove('d-none');

                        if (collapseEl) {
                            if (query) {
                                // Expand when searching
                                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                                    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                                    bsCollapse.show();
                                } else {
                                    collapseEl.classList.add('show');
                                }
                                setGroupState(groupItem, true);
                            } else {
                                // Collapse back when search is cleared
                                if (typeof bootstrap !== 'undefined' && bootstrap.Collapse) {
                                    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                                    bsCollapse.hide();
                                } else {
                                    collapseEl.classList.remove('show');
                                }
                                setGroupState(groupItem, false);
                            }
                        }
                    } else {
                        groupItem.classList.add('d-none');
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
