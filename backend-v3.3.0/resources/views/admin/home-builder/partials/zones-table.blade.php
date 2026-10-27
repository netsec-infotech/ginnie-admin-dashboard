<div class="card mb-3" id="hb-zones-table-card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-3">
        <div>
            <h3 class="card-title m-0 d-flex align-items-center gap-2">
                <i class="ti ti-map-pins text-primary fs-2"></i>
                <span>Delivery Zone Homepage Layouts</span>
            </h3>
            <p class="card-subtitle text-muted mt-1 mb-0 fs-5">
                Customize app and web storefront layouts for specific delivery zones. Zones without custom layouts
                automatically use the <strong>Global System Layout</strong>.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-outline-primary" id="refresh-zones-table">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-tabler icons-tabler-outline icon-tabler-refresh">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                    <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                </svg>
                {{ __('labels.refresh') }}
            </button>
        </div>
    </div>
    <div class="card-body p-3">
        <x-datatable id="zones-layout-table" :columns="$columns" route="{{ route('admin.home-builder.zones-datatable') }}"
            :options="['order' => [[0, 'asc']], 'pageLength' => 10]" />
    </div>
</div>
