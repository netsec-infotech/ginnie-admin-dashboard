<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | {{ config('installer.product_name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset(config('installer.branding.favicon')) }}" sizes="16x16"/>
    <link rel="icon" type="image/png" href="{{ asset(config('installer.branding.favicon')) }}" sizes="32x32"/>
    <link rel="icon" type="image/png" href="{{ asset(config('installer.branding.favicon')) }}" sizes="96x96"/>

    <link href="{{ asset('installer/css/style.min.css') }}" rel="stylesheet"/>
    @include('layouts.partials._head')
    <script src="{{hyperAsset('assets/theme/js/tabler-theme.min.js')}}"></script>
    @yield('style')
    <style>
        .form-control {
            font-size: 0.875rem !important;
        }

        .alert-danger {
            color: var(--tblr-red);
            font-size: inherit;
            font-weight: 500;
        }

        .alert-success {
            color: var(--tblr-green);
            font-size: inherit;
            font-weight: 500;
        }

        .installer-resources {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 14px;
            padding: 18px 24px 22px;
            border-top: 1px solid #e9ecef;
            font-size: 0.8125rem;
        }

        .installer-resources a {
            color: #526273;
            text-decoration: none;
        }

        .installer-resources a:hover {
            color: #087f68;
            text-decoration: underline;
        }
    </style>

</head>
<body>
<div class="master" style="
    background-image: none;
">
    <div class="box shadow-sm border rounded-4">
        <div class="header" style="
                background: none;
            ">
            <img src="{{ asset(config('installer.branding.logo')) }}" class="mb-4" width="170px" alt="{{ config('installer.branding.company_name') }}">
            <h1 class="header__title text-dark">{{ config('installer.product_name') }}</h1>
        </div>
        <ul class="step">
            <li class="step__divider"></li>
            <li class="step__item {{ isActive('LaravelInstaller::final') }}"><i class="step__icon database"></i></li>
            <li class="step__divider"></li>
            <li class="step__item {{ isActive('LaravelInstaller::superAdmin') }}"><i class="step__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                         class="icon icon-tabler icons-tabler-outline icon-tabler-user">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/>
                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/>
                    </svg>
                </i></li>
            <li class="step__divider"></li>
            <li class="step__item {{ isActive('LaravelInstaller::environment') }}"><i class="step__icon update"></i>
            </li>
            <li class="step__divider"></li>
            <li class="step__item {{ isActive('LaravelInstaller::license') }}"><i class="step__icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-license"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 21h-9a3 3 0 0 1 -3 -3v-1h10v2a2 2 0 0 0 4 0v-14a2 2 0 1 1 2 2h-2m2 -4h-11a3 3 0 0 0 -3 3v11" /><path d="M9 7l4 0" /><path d="M9 11l4 0" /></svg></i>
            </li>
            <li class="step__divider"></li>
            <li class="step__item {{ isActive('LaravelInstaller::permissions') }}"><i
                    class="step__icon permissions"></i></li>
            <li class="step__divider"></li>
            <li class="step__item {{ isActive('LaravelInstaller::requirements') }}"><i
                    class="step__icon requirements"></i></li>
            <li class="step__divider"></li>
            <li class="step__item {{ isActive('LaravelInstaller::welcome') }}"><i class="step__icon welcome"></i></li>
            <li class="step__divider"></li>
        </ul>
        <div class="main">
            @yield('container')
        </div>
        <nav class="installer-resources" aria-label="Installer resources">
            <a href="{{ config('installer.branding.guide_url') }}" target="_blank" rel="noopener noreferrer">Installation Guide</a>
            <a href="{{ config('installer.branding.company_url') }}" target="_blank" rel="noopener noreferrer">{{ config('installer.branding.company_name') }}</a>
            <a href="{{ config('installer.branding.portfolio_url') }}" target="_blank" rel="noopener noreferrer">View Portfolio</a>
        </nav>
    </div>
</div>
</body>
@yield('scripts')
</html>
