@php use App\Enums\PoliciesEnum; @endphp
@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['web']['sub_active'] ?? "" ])

@section('title', __('labels.web_settings'))

@section('header_data')
    @php
        $page_title = __('labels.web_settings');
        $page_pretitle = __('labels.admin') . " " . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.web_settings'), 'url' => null],
    ];
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.web_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs"/>
            </div>
        </div>
    </div>
    <!-- BEGIN PAGE BODY -->
    <div class="page-body">
        <div class="container-xl">
            <div class="row g-5">
                <div class="col-sm-2 d-none d-lg-block">
                    <div class="sticky-top">
                        <h3>{{ __('labels.menu') }}</h3>
                        <nav class="nav nav-vertical nav-pills" id="pills">
                            <a class="nav-link" href="#pills-general">{{ __('labels.general') }}</a>
                            <a class="nav-link" href="#pills-default-location">{{ __('labels.default_location') }}</a>
                            <a class="nav-link"
                               href="#pills-country-validation">{{ __('labels.country_validation') }}</a>
                            <a class="nav-link"
                               href="#pills-support">{{ __('labels.support_information') }}</a>
                            <a class="nav-link" href="#pills-seo">{{ __('labels.seo_settings') }}</a>
                            <a class="nav-link" href="#pills-social">{{ __('labels.social_media') }}</a>
                            <a class="nav-link" href="#pills-app">{{ __('labels.app_download_section') }}</a>
                            <a class="nav-link" href="#pills-features">{{ __('labels.feature_sections') }}</a>
                            <a class="nav-link" href="#pills-color-scheme">{{ __('labels.color_scheme') }}</a>
                            <a class="nav-link" href="#pills-order-now-banner">{{ __('labels.order_now_section') }}</a>
                            <a class="nav-link" href="#pills-seller-banner">{{ __('labels.seller_registration_banner') }}</a>
                            <a class="nav-link" href="#pills-policies">{{ __('labels.policy_settings') }}</a>
                            <a class="nav-link" href="#pills-pwa-manifest">{{ __('labels.pwa_manifest_settings') }}</a>
                            <a class="nav-link" href="#pills-scripts">{{ __('labels.scripts') }}</a>
                        </nav>
                    </div>
                </div>
                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">
                    <div class="row row-cards">
                        <div class="col-12">
                            <form action="{{route('admin.settings.store')}}" class="form-submit" method="post"
                                  enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="type" value="web">
                                <div class="card mb-4" id="pills-general">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.general') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.site_name') }}</label>
                                            <input type="text" class="form-control" name="siteName"
                                                   placeholder="{{ __('labels.site_name_placeholder') }}"
                                                   value="{{ $settings['siteName'] ?? '' }}" maxlength="255"
                                                   required/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.customer_web_url') }}</label>
                                            <input type="url" class="form-control" name="customerWebUrl"
                                                   placeholder="{{ __('labels.customer_web_url_placeholder') }}"
                                                   value="{{ $settings['customerWebUrl'] ?? '' }}"/>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('labels.powered_by_text') }}</label>
                                                <input type="text" class="form-control" name="poweredByText"
                                                       placeholder="{{ __('labels.powered_by_text_placeholder') }}"
                                                       value="{{ $settings['poweredByText'] ?? '' }}" maxlength="255"/>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('labels.powered_by_url') }}</label>
                                                <input type="url" class="form-control" name="poweredByUrl"
                                                       placeholder="{{ __('labels.powered_by_url_placeholder') }}"
                                                       value="{{ $settings['poweredByUrl'] ?? '' }}"/>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.site_copyright') }}</label>
                                            <input type="text" class="form-control" name="siteCopyright"
                                                   placeholder="{{ __('labels.site_copyright_placeholder') }}"
                                                   value="{{ $settings['siteCopyright'] ?? '' }}" maxlength="255"
                                                   required/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.address') }}</label>
                                            <input type="text" class="form-control" name="address"
                                                   placeholder="{{ __('labels.address_placeholder') }}"
                                                   value="{{ $settings['address'] ?? '' }}" maxlength="255"
                                                   required/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.short_description') }}</label>
                                            <textarea class="form-control" name="shortDescription"
                                                      placeholder="{{ __('labels.short_description_placeholder') }}"
                                                      maxlength="500"
                                                      required>{{ $settings['shortDescription'] ?? '' }}</textarea>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <div class="mb-3">
                                                    <div
                                                        class="form-label required">{{ __('labels.site_header_logo') }}</div>
                                                    <x-filepond_image name="siteHeaderLogo"
                                                                      imageUrl="{{ $settings['siteHeaderLogo'] ?? '' }}"/>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="mb-3">
                                                    <div
                                                        class="form-label required">{{ __('labels.site_header_dark_logo') }}</div>
                                                    <x-filepond_image name="siteHeaderDarkLogo"
                                                                      imageUrl="{{ $settings['siteHeaderDarkLogo'] ?? '' }}"/>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="mb-3">
                                                    <div
                                                        class="form-label required">{{ __('labels.site_footer_logo') }}</div>
                                                    <x-filepond_image name="siteFooterLogo"
                                                                      imageUrl="{{$settings['siteFooterLogo'] ?? ''}}"/>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="mb-3">
                                                    <div
                                                        class="form-label required">{{ __('labels.site_favicon') }}</div>
                                                    <x-filepond_image name="siteFavicon"
                                                                      imageUrl="{{ $settings['siteFavicon'] ?? '' }}"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Default Location Section -->
                                <div class="card mb-4" id="pills-default-location">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.default_location') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.default_location') }}</label>
                                            <div class="position-relative">
                                                <!-- Search field will be positioned inside the map -->
                                                <div id="place-autocomplete-card"
                                                     style="position: absolute; top: 10px; left: 10px; z-index: 1000; ">
                                                    <!-- This will be populated by JavaScript -->
                                                </div>
                                                <div id="default-location-map"
                                                     style="height: 400px; width: 100%;"></div>
                                            </div>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label
                                                        class="form-label required">{{ __('labels.latitude') }}</label>
                                                    <input type="number" class="form-control" id="default-latitude"
                                                           name="defaultLatitude" step="any"
                                                           placeholder="{{ __('labels.latitude_placeholder') }}"
                                                           value="{{ $settings['defaultLatitude'] ?? '' }}" required/>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label
                                                        class="form-label required">{{ __('labels.longitude') }}</label>
                                                    <input type="number" class="form-control" id="default-longitude"
                                                           name="defaultLongitude" step="any"
                                                           placeholder="{{ __('labels.longitude_placeholder') }}"
                                                           value="{{ $settings['defaultLongitude'] ?? '' }}" required/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Country Validation Section -->
                                <div class="card mb-4" id="pills-country-validation">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.country_validation') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                <span class="col">{{ __('labels.enable_country_validation') }}</span>
                                                <span class="col-auto">
                                                        <label class="form-check form-check-single form-switch">
                                                            <input class="form-check-input" type="checkbox"
                                                                   name="enableCountryValidation" value="1"
                                                                   {{ isset($settings['enableCountryValidation']) && $settings['enableCountryValidation'] ? 'checked' : '' }} />
                                                        </label>
                                                    </span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.allowed_countries') }}</label>
                                            <select class="form-select" id="select-countries" name="allowedCountries[]"
                                                    multiple placeholder="{{ __('labels.select_countries') }}">
                                            </select>
                                            <input type="hidden" id="selected-country"
                                                   value='@json($settings['allowedCountries'] ?? "")'>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4" id="pills-support">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.support_information') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.support_email') }}</label>
                                            <input type="email" class="form-control" name="supportEmail"
                                                   placeholder="{{ __('labels.support_email_placeholder') }}"
                                                   value="{{ $settings['supportEmail'] ?? '' }}" maxlength="255"
                                                   required/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.support_number') }}</label>
                                            <input type="tel" class="form-control" name="supportNumber"
                                                   placeholder="{{ __('labels.support_number_placeholder') }}"
                                                   value="{{ $settings['supportNumber'] ?? '' }}" maxlength="20"
                                                   required/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.google_map_key') }}</label>
                                            <input type="text" class="form-control" name="googleMapKey"
                                                   placeholder="{{ __('labels.google_map_key_placeholder') }}"
                                                   value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['googleMapKey'] ?? '****'), '****', 3, 8) : ($settings['googleMapKey'] ?? '') }}" maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.map_iframe') }}</label>
                                            <textarea class="form-control" name="mapIframe"
                                                      placeholder="{{ __('labels.map_iframe_placeholder') }}">{{ $settings['mapIframe'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-seo">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.seo_settings') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.meta_keywords') }}</label>
                                            <input type="text" class="form-control" name="metaKeywords"
                                                   placeholder="{{ __('labels.meta_keywords_placeholder') }}"
                                                   value="{{ $settings['metaKeywords'] ?? '' }}" maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.meta_description') }}</label>
                                            <textarea class="form-control" name="metaDescription"
                                                      placeholder="{{ __('labels.meta_description_placeholder') }}"
                                                      maxlength="500">{{ $settings['metaDescription'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-social">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.social_media') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.facebook_link') }}</label>
                                            <input type="text" class="form-control" name="facebookLink"
                                                   placeholder="{{ __('labels.facebook_link_placeholder') }}"
                                                   value="{{ $settings['facebookLink'] ?? '' }}" maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.instagram_link') }}</label>
                                            <input type="text" class="form-control" name="instagramLink"
                                                   placeholder="{{ __('labels.instagram_link_placeholder') }}"
                                                   value="{{ $settings['instagramLink'] ?? '' }}" maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.x_link') }}</label>
                                            <input type="text" class="form-control" name="xLink"
                                                   placeholder="{{ __('labels.x_link_placeholder') }}"
                                                   value="{{ $settings['xLink'] ?? '' }}" maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.youtube_link') }}</label>
                                            <input type="text" class="form-control" name="youtubeLink"
                                                   placeholder="{{ __('labels.youtube_link_placeholder') }}"
                                                   value="{{ $settings['youtubeLink'] ?? '' }}" maxlength="255"/>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-app">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.app_download_section') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="row">
                                                    <span
                                                        class="col">{{ __('labels.app_download_section') }}</span>
                                                <span class="col-auto">
                                                        <label class="form-check form-check-single form-switch">
                                                            <input class="form-check-input" type="checkbox"
                                                                   name="appDownloadSection" value="1" {{ isset($settings['appDownloadSection']) && $settings['appDownloadSection'] ? 'checked' : '' }} />
                                                        </label>
                                                    </span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.app_section_title') }}</label>
                                            <input type="text" class="form-control" name="appSectionTitle"
                                                   placeholder="{{ __('labels.app_section_title_placeholder') }}"
                                                   value="{{ $settings['appSectionTitle'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.app_section_tagline') }}</label>
                                            <input type="text" class="form-control" name="appSectionTagline"
                                                   placeholder="{{ __('labels.app_section_tagline_placeholder') }}"
                                                   value="{{ $settings['appSectionTagline'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.app_section_playstore_link') }}</label>
                                            <input type="url" class="form-control" name="appSectionPlaystoreLink"
                                                   placeholder="{{ __('labels.app_section_playstore_link_placeholder') }}"
                                                   value="{{ $settings['appSectionPlaystoreLink'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.app_section_appstore_link') }}</label>
                                            <input type="url" class="form-control" name="appSectionAppstoreLink"
                                                   placeholder="{{ __('labels.app_section_appstore_link_placeholder') }}"
                                                   value="{{ $settings['appSectionAppstoreLink'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.app_section_short_description') }}</label>
                                            <textarea class="form-control" name="appSectionShortDescription"
                                                      placeholder="{{ __('labels.app_section_short_description_placeholder') }}"
                                                      maxlength="500">{{ $settings['appSectionShortDescription'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-4" id="pills-features">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.feature_sections') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.shipping_feature_section') }}</label>
                                            <input type="text" class="form-control" name="shippingFeatureSection"
                                                   placeholder="{{ __('labels.shipping_feature_section_placeholder') }}"
                                                   value="{{ $settings['shippingFeatureSection'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.shipping_feature_section_title') }}</label>
                                            <input type="text" class="form-control"
                                                   name="shippingFeatureSectionTitle"
                                                   placeholder="{{ __('labels.shipping_feature_section_title_placeholder') }}"
                                                   value="{{ $settings['shippingFeatureSectionTitle'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.shipping_feature_section_description') }}</label>
                                            <textarea class="form-control" name="shippingFeatureSectionDescription"
                                                      placeholder="{{ __('labels.shipping_feature_section_description_placeholder') }}"
                                                      maxlength="500">{{ $settings['shippingFeatureSectionDescription'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.return_feature_section') }}</label>
                                            <input type="text" class="form-control" name="returnFeatureSection"
                                                   placeholder="{{ __('labels.return_feature_section_placeholder') }}"
                                                   value="{{ $settings['returnFeatureSection'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.return_feature_section_title') }}</label>
                                            <input type="text" class="form-control" name="returnFeatureSectionTitle"
                                                   placeholder="{{ __('labels.return_feature_section_title_placeholder') }}"
                                                   value="{{ $settings['returnFeatureSectionTitle'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.return_feature_section_description') }}</label>
                                            <textarea class="form-control" name="returnFeatureSectionDescription"
                                                      placeholder="{{ __('labels.return_feature_section_description_placeholder') }}"
                                                      maxlength="500">{{ $settings['returnFeatureSectionDescription'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.safety_security_feature_section') }}</label>
                                            <input type="text" class="form-control"
                                                   name="safetySecurityFeatureSection"
                                                   placeholder="{{ __('labels.safety_security_feature_section_placeholder') }}"
                                                   value="{{ $settings['safetySecurityFeatureSection'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.safety_security_feature_section_title') }}</label>
                                            <input type="text" class="form-control"
                                                   name="safetySecurityFeatureSectionTitle"
                                                   placeholder="{{ __('labels.safety_security_feature_section_title_placeholder') }}"
                                                   value="{{ $settings['safetySecurityFeatureSectionTitle'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.safety_security_feature_section_description') }}</label>
                                            <textarea class="form-control"
                                                      name="safetySecurityFeatureSectionDescription"
                                                      placeholder="{{ __('labels.safety_security_feature_section_description_placeholder') }}"
                                                      maxlength="500">{{ $settings['safetySecurityFeatureSectionDescription'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.support_feature_section') }}</label>
                                            <input type="text" class="form-control" name="supportFeatureSection"
                                                   placeholder="{{ __('labels.support_feature_section_placeholder') }}"
                                                   value="{{ $settings['supportFeatureSection'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.support_feature_section_title') }}</label>
                                            <input type="text" class="form-control"
                                                   name="supportFeatureSectionTitle"
                                                   placeholder="{{ __('labels.support_feature_section_title_placeholder') }}"
                                                   value="{{ $settings['supportFeatureSectionTitle'] ?? '' }}"
                                                   maxlength="255"/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label">{{ __('labels.support_feature_section_description') }}</label>
                                            <textarea class="form-control" name="supportFeatureSectionDescription"
                                                      placeholder="{{ __('labels.support_feature_section_description_placeholder') }}"
                                                      maxlength="500">{{ $settings['supportFeatureSectionDescription'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Color Scheme Section -->
                                <div class="card mb-4" id="pills-color-scheme">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.color_scheme') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('labels.primary_color') }}</label>
                                                <div class="input-group">
                                                    <input type="color" class="form-control form-control-color border-end-0" 
                                                           value="{{ !empty($settings['primaryColor']) ? $settings['primaryColor'] : '#3B82F6' }}" 
                                                           oninput="this.nextElementSibling.value = this.value" title="Choose color">
                                                    <input type="text" class="form-control" name="primaryColor"
                                                           placeholder="{{ __('labels.primary_color_placeholder') }}"
                                                           value="{{ $settings['primaryColor'] ?? '' }}"
                                                           oninput="if(/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(this.value)) this.previousElementSibling.value = this.value"/>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('labels.font_color') }}</label>
                                                <div class="input-group">
                                                    <input type="color" class="form-control form-control-color border-end-0" 
                                                           value="{{ !empty($settings['fontColor']) ? $settings['fontColor'] : '#FFFFFF' }}" 
                                                           oninput="this.nextElementSibling.value = this.value" title="Choose color">
                                                    <input type="text" class="form-control" name="fontColor"
                                                           placeholder="{{ __('labels.font_color_placeholder') }}"
                                                           value="{{ $settings['fontColor'] ?? '' }}"
                                                           oninput="if(/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(this.value)) this.previousElementSibling.value = this.value"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Promotional Order Now Banner Section -->
                                <div class="card mb-4" id="pills-order-now-banner">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.order_now_section') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">{{ __('labels.order_now_section_image') }}</label>
                                                <x-filepond_image name="orderNowSectionImage"
                                                                  imageUrl="{{ $settings['orderNowSectionImage'] ?? '' }}"/>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">{{ __('labels.order_now_section_tagline') }}</label>
                                                <input type="text" class="form-control" name="orderNowSectionTagline"
                                                       placeholder="{{ __('labels.order_now_section_tagline_placeholder') }}"
                                                       value="{{ $settings['orderNowSectionTagline'] ?? '' }}" maxlength="255"/>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">{{ __('labels.order_now_section_title') }}</label>
                                                <input type="text" class="form-control" name="orderNowSectionTitle"
                                                       placeholder="{{ __('labels.order_now_section_title_placeholder') }}"
                                                       value="{{ $settings['orderNowSectionTitle'] ?? '' }}" maxlength="255"/>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">{{ __('labels.order_now_section_title_highlight') }}</label>
                                                <input type="text" class="form-control" name="orderNowSectionTitleHighlight"
                                                       placeholder="{{ __('labels.order_now_section_title_highlight_placeholder') }}"
                                                       value="{{ $settings['orderNowSectionTitleHighlight'] ?? '' }}" maxlength="255"/>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.order_now_section_description') }}</label>
                                            <textarea class="form-control" name="orderNowSectionDescription"
                                                      placeholder="{{ __('labels.order_now_section_description_placeholder') }}"
                                                      maxlength="500">{{ $settings['orderNowSectionDescription'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.order_now_features') }}</label>
                                            <div id="orderNowFeaturesContainer">
                                                @php
                                                    $features = is_array($settings['orderNowFeatures'] ?? null) 
                                                        ? array_values(array_filter($settings['orderNowFeatures'])) 
                                                        : [];
                                                    if (empty($features)) {
                                                        $features = [''];
                                                    }
                                                @endphp
                                                @foreach($features as $feature)
                                                    <div class="input-group mb-2 feature-item">
                                                        <input type="text" class="form-control" name="orderNowFeatures[]"
                                                               placeholder="{{ __('labels.order_now_feature_placeholder') }}"
                                                               value="{{ $feature }}" maxlength="255"/>
                                                        <button class="btn btn-outline-danger remove-feature-btn" type="button">
                                                            {{ __('labels.remove') }}
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button type="button" class="btn btn-outline-primary mt-1" id="addFeatureBtn">
                                                + {{ __('labels.add_feature') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Seller Registration Banner Section -->
                                <div class="card mb-4" id="pills-seller-banner">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.seller_registration_banner') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">{{ __('labels.seller_banner_bg_image') }}</label>
                                                <x-filepond_image name="sellerBannerBackgroundImage"
                                                                  imageUrl="{{ $settings['sellerBannerBackgroundImage'] ?? '' }}"/>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('labels.seller_banner_icon_text') }}</label>
                                                <input type="text" class="form-control" name="sellerBannerIconText"
                                                       placeholder="{{ __('labels.seller_banner_icon_text_placeholder') }}"
                                                       value="{{ $settings['sellerBannerIconText'] ?? '' }}" maxlength="255"/>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('labels.seller_banner_title') }}</label>
                                                <input type="text" class="form-control" name="sellerBannerTitle"
                                                       placeholder="{{ __('labels.seller_banner_title_placeholder') }}"
                                                       value="{{ $settings['sellerBannerTitle'] ?? '' }}" maxlength="255"/>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('labels.seller_banner_title_highlight') }}</label>
                                                <input type="text" class="form-control" name="sellerBannerTitleHighlight"
                                                       placeholder="{{ __('labels.seller_banner_title_highlight_placeholder') }}"
                                                       value="{{ $settings['sellerBannerTitleHighlight'] ?? '' }}" maxlength="255"/>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">{{ __('labels.seller_banner_description') }}</label>
                                                <input type="text" class="form-control" name="sellerBannerDescription"
                                                       placeholder="{{ __('labels.seller_banner_description_placeholder') }}"
                                                       value="{{ $settings['sellerBannerDescription'] ?? '' }}" maxlength="500"/>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.seller_banner_features') }}</label>
                                            <div id="sellerBannerFeaturesContainer">
                                                @php
                                                    $sellerFeatures = is_array($settings['sellerBannerFeatures'] ?? null) 
                                                        ? array_values(array_filter($settings['sellerBannerFeatures'])) 
                                                        : [];
                                                    if (empty($sellerFeatures)) {
                                                        $sellerFeatures = [''];
                                                    }
                                                @endphp
                                                @foreach($sellerFeatures as $feature)
                                                    <div class="input-group mb-2 seller-feature-item">
                                                        <input type="text" class="form-control" name="sellerBannerFeatures[]"
                                                               placeholder="{{ __('labels.seller_banner_feature_placeholder') }}"
                                                               value="{{ $feature }}" maxlength="255"/>
                                                        <button class="btn btn-outline-danger remove-seller-feature-btn" type="button">
                                                            {{ __('labels.remove') }}
                                                        </button>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button type="button" class="btn btn-outline-primary mt-1" id="addSellerFeatureBtn">
                                                + {{ __('labels.add_feature') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Policy Settings Section -->
                                <div class="card mb-4" id="pills-policies">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.policy_settings') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.return_refund_policy') }}
                                                <a href="{{ route('policies.show', PoliciesEnum::REFUND_AND_RETURN()) }}"
                                                   target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                        <path
                                                            d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
                                                    </svg>
                                                </a>
                                            </label>
                                            <textarea class="hugerte-mytextarea" name="returnRefundPolicy" rows="8"
                                                      placeholder="{{ __('labels.return_refund_policy_placeholder') }}">{{ $settings['returnRefundPolicy'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.shipping_policy') }}
                                                <a href="{{ route('policies.show', PoliciesEnum::SHIPPING()) }}"
                                                   target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                        <path
                                                            d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
                                                    </svg>
                                                </a></label>
                                            <textarea class="hugerte-mytextarea" name="shippingPolicy" rows="8"
                                                      placeholder="{{ __('labels.shipping_policy_placeholder') }}">{{ $settings['shippingPolicy'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.privacy_policy') }}
                                                <a href="{{ route('policies.show', PoliciesEnum::PRIVACY()) }}"
                                                   target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                        <path
                                                            d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
                                                    </svg>
                                                </a></label>
                                            <textarea class="hugerte-mytextarea" name="privacyPolicy" rows="8"
                                                      placeholder="{{ __('labels.privacy_policy_placeholder') }}">{{ $settings['privacyPolicy'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.terms_condition') }}
                                                <a href="{{ route('policies.show', PoliciesEnum::TERMS()) }}"
                                                   target="_blank">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                         class="icon icon-tabler icons-tabler-outline icon-tabler-eye">
                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                        <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/>
                                                        <path
                                                            d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/>
                                                    </svg>
                                                </a></label>
                                            <textarea class="hugerte-mytextarea" name="termsCondition" rows="8"
                                                      placeholder="{{ __('labels.terms_condition_placeholder') }}">{{ $settings['termsCondition'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.about_us') }}</label>
                                            <textarea class="hugerte-mytextarea" name="aboutUs" rows="8"
                                                      placeholder="{{ __('labels.about_us_placeholder') }}">{{ $settings['aboutUs'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- PWA Manifest Settings Section -->
                                <div class="card mb-4" id="pills-pwa-manifest">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.pwa_manifest_settings') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label required">{{ __('labels.pwa_name') }}</label>
                                            <input type="text" class="form-control" name="pwaName"
                                                   placeholder="{{ __('labels.pwa_name_placeholder') }}"
                                                   value="{{ $settings['pwaName'] ?? '' }}" maxlength="255" required/>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label required">{{ __('labels.pwa_description') }}</label>
                                            <textarea class="form-control" name="pwaDescription"
                                                      placeholder="{{ __('labels.pwa_description_placeholder') }}"
                                                      maxlength="500"
                                                      required>{{ $settings['pwaDescription'] ?? '' }}</textarea>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div
                                                        class="form-label required">{{ __('labels.pwa_logo_192x192') }}</div>
                                                    <x-filepond_image name="pwaLogo192x192"
                                                                      imageUrl="{{ $settings['pwaLogo192x192'] ?? '' }}"
                                                                      data-accepted-file-types="image/png,image/jpeg,image/webp"
                                                                      data-max-file-size="2MB"
                                                                      data-image-crop-aspect-ratio="1:1"
                                                                      data-image-resize-target-width="192"
                                                                      data-image-resize-target-height="192"/>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div
                                                        class="form-label required">{{ __('labels.pwa_logo_512x512') }}</div>
                                                    <x-filepond_image name="pwaLogo512x512"
                                                                      imageUrl="{{ $settings['pwaLogo512x512'] ?? '' }}"
                                                                      data-accepted-file-types="image/png,image/jpeg,image/webp"
                                                                      data-max-file-size="2MB"
                                                                      data-image-crop-aspect-ratio="1:1"
                                                                      data-image-resize-target-width="512"
                                                                      data-image-resize-target-height="512"/>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <div
                                                        class="form-label required">{{ __('labels.pwa_logo_144x144') }}</div>
                                                    <x-filepond_image name="pwaLogo144x144"
                                                                      imageUrl="{{ $settings['pwaLogo144x144'] ?? '' }}"
                                                                      data-accepted-file-types="image/png,image/jpeg,image/webp"
                                                                      data-max-file-size="2MB"
                                                                      data-image-crop-aspect-ratio="1:1"
                                                                      data-image-resize-target-width="144"
                                                                      data-image-resize-target-height="144"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4" id="pills-scripts">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ __('labels.scripts') }}</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.header_script') }}</label>
                                            <textarea class="form-control" name="headerScript"
                                                      placeholder="{{ __('labels.header_script_placeholder') }}">{{ $settings['headerScript'] ?? '' }}</textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">{{ __('labels.footer_script') }}</label>
                                            <textarea class="form-control" name="footerScript"
                                                      placeholder="{{ __('labels.footer_script_placeholder') }}">{{ $settings['footerScript'] ?? '' }}</textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-end">
                                    <div class="d-flex">
                                        @can('updateSetting', [\App\Models\Setting::class, 'web'])
                                            <button type="submit"
                                                    class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>
                                        @endcan
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END PAGE BODY -->
@endsection

@push('script')
    <script async defer>(g => {
            var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__",
                m = document, b = window;
            b = b[c] || (b[c] = {});
            var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams,
                u = () => h || (h = new Promise(async (f, n) => {
                    await (a = m.createElement("script"));
                    e.set("libraries", [...r] + "");
                    for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]);
                    e.set("callback", c + ".maps." + q);
                    a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
                    d[q] = f;
                    a.onerror = () => h = n(Error(p + " could not load."));
                    a.nonce = m.querySelector("script[nonce]")?.nonce || "";
                    m.head.append(a)
                }));
            d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n))
        })
        ({key: "{{$settings['googleMapKey']}}", v: "weekly"});</script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('orderNowFeaturesContainer');
            const addBtn = document.getElementById('addFeatureBtn');
            if (container && addBtn) {
                addBtn.addEventListener('click', function () {
                    const item = document.createElement('div');
                    item.className = 'input-group mb-2 feature-item';
                    item.innerHTML = `
                        <input type="text" class="form-control" name="orderNowFeatures[]"
                               placeholder="{{ __('labels.order_now_feature_placeholder') }}"
                               maxlength="255"/>
                        <button class="btn btn-outline-danger remove-feature-btn" type="button">
                            {{ __('labels.remove') }}
                        </button>
                    `;
                    container.appendChild(item);
                });
                container.addEventListener('click', function (e) {
                    if (e.target && (e.target.classList.contains('remove-feature-btn') || e.target.closest('.remove-feature-btn'))) {
                        const item = e.target.closest('.feature-item');
                        if (item && container.querySelectorAll('.feature-item').length > 1) {
                            item.remove();
                        } else if (item) {
                            const input = item.querySelector('input');
                            if (input) input.value = '';
                        }
                    }
                });
            }

            // Seller Registration Banner Features
            const sellerContainer = document.getElementById('sellerBannerFeaturesContainer');
            const addSellerBtn = document.getElementById('addSellerFeatureBtn');
            if (sellerContainer && addSellerBtn) {
                addSellerBtn.addEventListener('click', function () {
                    const item = document.createElement('div');
                    item.className = 'input-group mb-2 seller-feature-item';
                    item.innerHTML = `
                        <input type="text" class="form-control" name="sellerBannerFeatures[]"
                               placeholder="{{ __('labels.seller_banner_feature_placeholder') }}"
                               maxlength="255"/>
                        <button class="btn btn-outline-danger remove-seller-feature-btn" type="button">
                            {{ __('labels.remove') }}
                        </button>
                    `;
                    sellerContainer.appendChild(item);
                });
                sellerContainer.addEventListener('click', function (e) {
                    if (e.target && (e.target.classList.contains('remove-seller-feature-btn') || e.target.closest('.remove-seller-feature-btn'))) {
                        const item = e.target.closest('.seller-feature-item');
                        if (item && sellerContainer.querySelectorAll('.seller-feature-item').length > 1) {
                            item.remove();
                        } else if (item) {
                            const input = item.querySelector('input');
                            if (input) input.value = '';
                        }
                    }
                });
            }
        });
    </script>
    <script src="{{hyperAsset('assets/js/settings.js')}}"></script>
@endpush
