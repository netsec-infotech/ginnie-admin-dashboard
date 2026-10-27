{{-- Global Search Command Palette Modal Component --}}
<div class="modal fade" id="globalSearchModal" tabindex="-1" aria-labelledby="globalSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-top">
        <div class="modal-content shadow-none">
            <!-- Search Header Bar -->
            <div class="search-input-group">
                <i class="ti ti-search search-icon"></i>
                <input type="text" id="globalSearchInput" class="search-input" placeholder="{{ __('labels.global_search_placeholder') }}" autocomplete="off">
                <button type="button" id="globalSearchClear" class="search-clear-btn" title="{{ __('labels.clear_search') }}">
                    <i class="ti ti-x"></i>
                </button>
            </div>

            <!-- Filter Category Tabs -->
            <div id="globalSearchFilters" class="search-filter-bar" style="display: none;">
                <!-- Filter chips populated via JS -->
            </div>

            <!-- Search Results Container -->
            <div id="globalSearchResults" class="search-results-container">
                <!-- Results populated via JS -->
            </div>

            <!-- Footer Keyboard Navigation Hints -->
            <div class="search-footer-hints">
                <div class="d-flex align-items-center gap-2">
                    <span><kbd>↑</kbd> <kbd>↓</kbd> {{ __('labels.navigate') }}</span>
                    <span><kbd>↵</kbd> {{ __('labels.select') }}</span>
                    <span><kbd>ESC</kbd> {{ __('labels.close') }}</span>
                </div>
                <div>
                    <span class="badge bg-primary-lt">{{ __('labels.command_palette') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
