{{-- <script src="{{hyperAsset('assets/theme/js/bootstrap.bundle.min.js')}}" defer></script> --}}
<!-- include jQuery library -->
<script src="{{ asset('assets/vendor/axios/axios.min.js') }}"></script>
<script src="{{ asset('assets/vendor/jquery/jquery.js') }}"></script>
<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

<script src="{{ asset('assets/theme/js/tabler.min.js') }}" defer></script>
{{-- <script src="{{asset('assets/theme/js/style.min.js')}}" defer></script> --}}


<!-- include FilePond library -->
<script src="{{ asset('assets/vendor/filepond/js/filepond.min.js') }}"></script>

<!-- include FilePond plugins -->
<script src="{{ asset('assets/vendor/filepond/js/filepond-plugin-image-preview.min.js') }}"></script>
<script src="{{ asset('assets/vendor/filepond/js/filepond-plugin-file-validate-type.js') }}"></script>
<script src="{{ asset('assets/vendor/filepond/js/filepond-plugin-file-validate-size.js') }}"></script>

<!-- include FilePond jQuery adapter -->
<script src="{{ asset('assets/vendor/filepond/js/filepond.jquery.js') }}" defer></script>
<script src="{{ hyperAsset('assets/js/filepond.custom.js') }}" defer></script>

{{-- light box --}}
<script src="{{ asset('assets/vendor/lightbox/index.js') }}"></script>

{{-- tom select --}}
<script src="{{ asset('assets/vendor/tom-select/js/tom-select.complete.min.js') }}" defer></script>

{{-- sweet alert --}}
<script src="{{ asset('assets/vendor/sweetalert/js/sweetalert2.all.min.js') }}"></script>

{{-- Data Table --}}
<script src="{{ asset('assets/vendor/datatable/js/dataTables.js') }}" defer></script>
<script src="{{ asset('assets/vendor/datatable/js/dataTables.bootstrap5.js') }}" defer></script>
<script src="{{ asset('assets/vendor/datatable/js/dataTables.buttons.js') }}" defer></script>
<script src="{{ asset('assets/vendor/datatable/js/buttons.bootstrap5.js') }}" defer></script>
<script src="{{ asset('assets/vendor/datatable/js/buttons.colVis.min.js') }}" defer></script>
<script src="{{ asset('assets/vendor/datatable/js/buttons.html5.min.js') }}" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>



{{-- hugerte text editor --}}
<script src="{{ asset('assets/vendor/hugerte/hugerte.min.js') }}" defer></script>

<script src="{{ hyperAsset('assets/js/datatable.custom.js') }}" defer></script>
<script src="{{ hyperAsset('assets/js/custom.js') }}" defer></script>
<script type="module" src="{{ hyperAsset('assets/js/firebase.js') }}" defer></script>
<script src="{{ hyperAsset('assets/admin/js/custom.js') }}" defer></script>
<script src="{{ hyperAsset('assets/seller/js/custom.js') }}" defer></script>
@stack('scripts')
<script>
    const base_url = document.getElementById('base_url')?.value;
    const user_id = document.getElementById('user_id')?.value;
    const currencySymbol = document.getElementById('selected-currency-symbol')?.value;
    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });

    // Theme switching functions
    function getCurrentTheme() {
        // Check localStorage first, then data attribute, fallback to 'light'
        const storedTheme = localStorage.getItem('tabler-theme');
        const dataTheme = document.documentElement.getAttribute('data-bs-theme');
        return storedTheme || dataTheme || 'light';
    }

    function setTheme(theme) {
        // Store in localStorage
        localStorage.setItem('tabler-theme', theme);

        // Update data attribute
        if (theme === 'light') {
            document.documentElement.removeAttribute('data-bs-theme');
        } else {
            document.documentElement.setAttribute('data-bs-theme', theme);
        }

        // Update icon
        updateThemeIcon(theme);
    }

    function updateThemeIcon(theme) {
        const themeIcon = document.getElementById('theme-icon');
        if (themeIcon) {
            if (theme === 'dark') {
                themeIcon.className = 'ti ti-sun fs-2';
            } else {
                themeIcon.className = 'ti ti-moon fs-2';
            }
        }
    }

    function toggleTheme() {
        const currentTheme = getCurrentTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    }

    // Initialize theme icon on page load
    document.addEventListener('DOMContentLoaded', function() {
        const currentTheme = getCurrentTheme();
        updateThemeIcon(currentTheme);
    });
</script>

{{-- Driver.js & Guided Feature Tour Engine --}}
<script src="{{ asset('assets/vendor/driverjs/driver.min.js') }}"></script>
<script src="{{ asset('assets/js/tour-manager.js') }}?v={{ @filemtime(public_path('assets/js/tour-manager.js')) }}"></script>

{{-- Guided Tour Modules --}}
<script src="{{ asset('assets/js/tours/seller-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/seller-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/delivery-zone-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/delivery-zone-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/category-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/category-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/brand-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/brand-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/product-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/product-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/tax-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/tax-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/banner-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/banner-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/featured-section-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/featured-section-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/home-page-builder-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/home-page-builder-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/coupon-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/coupon-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/subscription-plan-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/subscription-plan-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/system-role-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/system-role-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/system-user-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/system-user-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/system-settings-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/system-settings-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/payment-settings-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/payment-settings-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/payment-withdrawal-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/payment-withdrawal-tours.js')) }}"></script>

{{-- Seller Panel Guided Tour Modules --}}
<script src="{{ asset('assets/js/tours/seller/store-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/seller/store-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/seller/subscription-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/seller/subscription-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/seller/attribute-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/seller/attribute-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/seller/product-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/seller/product-tours.js')) }}"></script>
<script src="{{ asset('assets/js/tours/seller/wallet-tours.js') }}?v={{ @filemtime(public_path('assets/js/tours/seller/wallet-tours.js')) }}"></script>
{{-- Global Command Palette & Search Script --}}
<script src="{{ asset('assets/js/global-search.js') }}"></script>
