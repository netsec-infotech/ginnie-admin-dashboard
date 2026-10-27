@extends('vendor.installer.layouts.master')

@section('title', trans('installer_messages.permissions.title'))

@section('style')
    <style>
        .permission-help {
            margin-top: 24px;
            text-align: left;
        }
        .permission-help__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 12px;
        }
        .permission-help__platform {
            padding: 14px;
            margin-bottom: 12px;
            border: 1px solid #e3e8ef;
            border-radius: 10px;
            background: #f8fafc;
        }
        .permission-help__platform strong {
            display: block;
            margin-bottom: 8px;
        }
        .permission-help__command {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .permission-help__command + .permission-help__command {
            margin-top: 8px;
        }
        .permission-help__command code {
            flex: 1;
            padding: 9px 11px;
            overflow-x: auto;
            color: #d8dee9;
            background: #18212f;
            border-radius: 7px;
            white-space: nowrap;
        }
        .permission-help__copy {
            flex: 0 0 auto;
        }
        .permission-help__note {
            color: #5f6673;
            font-size: 0.8125rem;
        }
    </style>
@endsection

@section('container')
    @if (isset($permissions['errors']))
        <div class="alert alert-danger">Please fix the below error and the click  {{ trans('installer_messages.checkPermissionAgain') }}</div>
    @endif
    <ul class="list">
        @foreach($permissions['permissions'] as $permission)
        <li class="list__item list__item--permissions {{ $permission['isSet'] ? 'success' : 'error' }}">
            {{ $permission['folder'] }}<span>{{ $permission['permission'] }}</span>
        </li>
        @endforeach
    </ul>

    <section class="permission-help">
        <div class="permission-help__header">
            <h3>Permission Commands</h3>
            <a href="{{ config('installer.branding.guide_url') }}" target="_blank" rel="noopener noreferrer">Open Installation Guide</a>
        </div>
        <p class="permission-help__note">Run commands from the project root. Confirm your web-server user before applying ownership commands.</p>

        @foreach(config('installer.permission_commands', []) as $platform)
            <div class="permission-help__platform">
                <strong>{{ $platform['label'] }}</strong>
                @foreach($platform['commands'] as $command)
                    <div class="permission-help__command">
                        <code>{{ $command }}</code>
                        <button type="button" class="btn btn-sm btn-outline-secondary permission-help__copy" data-command="{{ $command }}">Copy</button>
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>

    <div class="buttons">
        @if ( ! isset($permissions['errors']))
            <a class="btn btn-primary" href="{{ route('LaravelInstaller::license') }}">
                {{ trans('installer_messages.next') }}
            </a>
        @else
            <a class="btn btn-primary" href="javascript:window.location.href='';">
                {{ trans('installer_messages.checkPermissionAgain') }}
            </a>
        @endif
    </div>

@stop

@section('scripts')
    <script>
        document.querySelectorAll('.permission-help__copy').forEach(button => {
            button.addEventListener('click', async () => {
                const label = button.textContent;

                try {
                    await navigator.clipboard.writeText(button.dataset.command);
                    button.textContent = 'Copied';
                } catch (error) {
                    button.textContent = 'Copy failed';
                }

                window.setTimeout(() => {
                    button.textContent = label;
                }, 1500);
            });
        });
    </script>
@endsection
