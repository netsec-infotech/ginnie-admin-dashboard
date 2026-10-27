@php
    $formId = $formId ?? 'login-form';
    $passwordInputId = $passwordInputId ?? 'password';
    $passwordToggleId = $passwordToggleId ?? 'password-toggle';
@endphp

<h2 class="h2 text-center mb-4">{{ $title }}</h2>
<form id="{{ $formId }}" action="{{ $action }}" method="post" autocomplete="off" novalidate>
    @csrf
    <input type="hidden" name="fcm_token" value="">
    <input type="hidden" name="device_type" value="web">
    <div class="mb-3">
        <label class="form-label">{{ __('labels.email_address') }}</label>
        <input type="email" class="form-control" name="email" placeholder="your@email.com"
               autocomplete="off"/>
    </div>
    <div class="mb-2">
        <label class="form-label">
            {{ __('labels.password') }}
            <span class="form-label-description"><a href="{{ $forgotPasswordRoute }}">{{ __('labels.i_forgot_password') }}</a></span>
        </label>
        <div class="input-group input-group-flat">
            <input type="password" class="form-control" name="password"
                   placeholder="{{ __('labels.your_password') }}"
                   autocomplete="off" id="{{ $passwordInputId }}"/>
            <span class="input-group-text">
                <a href="#" class="link-secondary" title="{{ __('labels.show_password') }}" id="{{ $passwordToggleId }}"
                   data-password-toggle="#{{ $passwordInputId }}"
                   data-bs-toggle="tooltip">
                    {{ __('labels.show') }}
                </a>
            </span>
        </div>
    </div>
    <div class="mb-2">
        <label class="form-check">
            <input type="checkbox" class="form-check-input"/>
            <span class="form-check-label">{{ __('labels.remember_me_on_this_device') }}</span>
        </label>
    </div>
    <div class="form-footer">
        <button type="submit" class="btn btn-primary w-100">{{ __('labels.sign_in') }}</button>
    </div>
</form>
