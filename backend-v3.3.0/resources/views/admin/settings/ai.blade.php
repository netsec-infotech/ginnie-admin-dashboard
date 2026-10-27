@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? '', 'sub_page' => $menuAdmin['settings']['route']['ai']['sub_active'] ?? ''])

@section('title', __('labels.ai_settings'))

@section('header_data')
    @php
        $page_title = __('labels.ai_settings');
        $page_pretitle = __('labels.admin') . ' ' . __('labels.settings');
    @endphp
@endsection

@php
    $breadcrumbs = [
        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],
        ['title' => __('labels.ai_settings'), 'url' => null],
    ];

    $providerCatalog = $providerCatalog ?? app(\App\Services\Ai\AiManager::class)->getProviderCatalog();
    $currentProvider = $settings['aiProvider'] ?? 'gemini';
    $currentModel = $settings['aiModel'] ?? 'gemini-3.8-flash';
    $currentCustomModel = $settings['aiCustomModel'] ?? '';
    $currentCustomBaseUrl = $settings['aiCustomBaseUrl'] ?? '';
    $currentTemperature = $settings['aiTemperature'] ?? 0.7;
    $enableAi = !empty($settings['enableAiDescription']);
@endphp

@section('admin-content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('labels.ai_settings') }}</h2>
                <x-breadcrumb :items="$breadcrumbs" />
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <form id="ai-settings-form" action="{{ route('admin.settings.store') }}" method="post" class="form-submit"
                data-test-url="{{ route('admin.settings.ai.test-connection') }}">
                @csrf
                <input type="hidden" name="type" value="ai">
                <input type="hidden" name="redirect_url" value="{{ route('admin.settings.show', ['setting' => 'ai']) }}">

                {{-- Card 1: Master Enable / Disable --}}
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title">{{ __('labels.ai_description_generator') }}</h3>
                            <p class="text-secondary mb-0">{{ __('labels.ai_description_generator_subtitle') }}</p>
                        </div>
                        <div class="form-check form-switch form-switch-lg m-0">
                            <input type="hidden" name="enableAiDescription" value="0">
                            <input class="form-check-input" type="checkbox" name="enableAiDescription"
                                id="enableAiDescriptionToggle" value="1" @checked($enableAi)>
                        </div>
                    </div>
                </div>

                <div id="aiConfigSection" class="{{ $enableAi ? '' : 'd-none' }}">
                    {{-- Card 2: Provider & Model Selection --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.ai_provider_model_configuration') }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                {{-- Provider Select --}}
                                <div class="col-md-6">
                                    <label class="form-label required"
                                        for="aiProviderSelect">{{ __('labels.ai_provider') }}</label>
                                    <select class="form-select" id="aiProviderSelect" name="aiProvider" required>
                                        @foreach ($providerCatalog as $key => $info)
                                            <option value="{{ $key }}" @selected($currentProvider === $key)>
                                                {{ $info['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-hint">{{ __('labels.ai_provider_hint') }}</small>
                                </div>

                                {{-- Model Select --}}
                                <div class="col-md-6" id="modelSelectWrap">
                                    <label class="form-label required"
                                        for="aiModelSelect">{{ __('labels.ai_model') }}</label>
                                    <select class="form-select" id="aiModelSelect" name="aiModel">
                                        {{-- Populated dynamically via JS --}}
                                    </select>
                                    <small class="form-hint">{{ __('labels.ai_model_hint') }}</small>
                                </div>

                                {{-- Custom Model Input (shown if custom provider is chosen or custom model is selected) --}}
                                <div class="col-md-6 d-none" id="customModelWrap">
                                    <label class="form-label required"
                                        for="aiCustomModelInput">{{ __('labels.custom_model_name') }}</label>
                                    <input type="text" class="form-control" name="aiCustomModel" id="aiCustomModelInput"
                                        placeholder="e.g. deepseek/deepseek-r1, meta-llama/llama-3.3-70b-instruct"
                                        value="{{ $currentCustomModel }}">
                                    <small class="form-hint">{{ __('labels.custom_model_hint') }}</small>

                                    {{-- Quick Preset Helper Chips --}}
                                    <div class="mt-2" id="quickModelPresets">
                                        <small class="text-muted d-block mb-1">Quick Presets (click to apply):</small>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-quick-model"
                                                data-model="deepseek/deepseek-r1" data-base-url="https://openrouter.ai/api/v1">
                                                DeepSeek R1 (OpenRouter)
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-quick-model"
                                                data-model="meta-llama/llama-3.3-70b-instruct" data-base-url="https://openrouter.ai/api/v1">
                                                Llama 3.3 70B (OpenRouter)
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-quick-model"
                                                data-model="anthropic/claude-3.5-sonnet" data-base-url="https://openrouter.ai/api/v1">
                                                Claude 3.5 Sonnet (OpenRouter)
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-quick-model"
                                                data-model="openai/gpt-4o-mini" data-base-url="https://openrouter.ai/api/v1">
                                                GPT-4o Mini (OpenRouter)
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-quick-model"
                                                data-model="llama3.2" data-base-url="http://localhost:11434/v1">
                                                Llama 3.2 (Local Ollama)
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 btn-quick-model"
                                                data-model="qwen2.5" data-base-url="http://localhost:11434/v1">
                                                Qwen 2.5 (Local Ollama)
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Custom Base URL (for Ollama, OpenRouter, LocalAI) --}}
                                <div class="col-md-6 d-none" id="customBaseUrlWrap">
                                    <label class="form-label"
                                        for="aiCustomBaseUrlInput">{{ __('labels.custom_api_base_url') }}</label>
                                    <input type="url" class="form-control" name="aiCustomBaseUrl"
                                        id="aiCustomBaseUrlInput"
                                        placeholder="https://openrouter.ai/api/v1"
                                        value="{{ $currentCustomBaseUrl }}">
                                    <small class="form-hint">{{ __('labels.custom_api_base_url_hint') }}</small>
                                </div>

                                {{-- Creativity / Temperature Slider --}}
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label mb-0"
                                            for="aiTemperatureInput">{{ __('labels.ai_creativity_temperature') }}</label>
                                        <span class="badge bg-blue-lt fw-bold"
                                            id="temperatureBadge">{{ $currentTemperature }}</span>
                                    </div>
                                    <input type="range" class="form-range" min="0.0" max="1.0" step="0.05"
                                        name="aiTemperature" id="aiTemperatureInput" value="{{ $currentTemperature }}">
                                    <div class="d-flex justify-content-between text-muted fs-5">
                                        <span>0.0 (Factual & Precise)</span>
                                        <span>0.7 (Balanced)</span>
                                        <span>1.0 (Creative & Punchy)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Card 3: Provider API Credentials --}}
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">{{ __('labels.api_credentials') }}</h3>
                        </div>
                        <div class="card-body">
                            {{-- Gemini Key --}}
                            <div class="mb-3 provider-key-field" data-provider="gemini">
                                <label class="form-label required">{{ __('labels.gemini_api_key') }}</label>
                                <input type="text" class="form-control" name="geminiApiKey" id="geminiApiKeyInput"
                                    placeholder="Enter Google Gemini API Key"
                                    value="{{ $isDemoUser ? Str::mask($settings['geminiApiKey'] ?? '****', '****', 3, 8) : $settings['geminiApiKey'] ?? '' }}">
                                <small class="form-hint">Get an API key free at <a
                                        href="https://aistudio.google.com/app/apikey" target="_blank"
                                        rel="noopener">Google AI Studio</a>.</small>
                            </div>

                            {{-- OpenAI Key --}}
                            <div class="mb-3 provider-key-field d-none" data-provider="openai">
                                <label class="form-label required">{{ __('labels.openai_api_key') }}</label>
                                <input type="text" class="form-control" name="openaiApiKey" id="openaiApiKeyInput"
                                    placeholder="Enter OpenAI API Key (sk-...)"
                                    value="{{ $isDemoUser ? Str::mask($settings['openaiApiKey'] ?? '****', '****', 3, 8) : $settings['openaiApiKey'] ?? '' }}">
                                <small class="form-hint">Obtain your API key from <a
                                        href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI
                                        Platform</a>.</small>
                            </div>

                            {{-- Claude Key --}}
                            <div class="mb-3 provider-key-field d-none" data-provider="claude">
                                <label class="form-label required">{{ __('labels.claude_api_key') }}</label>
                                <input type="text" class="form-control" name="claudeApiKey" id="claudeApiKeyInput"
                                    placeholder="Enter Anthropic API Key (sk-ant-...)"
                                    value="{{ $isDemoUser ? Str::mask($settings['claudeApiKey'] ?? '****', '****', 3, 8) : $settings['claudeApiKey'] ?? '' }}">
                                <small class="form-hint">Get your API key from <a href="https://console.anthropic.com/"
                                        target="_blank" rel="noopener">Anthropic Console</a>.</small>
                            </div>

                            {{-- DeepSeek Key --}}
                            <div class="mb-3 provider-key-field d-none" data-provider="deepseek">
                                <label class="form-label required">{{ __('labels.deepseek_api_key') }}</label>
                                <input type="text" class="form-control" name="deepseekApiKey"
                                    id="deepseekApiKeyInput" placeholder="Enter DeepSeek API Key (sk-...)"
                                    value="{{ $isDemoUser ? Str::mask($settings['deepseekApiKey'] ?? '****', '****', 3, 8) : $settings['deepseekApiKey'] ?? '' }}">
                                <small class="form-hint">Get your API key from <a
                                        href="https://platform.deepseek.com/api_keys" target="_blank"
                                        rel="noopener">DeepSeek Platform</a>.</small>
                            </div>

                            {{-- Groq Key --}}
                            <div class="mb-3 provider-key-field d-none" data-provider="groq">
                                <label class="form-label required">{{ __('labels.groq_api_key') }}</label>
                                <input type="text" class="form-control" name="groqApiKey" id="groqApiKeyInput"
                                    placeholder="Enter Groq API Key (gsk_...)"
                                    value="{{ $isDemoUser ? Str::mask($settings['groqApiKey'] ?? '****', '****', 3, 8) : $settings['groqApiKey'] ?? '' }}">
                                <small class="form-hint">Fastest inference on Llama 3.3. Get your key at <a
                                        href="https://console.groq.com/keys" target="_blank" rel="noopener">Groq
                                        Console</a>.</small>
                            </div>

                            {{-- Custom Key --}}
                            <div class="mb-3 provider-key-field d-none" data-provider="custom">
                                <label class="form-label">{{ __('labels.custom_ai_api_key') }}</label>
                                <input type="text" class="form-control" name="customAiApiKey"
                                    id="customAiApiKeyInput"
                                    placeholder="Enter Custom API Key (Optional for local Ollama)"
                                    value="{{ $isDemoUser ? Str::mask($settings['customAiApiKey'] ?? '****', '****', 3, 8) : $settings['customAiApiKey'] ?? '' }}">
                                <small class="form-hint">Leave blank if your local Ollama / LocalAI instance does not
                                    require an Authorization header.</small>
                            </div>
                        </div>
                    </div>

                    {{-- Card 4: Live Connection Test & Diagnostics --}}
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">{{ __('labels.live_connection_test') }}</h3>
                            <button type="button" class="btn btn-outline-primary" id="btnTestAiConnection">
                                <i class="ti ti-plug-connected me-1"></i> {{ __('labels.test_api_connection') }}
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-secondary mb-3">{{ __('labels.live_connection_test_hint') }}</p>

                            <div id="testResultAlert" class="alert d-none" role="alert">
                                <div class="d-flex align-items-center">
                                    <div class="alert-icon me-2" id="testResultIcon"></div>
                                    <div>
                                        <h4 class="alert-title mb-1" id="testResultTitle"></h4>
                                        <div class="text-secondary" id="testResultMessage"></div>
                                    </div>
                                    <div class="ms-auto" id="testResultLatency"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit Button --}}
                @can('updateSetting', [\App\Models\Setting::class, 'ai'])
                    <div class="d-flex">
                        <button type="submit" class="btn btn-primary ms-auto">
                            {{ __('labels.submit') }}
                        </button>
                    </div>
                @endcan
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const catalog = @json($providerCatalog);
            const selectedModelValue = @json($currentModel);
            const providerSelect = document.getElementById('aiProviderSelect');
            const modelSelect = document.getElementById('aiModelSelect');
            const modelSelectWrap = document.getElementById('modelSelectWrap');
            const customModelWrap = document.getElementById('customModelWrap');
            const customModelInput = document.getElementById('aiCustomModelInput');
            const customBaseUrlWrap = document.getElementById('customBaseUrlWrap');
            const customBaseUrlInput = document.getElementById('aiCustomBaseUrlInput');
            const quickModelPresets = document.getElementById('quickModelPresets');
            const enableToggle = document.getElementById('enableAiDescriptionToggle');
            const configSection = document.getElementById('aiConfigSection');
            const tempInput = document.getElementById('aiTemperatureInput');
            const tempBadge = document.getElementById('temperatureBadge');

            // Master toggle
            if (enableToggle && configSection) {
                enableToggle.addEventListener('change', function() {
                    configSection.classList.toggle('d-none', !this.checked);
                });
            }

            // Temperature badge
            if (tempInput && tempBadge) {
                tempInput.addEventListener('input', function() {
                    tempBadge.textContent = Number(this.value).toFixed(2);
                });
            }

            // Populate Model dropdown or show custom model input based on selected provider
            function updateProviderUI() {
                const provider = providerSelect.value;
                const info = catalog[provider] || {};
                const models = info.models || {};
                const hasCuratedModels = Object.keys(models).length > 0;

                if (provider === 'custom' || !hasCuratedModels) {
                    // Option 3: For Custom / OpenAI-compatible, hide the dropdown and show direct model text input
                    if (modelSelectWrap) modelSelectWrap.classList.add('d-none');
                    if (customModelWrap) customModelWrap.classList.remove('d-none');
                    if (quickModelPresets) quickModelPresets.classList.remove('d-none');
                    if (customBaseUrlWrap) customBaseUrlWrap.classList.remove('d-none');

                    // Set default base URL if empty
                    if (customBaseUrlInput && !customBaseUrlInput.value.trim() && info.base_url_default) {
                        customBaseUrlInput.value = info.base_url_default;
                    }
                } else {
                    // Standard dedicated provider with curated models
                    if (modelSelectWrap) modelSelectWrap.classList.remove('d-none');
                    if (quickModelPresets) quickModelPresets.classList.add('d-none');

                    modelSelect.innerHTML = '';
                    let isCurrentModelPresent = false;

                    Object.keys(models).forEach(modelKey => {
                        const opt = document.createElement('option');
                        opt.value = modelKey;
                        opt.textContent = models[modelKey];
                        if (modelKey === selectedModelValue) {
                            opt.selected = true;
                            isCurrentModelPresent = true;
                        }
                        modelSelect.appendChild(opt);
                    });

                    // Add custom model option to allow overriding dedicated provider models
                    const customOpt = document.createElement('option');
                    customOpt.value = 'custom';
                    customOpt.textContent = 'Custom Model (Specify below)';
                    if (!isCurrentModelPresent && (selectedModelValue === 'custom' || (customModelInput && customModelInput.value.trim() !== ''))) {
                        customOpt.selected = true;
                    }
                    modelSelect.appendChild(customOpt);

                    checkCustomModelVisibility();

                    if (customBaseUrlWrap) {
                        if (info.requires_base_url) {
                            customBaseUrlWrap.classList.remove('d-none');
                        } else {
                            customBaseUrlWrap.classList.add('d-none');
                        }
                    }
                }

                // API Key fields visibility
                document.querySelectorAll('.provider-key-field').forEach(field => {
                    const targetProvider = field.getAttribute('data-provider');
                    if (targetProvider === provider) {
                        field.classList.remove('d-none');
                    } else {
                        field.classList.add('d-none');
                    }
                });
            }

            function checkCustomModelVisibility() {
                const provider = providerSelect.value;
                if (provider === 'custom') {
                    if (customModelWrap) customModelWrap.classList.remove('d-none');
                } else if (modelSelect && modelSelect.value === 'custom') {
                    if (customModelWrap) customModelWrap.classList.remove('d-none');
                } else {
                    if (customModelWrap) customModelWrap.classList.add('d-none');
                }
            }

            // Quick Preset Click Handler
            document.querySelectorAll('.btn-quick-model').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const modelName = this.getAttribute('data-model');
                    const baseUrl = this.getAttribute('data-base-url');

                    if (customModelInput && modelName) {
                        customModelInput.value = modelName;
                    }
                    if (customBaseUrlInput && baseUrl) {
                        customBaseUrlInput.value = baseUrl;
                    }
                });
            });

            providerSelect.addEventListener('change', updateProviderUI);
            if (modelSelect) {
                modelSelect.addEventListener('change', checkCustomModelVisibility);
            }
            updateProviderUI();

            // ── Test Connection Button ──
            const btnTest = document.getElementById('btnTestAiConnection');
            const alertBox = document.getElementById('testResultAlert');
            const alertIcon = document.getElementById('testResultIcon');
            const alertTitle = document.getElementById('testResultTitle');
            const alertMessage = document.getElementById('testResultMessage');
            const alertLatency = document.getElementById('testResultLatency');

            if (btnTest) {
                btnTest.addEventListener('click', function() {
                    const form = document.getElementById('ai-settings-form');
                    const testUrl = form.getAttribute('data-test-url');
                    const provider = providerSelect.value;
                    const activeKeyField = document.querySelector(
                        `.provider-key-field[data-provider="${provider}"] input`);
                    const apiKey = activeKeyField ? activeKeyField.value.trim() : '';
                    const customModel = customModelInput ? customModelInput.value.trim() : '';
                    
                    let model = '';
                    if (provider === 'custom') {
                        model = customModel;
                    } else if (modelSelect && modelSelect.value === 'custom') {
                        model = customModel;
                    } else if (modelSelect) {
                        model = modelSelect.value;
                    }

                    const customBaseUrl = document.getElementById('aiCustomBaseUrlInput')?.value.trim() || '';
                    const temperature = tempInput ? tempInput.value : 0.1;

                    btnTest.disabled = true;
                    btnTest.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Testing...';
                    alertBox.classList.add('d-none');

                    fetch(testUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    ?.content || '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                provider: provider,
                                api_key: apiKey,
                                model: model,
                                custom_model: (provider === 'custom' || (modelSelect && modelSelect.value === 'custom')) ? customModel : '',
                                custom_base_url: customBaseUrl,
                                temperature: temperature,
                            })
                        })
                        .then(async res => {
                            const data = await res.json().catch(() => null);
                            return {
                                ok: res.ok,
                                status: res.status,
                                data: data
                            };
                        })
                        .then(res => {
                            btnTest.disabled = false;
                            btnTest.innerHTML =
                                '<i class="ti ti-plug-connected me-1"></i> Test API Connection';
                            alertBox.classList.remove('d-none', 'alert-success', 'alert-danger');

                            if (res.ok && res.data && res.data.success) {
                                alertBox.classList.add('alert-success');
                                alertIcon.innerHTML =
                                    '<i class="ti ti-circle-check fs-2 text-success"></i>';
                                alertTitle.textContent = 'Connection Successful';
                                alertMessage.textContent = res.data.message ||
                                    'Model responded successfully.';
                                alertLatency.innerHTML = res.data.latency_ms ?
                                    `<span class="badge bg-success-lt">${res.data.latency_ms} ms</span>` :
                                    '';
                            } else {
                                alertBox.classList.add('alert-danger');
                                alertIcon.innerHTML =
                                    '<i class="ti ti-alert-triangle fs-2 text-danger"></i>';
                                alertTitle.textContent = 'Connection Failed';
                                alertMessage.textContent = (res.data && res.data.message) ? res.data
                                    .message : `HTTP Error ${res.status}`;
                                alertLatency.innerHTML = res.data && res.data.latency_ms ?
                                    `<span class="badge bg-danger-lt">${res.data.latency_ms} ms</span>` :
                                    '';
                            }
                        })
                        .catch(err => {
                            btnTest.disabled = false;
                            btnTest.innerHTML =
                                '<i class="ti ti-plug-connected me-1"></i> Test API Connection';
                            alertBox.classList.remove('d-none', 'alert-success');
                            alertBox.classList.add('alert-danger');
                            alertIcon.innerHTML =
                                '<i class="ti ti-alert-triangle fs-2 text-danger"></i>';
                            alertTitle.textContent = 'Network / Request Error';
                            alertMessage.textContent = err.message || 'Unable to connect to server.';
                            alertLatency.innerHTML = '';
                        });
                });
            }
        });
    </script>
@endpush
