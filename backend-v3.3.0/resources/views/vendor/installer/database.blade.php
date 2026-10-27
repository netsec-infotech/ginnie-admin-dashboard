@extends('vendor.installer.layouts.master')

@section('title', 'Install Database')

@section('style')
    <style>
        .install-progress {
            margin-top: 24px;
            text-align: left;
        }
        .install-progress__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 10px;
        }
        .install-progress__track {
            height: 14px;
            overflow: hidden;
            background: #e9ecef;
            border-radius: 999px;
        }
        .install-progress__bar {
            width: 0;
            height: 100%;
            background: #08a88a;
            border-radius: inherit;
            transition: width .25s ease;
        }
        .install-progress__message {
            margin: 10px 0;
            color: #5f6673;
        }
        .install-progress__log {
            height: 250px;
            padding: 14px;
            overflow: auto;
            list-style: none;
            color: #d8dee9;
            background: #18212f;
            border-radius: 10px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
        }
        .install-progress__log li + li {
            margin-top: 7px;
        }
        .install-progress__log .error {
            color: #ff8c8c;
        }
        .install-progress__log .success {
            color: #82e6b1;
        }
        .install-progress__reference {
            display: none;
            margin-bottom: 12px;
            color: #b42318;
            font-weight: 600;
        }
    </style>
@endsection

@section('container')
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <p class="paragraph">The database connection and administrator details are ready. Keep this page open while installation is running.</p>
    <div class="alert alert-warning">Starting installation will remove all existing tables from the selected database and create a clean database.</div>

    <form method="post" action="{{ route('LaravelInstaller::database.install') }}" id="database-install-form">
        @csrf
        <div class="buttons">
            <button type="submit" class="btn btn-primary" id="database-install-button">Start Installation</button>
        </div>
    </form>

    <section class="install-progress" aria-live="polite">
        <div class="install-progress__header">
            <strong id="install-phase">Ready</strong>
            <strong id="install-percentage">0%</strong>
        </div>
        <div class="install-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="install-progress-track">
            <div class="install-progress__bar" id="install-progress-bar"></div>
        </div>
        <p class="install-progress__message" id="install-progress-message">Ready to install.</p>
        <p class="install-progress__reference" id="install-error-reference"></p>
        <ol class="install-progress__log" id="install-progress-log"></ol>
    </section>
@stop

@section('scripts')
    <script>
        (() => {
            const form = document.getElementById('database-install-form');
            const button = document.getElementById('database-install-button');
            const phase = document.getElementById('install-phase');
            const percentage = document.getElementById('install-percentage');
            const track = document.getElementById('install-progress-track');
            const bar = document.getElementById('install-progress-bar');
            const message = document.getElementById('install-progress-message');
            const reference = document.getElementById('install-error-reference');
            const log = document.getElementById('install-progress-log');
            const progressUrl = @json(route('LaravelInstaller::database.progress'));
            const finalUrl = @json(route('LaravelInstaller::final'));
            const pollInterval = @json((int) config('installer.progress.poll_interval_ms', 750));
            const initialProgress = @json($progress);
            const finalizationRetryRequired = @json(session()->has('error') && ($progress['status'] ?? null) === 'ready');
            let polling = null;
            let finalRedirectStarted = false;

            const phaseLabel = value => value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());

            const render = state => {
                const value = Math.min(100, Math.max(0, Number(state.percentage || 0)));
                phase.textContent = phaseLabel(state.phase || 'idle');
                percentage.textContent = `${value}%`;
                track.setAttribute('aria-valuenow', String(value));
                bar.style.width = `${value}%`;
                message.textContent = state.message || 'Installation status unavailable.';
                reference.textContent = state.error_reference ? `Support reference: ${state.error_reference}` : '';
                reference.style.display = state.error_reference ? 'block' : 'none';
                log.replaceChildren();

                (state.logs || []).forEach(entry => {
                    const item = document.createElement('li');
                    item.className = entry.level || 'info';
                    item.textContent = `[${entry.time || '--:--:--'}] ${entry.message || ''}`;
                    log.appendChild(item);
                });

                log.scrollTop = log.scrollHeight;

                if (state.status === 'failed') {
                    button.disabled = false;
                    button.textContent = 'Retry Installation';
                    stopPolling();
                } else if (state.status === 'ready' && finalizationRetryRequired) {
                    button.disabled = false;
                    button.textContent = 'Retry Final Verification';
                } else if (state.status === 'running' || state.status === 'ready') {
                    button.disabled = true;
                    button.textContent = state.status === 'ready' ? 'Finalizing…' : 'Installing…';
                }
            };

            const poll = async () => {
                try {
                    const response = await fetch(progressUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    });

                    if (response.ok) {
                        const state = await response.json();
                        render(state);

                        if (state.status === 'ready' && !finalizationRetryRequired) {
                            redirectToFinal(finalUrl);
                        }
                    }
                } catch (error) {
                    message.textContent = 'Waiting for installation status…';
                }
            };

            const startPolling = () => {
                if (polling === null) {
                    polling = window.setInterval(poll, pollInterval);
                }
            };

            const stopPolling = () => {
                if (polling !== null) {
                    window.clearInterval(polling);
                    polling = null;
                }
            };

            const redirectToFinal = redirectUrl => {
                if (finalRedirectStarted) {
                    return;
                }

                finalRedirectStarted = true;
                stopPolling();
                window.location.assign(redirectUrl || finalUrl);
            };

            form.addEventListener('submit', async event => {
                event.preventDefault();

                if (finalizationRetryRequired) {
                    redirectToFinal(finalUrl);

                    return;
                }

                button.disabled = true;
                button.textContent = 'Installing…';
                reference.style.display = 'none';
                startPolling();

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    });
                    const result = await response.json();

                    await poll();

                    if (response.ok && result.redirect_url) {
                        redirectToFinal(result.redirect_url);
                        return;
                    }

                    button.disabled = false;
                    button.textContent = 'Retry Installation';
                    message.textContent = result.message || 'Installation failed.';
                    stopPolling();
                } catch (error) {
                    button.disabled = false;
                    button.textContent = 'Retry Installation';
                    message.textContent = 'The installation request was interrupted. Check the log before retrying.';
                    stopPolling();
                }
            });

            render(initialProgress);

            if (initialProgress.status === 'ready' && !finalizationRetryRequired) {
                redirectToFinal(finalUrl);
            } else if (initialProgress.status === 'running') {
                startPolling();
            }
        })();
    </script>
@endsection
