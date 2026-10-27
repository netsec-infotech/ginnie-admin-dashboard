<script>
    (function () {
        try {
            if (localStorage.getItem('sidebar_collapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    })();
</script>
<!-- BEGIN GLOBAL MANDATORY STYLES -->
{{-- tom select --}}
<link href="{{ asset('assets/vendor/tom-select/css/tom-select.bootstrap5.min.css') }}" rel="stylesheet" />

@php
    $rtlTheme = ($textDirection ?? 'ltr') === 'rtl' ? '.rtl' : '';
@endphp
<link href="{{ asset("assets/theme/css/tabler{$rtlTheme}.css") }}" rel="stylesheet" />
<link href="{{ asset("assets/theme/css/tabler-flags{$rtlTheme}.css") }}" rel="stylesheet" />
<link href="{{ asset("assets/theme/css/tabler-socials{$rtlTheme}.css") }}" rel="stylesheet" />
<link href="{{ asset("assets/theme/css/tabler-payments{$rtlTheme}.css") }}" rel="stylesheet" />
<link href="{{ asset("assets/theme/css/tabler-vendors{$rtlTheme}.css") }}" rel="stylesheet" />
<link href="{{ asset("assets/theme/css/tabler-marketing{$rtlTheme}.css") }}" rel="stylesheet" />
<link href="{{ asset("assets/theme/css/tabler-themes{$rtlTheme}.css") }}" rel="stylesheet" />
<link href="{{ hyperAsset('assets/theme/css/style.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/vendor/filepond/css/filepond.css') }}" rel="stylesheet" />

<link rel="stylesheet" href="{{ asset('assets/theme/css/tabler-icons.min.css') }}" />

<link href="{{ asset('assets/vendor/filepond/css/filepond-plugin-image-preview.css') }}" rel="stylesheet" />

{{-- sweet alert --}}
<link href="{{ asset('assets/vendor/sweetalert/css/sweetalert2.min.css') }}" rel="stylesheet" />

{{-- datatable --}}
<link href="{{ asset('assets/vendor/datatable/css/dataTables.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/vendor/datatable/css/buttons.bootstrap5.css') }}" rel="stylesheet" />


<link href="{{ hyperAsset('assets/admin/css/custom.css') }}" rel="stylesheet" />
<link href="{{ hyperAsset('assets/css/custom.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/css/global-search.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/vendor/driverjs/driver.css') }}" rel="stylesheet" />

@stack('styles')
<style>
    @import url("https://rsms.me/inter/inter.css");
</style>

{{-- Google Analytics script for Admin and Seller Panel (only rendered when configured in .env) --}}
@php
    $gaSellerId = config('services.google_analytics.seller_id');
    $gaAdminId = config('services.google_analytics.admin_id');
    $gaActiveId = request()->is('seller*') ? $gaSellerId : $gaAdminId;
@endphp

@if (!empty($gaActiveId))
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaActiveId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', '{{ $gaActiveId }}');
    </script>
@endif

{{-- Microsoft Clarity script for Admin and Seller Panel (only rendered when configured in .env) --}}
@php
    $claritySellerId = config('services.clarity.seller_id');
    $clarityAdminId = config('services.clarity.admin_id');
    $clarityActiveId = request()->is('seller*') ? $claritySellerId : $clarityAdminId;
@endphp

@if (!empty($clarityActiveId))
    <script type="text/javascript">
        (function(c,l,a,r,i,t,y){
            c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
            t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
            y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window, document, "clarity", "script", "{{ $clarityActiveId }}");
    </script>
@endif
