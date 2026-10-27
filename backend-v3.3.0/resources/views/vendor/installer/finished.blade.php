@extends('vendor.installer.layouts.master')

@section('title', trans('installer_messages.final.title'))

@section('style')
    <style>
        .installation-complete {
            margin: 0 auto 24px;
            max-width: 600px;
        }
        .installation-complete__header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #087f68;
            font-weight: 700;
        }
        .installation-complete__track {
            height: 14px;
            overflow: hidden;
            background: #e9ecef;
            border-radius: 999px;
        }
        .installation-complete__bar {
            width: 100%;
            height: 100%;
            background: #08a88a;
            border-radius: inherit;
        }
    </style>
@endsection

@section('container')
    <section class="installation-complete" aria-label="Installation progress">
        <div class="installation-complete__header">
            <span>Complete</span>
            <span>100%</span>
        </div>
        <div class="installation-complete__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100">
            <div class="installation-complete__bar"></div>
        </div>
    </section>

    <div class="paragraph" style="max-width: 600px; margin: 0 auto;">
        <h3 style="text-align:center; margin-bottom: 15px;">Installation Finished</h3>
        <p style="text-align:center;">Following user has been created for admin access:</p>

        <?php
        $details = isset($details) && is_array($details) ? $details : [
            'name' => null,
            'email' => null,
            'mobile' => null,
        ];
        ?>

        <ul style="list-style: none; padding: 0;">
            <li><strong>Name:</strong> {{ $details['name'] ?? ($user->name ?? '-') }}</li>
            <li><strong>Email:</strong> {{ $details['email'] ?? ($user->email ?? '-') }}</li>
            <li><strong>Mobile:</strong> {{ $details['mobile'] ?? ($user->mobile ?? '-') }}</li>
            <li id="installer-admin-password-row" hidden><strong>Password:</strong> <span id="installer-admin-password"></span></li>
        </ul>
        <p id="installer-admin-password-note" style="display:none; margin-top: 12px; color: #5f6673;">This password is shown once. Store it securely before leaving this page.</p>
    </div>
    <div class="buttons">
        <a href="{{ url('/admin') }}" class="btn btn-primary">{{ trans('installer_messages.final.exit') }}</a>
    </div>
@stop

@section('scripts')
    <script>
        (() => {
            let password = '';

            try {
                password = window.sessionStorage.getItem('installer_admin_password') || '';
                window.sessionStorage.removeItem('installer_admin_password');
            } catch (error) {
            }

            if (password === '') {
                return;
            }

            document.getElementById('installer-admin-password').textContent = password;
            document.getElementById('installer-admin-password-row').hidden = false;
            document.getElementById('installer-admin-password-note').style.display = 'block';
        })();
    </script>
@endsection
