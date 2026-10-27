@php
    $routePanel = request()->segment(1);
    $panelName = in_array($routePanel, ['admin', 'seller'], true) ? $routePanel : ($panel ?? null);
    $isPanel = in_array($panelName, ['admin', 'seller'], true);
    $loginRoute = $isPanel && Route::has($panelName.'.login.post') ? route($panelName.'.login.post') : null;
    $reloginRoute = $isPanel && Route::has($panelName.'.auth.relogin') ? route($panelName.'.auth.relogin') : null;
    $checkRoute = $isPanel && Route::has($panelName.'.auth.check') ? route($panelName.'.auth.check') : null;
@endphp

@if($isPanel && $loginRoute && $reloginRoute && $checkRoute)
    <div class="modal fade" id="panel-relogin-modal" tabindex="-1" aria-labelledby="panel-relogin-title" aria-hidden="true"
         data-bs-backdrop="static" data-bs-keyboard="false"
         data-login-url="{{ $loginRoute }}"
         data-relogin-url="{{ $reloginRoute }}"
         data-check-url="{{ $checkRoute }}">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 bg-transparent shadow-none">
                <div class="card card-md mb-0">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h5 class="modal-title h2 mb-2" id="panel-relogin-title">{{ __('labels.session_expired') }}</h5>
                            <p class="text-muted mb-0">{{ __('labels.sign_in_again_without_losing_page') }}</p>
                        </div>
                        @include('components.panel-login-form', [
                            'formId' => 'panel-relogin-form',
                            'action' => $loginRoute,
                            'forgotPasswordRoute' => route($panelName.'.password.request'),
                            'title' => $panelName === 'seller' ? __('labels.login_to_seller_console') : __('labels.login_to_your_account'),
                            'passwordInputId' => 'panel-relogin-password',
                            'passwordToggleId' => 'panel-relogin-password-toggle',
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
