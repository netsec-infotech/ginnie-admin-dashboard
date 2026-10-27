@extends('layouts.admin.app', ['page' => $menuAdmin['support']['active'] ?? 'support', 'sub_page' => 'inbox'])

@section('title', __('labels.customer_support'))

@section('header_data')
    @php
        $page_title = __('labels.customer_support');
        $page_pretitle = 'Conversations';
    @endphp
@endsection

@section('admin-content')
    <div id="support-realtime-alert" class="alert alert-warning d-none align-items-center gap-3" role="alert" aria-live="polite">
        <span class="avatar avatar-sm bg-warning-lt text-warning flex-shrink-0"><i class="ti ti-plug-connected-x fs-2"></i></span>
        <div class="flex-fill">
            <div class="fw-semibold">{{ __('labels.support_realtime_test_failed') }}</div>
            <div class="small">{{ __('labels.support_realtime_health_alert') }}</div>
        </div>
        @can('updateSetting', [\App\Models\Setting::class, 'broadcast_driver'])
            <a class="btn btn-warning btn-sm flex-shrink-0" href="{{ route('admin.settings.show', ['setting' => 'broadcast_driver']) }}">
                {{ __('labels.configure_now') }}
            </a>
        @endcan
    </div>
    <div id="support-app"
         data-inbox-url="{{ route('admin.support.inbox') }}"
         data-show-url="{{ route('admin.support.threads.show', ['thread' => '__THREAD__']) }}"
         data-history-url="{{ route('admin.support.threads.messages', ['thread' => '__THREAD__']) }}"
         data-updates-url="{{ route('admin.support.threads.updates', ['thread' => '__THREAD__']) }}"
         data-read-url="{{ route('admin.support.threads.read', ['thread' => '__THREAD__']) }}"
         data-typing-url="{{ route('admin.support.threads.typing', ['thread' => '__THREAD__']) }}"
         data-clear-url="{{ route('admin.support.threads.messages.clear', ['thread' => '__THREAD__']) }}"
         data-delete-message-url="{{ route('admin.support.messages.destroy', ['message' => '__MESSAGE__']) }}"
         data-message-url="{{ route('admin.support.messages.store', ['ticket' => '__TICKET__']) }}"
         data-attachment-url="{{ route('admin.support.attachments.show', ['media' => '__MEDIA__']) }}"
         data-assign-url="{{ route('admin.support.tickets.assign', ['ticket' => '__TICKET__']) }}"
         data-status-url="{{ route('admin.support.tickets.status', ['ticket' => '__TICKET__']) }}"
         data-callback-url="{{ route('admin.support.callbacks.store', ['ticket' => '__TICKET__']) }}"
         data-resolve-url="{{ route('admin.support.tickets.resolve', ['ticket' => '__TICKET__']) }}"
         data-close-url="{{ route('admin.support.tickets.close', ['ticket' => '__TICKET__']) }}"
         data-topics-url="{{ route('admin.support.topics.index') }}"
         data-topic-update-url="{{ route('admin.support.topics.update', ['topic' => '__TOPIC__']) }}"
         data-realtime='@json($realtime)'
         data-poll-interval="{{ $pollInterval }}"
         data-polling-enabled="{{ $pollingEnabled ? '1' : '0' }}"
         data-can-reply="{{ $canReply ? '1' : '0' }}"
         data-can-assign="{{ $canAssign ? '1' : '0' }}"
         data-can-manage="{{ $canManage ? '1' : '0' }}"
         data-initial-thread="{{ request('thread') }}"
         data-initial-scope="{{ request('scope', 'unassigned') }}"
         data-agent-id="{{ auth()->id() }}">
        <div class="card support-card flex-fill">
            <div class="row g-0 flex-fill min-h-0 support-card-row">
                <section id="support-inbox-panel" class="col-12 col-lg-5 col-xl-3 border-end d-flex flex-column support-pane">
                    <div class="card-header d-block p-3">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="card-title mb-0">Conversations</h3>
                                <span id="support-inbox-unread-total" class="support-count-badge is-unread d-none">0</span>
                            </div>
                            <div class="btn-list flex-nowrap">
                                <button class="btn btn-sm btn-icon btn-ghost-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#support-advanced-filters" aria-expanded="false" aria-controls="support-advanced-filters" title="Filters" aria-label="Filters">
                                    <i class="ti ti-adjustments-horizontal"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <div class="input-icon flex-fill">
                                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                                <input id="support-search" class="form-control" type="search" placeholder="Search customer, order, ticket or message" autocomplete="off">
                            </div>
                        </div>
                        <div class="nav nav-pills nav-fill support-scope-tabs" id="support-scopes" role="tablist" aria-label="Conversation inbox">
                            <button class="nav-link active" data-scope="unassigned" type="button" role="tab" aria-selected="true"><span>Unassigned</span><span id="support-scope-unassigned-count" class="support-count-badge">0</span></button>
                            <button class="nav-link" data-scope="mine" type="button" role="tab" aria-selected="false"><span>Mine</span><span id="support-scope-mine-count" class="support-count-badge">0</span></button>
                            <button class="nav-link" data-scope="all" type="button" role="tab" aria-selected="false"><span>All</span><span id="support-scope-all-count" class="support-count-badge">0</span></button>
                        </div>
                        <div class="collapse" id="support-advanced-filters">
                            <div class="row g-2 pt-2">
                                <div class="col-6">
                                    <select id="support-status-filter" class="form-select form-select-sm">
                                        <option value="">Any status</option>
                                        <option value="open">Open</option>
                                        <option value="in_progress">In progress</option>
                                        <option value="waiting_customer">Waiting</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select id="support-callback-filter" class="form-select form-select-sm">
                                        <option value="all">Any callback</option>
                                        <option value="required">Callback required</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select id="support-topic-filter" class="form-select form-select-sm">
                                        <option value="">Any topic</option>
                                        @foreach($topics as $topic)
                                            <option value="{{ $topic->id }}">{{ $topic->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select id="support-rating-filter" class="form-select form-select-sm">
                                        <option value="">Any rating</option>
                                        @for($score = 5; $score >= 1; $score--)
                                            <option value="{{ $score }}">{{ $score }} star{{ $score === 1 ? '' : 's' }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div id="support-active-filters" class="d-none flex-wrap align-items-center gap-1 pt-2" aria-live="polite"></div>
                    </div>
                    <div id="support-inbox" class="card-body p-0 scrollable flex-fill list-group list-group-flush support-scrollbar" aria-live="polite"></div>
                    <div class="card-footer py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        @if($canManage)
                            <button id="support-topics-open" class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#support-topics-modal">
                                <i class="ti ti-adjustments"></i> Manage guidance
                            </button>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#support-availability-modal">
                                <i class="ti ti-clock"></i> {{ __('Availability') }}
                            </button>
                        @else
                            <span></span>
                        @endif
                        <button id="support-load-more" type="button" class="btn btn-sm btn-ghost-secondary d-none">Load more</button>
                    </div>
                </section>

                <section id="support-conversation-panel" class="col-12 col-lg-7 col-xl-6 d-none d-lg-flex flex-column support-pane position-relative">
                    <div id="support-drop-overlay" class="d-none position-absolute top-0 start-0 w-100 h-100 p-3 pe-none" role="status" aria-live="polite">
                        <div class="card h-100 border-primary border-2 border-dashed shadow-sm d-flex align-items-center justify-content-center text-center p-4">
                            <span class="avatar avatar-lg bg-primary-lt mb-3"><i class="ti ti-cloud-upload fs-1"></i></span>
                            <h3 class="mb-1">{{ __('Drop images or PDFs here') }}</h3>
                            <p class="text-secondary mb-0">{{ __('Up to 5 files · JPEG, PNG, WebP or PDF · 10 MB each') }}</p>
                        </div>
                    </div>
                    <div id="support-empty-conversation" class="empty flex-fill d-flex flex-column justify-content-center">
                        <div class="empty-icon"><i class="ti ti-messages fs-1"></i></div>
                        <p class="empty-title">Select a conversation</p>
                        <p class="empty-subtitle text-secondary">Customer history and the active support session will appear here.</p>
                    </div>
                    <div id="support-active-conversation" class="d-none flex-column flex-fill min-h-0">
                        <header class="card-header d-block py-2">
                            <div class="d-flex align-items-center gap-2">
                                <button id="support-mobile-back" class="btn btn-icon btn-ghost-secondary d-lg-none" type="button" aria-label="Back to inbox">
                                    <i class="ti ti-arrow-left"></i>
                                </button>
                                <span id="support-customer-avatar" class="avatar"></span>
                                <div class="flex-fill min-w-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <strong id="support-customer-name"></strong>
                                        <span id="support-status-badge" class="badge"></span>
                                        <span id="support-customer-presence" class="support-presence d-none text-green" aria-live="polite">
                                            <span class="support-presence-dot"></span> Online
                                        </span>
                                    </div>
                                    <div id="support-context-line" class="small text-secondary text-truncate"></div>
                                </div>
                                <button id="support-copy-ticket" class="btn btn-sm btn-ghost-secondary support-ticket-copy" type="button" title="Copy support ticket number" aria-label="Copy support ticket number">
                                    <i class="ti ti-ticket"></i>
                                    <span id="support-ticket-badge" class="d-none d-md-inline"></span>
                                    <i class="ti ti-copy"></i>
                                </button>
                                <button class="btn btn-sm btn-icon btn-ghost-secondary d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#support-context" aria-label="Customer context">
                                    <i class="ti ti-user-search"></i>
                                </button>
                                <button id="support-context-toggle" class="btn btn-sm btn-icon btn-ghost-secondary d-none d-xl-inline-flex" type="button" aria-label="Hide customer details" aria-expanded="true" title="Hide customer details">
                                    <i class="ti ti-layout-sidebar-right-collapse"></i>
                                </button>
                                <button id="support-fullscreen-toggle" class="btn btn-sm btn-icon btn-ghost-secondary" type="button" aria-label="Enter full screen" aria-pressed="false" title="Full screen">
                                    <i class="ti ti-arrows-maximize"></i>
                                </button>
                            </div>
                            <div class="support-header-toolbar d-flex align-items-center justify-content-between gap-2 mt-2">
                                <span id="support-connection" class="support-connection small fw-medium d-inline-flex align-items-center gap-1 text-yellow">
                                    <span class="support-connection-dot"></span> Polling
                                </span>
                                <div class="btn-list flex-nowrap align-items-center ms-auto">
                                    @if($canAssign)
                                        <div class="dropdown">
                                            <button id="support-assignee-toggle" class="btn btn-sm btn-ghost-secondary dropdown-toggle support-assignee-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                                <i class="ti ti-user-check"></i>
                                                <span id="support-assignee-toggle-label" class="text-truncate">Unassigned</span>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end p-2 support-assignee-menu">
                                                <label class="form-label mb-1" for="support-reassign">Assigned agent</label>
                                                <select id="support-reassign" class="form-select form-select-sm" aria-label="Assign conversation to an agent">
                                                    <option value="">Unassigned</option>
                                                    @foreach($agents as $agent)
                                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button id="support-claim" class="btn btn-sm btn-primary w-100 mt-2" type="button">
                                                    <i class="ti ti-user-plus"></i> Assign to me
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    @if($canManage)
                                        <button id="support-resolve" class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#support-action-modal"><i class="ti ti-check"></i><span class="d-none d-md-inline"> Resolve</span></button>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-icon btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More conversation actions">
                                                <i class="ti ti-dots-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button id="support-waiting" class="dropdown-item" type="button">
                                                    <i class="ti ti-clock me-2"></i> Wait for customer
                                                </button>
                                                <button id="support-progress" class="dropdown-item d-none" type="button">
                                                    <i class="ti ti-progress-check me-2"></i> Mark in progress
                                                </button>
                                                <button id="support-call" class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#support-action-modal">
                                                    <i class="ti ti-phone me-2"></i> Log a call
                                                </button>
                                                <div class="dropdown-divider"></div>
                                                <button id="support-close" class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#support-action-modal">
                                                    <i class="ti ti-lock me-2"></i> Close ticket
                                                </button>
                                                <button id="support-clear" class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#support-action-modal">
                                                    <i class="ti ti-trash me-2"></i> Clear chat history
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </header>
                        <div id="support-messages" class="card-body scrollable flex-fill support-message-scroll support-scrollbar" aria-live="polite">
                            <div class="chat">
                                <div class="text-center mb-2">
                                    <button id="support-load-older" class="btn btn-sm btn-ghost-secondary d-none" type="button">
                                        <i class="ti ti-history me-1"></i> Load older messages
                                    </button>
                                </div>
                                <div id="support-message-list" class="chat-bubbles"></div>
                                <div id="support-typing" class="chat-item d-none" aria-live="polite">
                                    <div class="row align-items-end">
                                        <div class="col-auto"><span id="support-typing-avatar" class="avatar avatar-1"></span></div>
                                        <div class="col-auto"><div class="chat-bubble support-typing-bubble"><span></span><span></span><span></span><span class="visually-hidden">Customer is typing</span></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer id="support-composer" class="card-footer p-2 p-md-3">
                            <form id="support-message-form" class="w-100{{ $canReply ? '' : ' d-none' }}">
                                <div id="support-attachment-preview" class="d-flex flex-wrap gap-2 mb-2" aria-live="polite"></div>
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-1 flex-fill min-w-0">
                                        <select id="support-canned" class="form-select form-select-sm support-canned" aria-label="Quick reply">
                                            <option value="">Quick reply…</option>
                                        </select>
                                        @if($canManage)
                                            <button id="support-quick-replies-manage" class="btn btn-sm btn-icon btn-ghost-secondary flex-shrink-0" type="button" data-bs-toggle="modal" data-bs-target="#support-topics-modal" title="Manage quick replies" aria-label="Manage quick replies">
                                                <i class="ti ti-settings"></i>
                                            </button>
                                        @endif
                                    </div>
                                    <span class="small text-secondary d-none d-md-inline">Enter to send · Shift+Enter for a new line</span>
                                </div>
                                <div class="input-group input-group-flat support-message-input">
                                    <textarea id="support-message" class="form-control" rows="1" maxlength="4000" placeholder="Type a reply…" autocomplete="off" aria-label="Message"></textarea>
                                    <span class="input-group-text">
                                        <label class="link-secondary mb-0 support-attachment-trigger" for="support-attachments" title="Attach image or PDF" aria-label="Attach image or PDF">
                                            <i class="ti ti-paperclip"></i>
                                        </label>
                                        <input id="support-attachments" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" multiple>
                                    </span>
                                    <button id="support-send" class="btn btn-primary" type="submit">
                                        <i class="ti ti-send"></i>
                                        <span class="ms-1 d-none d-sm-inline">Send</span>
                                    </button>
                                </div>
                                <div class="small text-secondary mt-1 d-none d-sm-block">JPEG, PNG, WebP or PDF · 5 files · 10 MB each · 4,000 characters</div>
                            </form>
                            <div id="support-readonly" class="alert alert-secondary d-none w-100 mb-0"></div>
                        </footer>
                    </div>
                </section>

                <aside id="support-context-panel" class="col-xl-3 border-start d-none d-xl-flex flex-column support-pane">
                    <div class="card-header py-3">
                        <div>
                            <h3 class="card-title mb-0">Customer details</h3>
                            <div class="small text-secondary">Order and support history</div>
                        </div>
                    </div>
                    <div id="support-context-panel-body" class="card-body overflow-auto support-scrollbar"></div>
                </aside>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="support-context" aria-labelledby="support-context-title">
        <div class="offcanvas-header">
            <h2 class="offcanvas-title" id="support-context-title">Customer context</h2>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div id="support-context-body" class="offcanvas-body support-scrollbar"></div>
    </div>

    <div class="modal modal-blur fade" id="support-image-modal" tabindex="-1" aria-labelledby="support-image-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h2 class="modal-title text-truncate" id="support-image-modal-title">Attachment preview</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="support-lightbox-viewport" class="modal-body p-2 text-center bg-dark-lt overflow-auto">
                    <img id="support-lightbox-image" class="img-fluid rounded support-lightbox-image" alt="">
                </div>
                <div class="modal-footer py-2">
                    <div class="btn-group me-auto" role="group" aria-label="{{ __('Image zoom') }}">
                        <button id="support-zoom-out" type="button" class="btn btn-outline-secondary btn-sm" aria-label="{{ __('Zoom out') }}"><i class="ti ti-zoom-out"></i></button>
                        <button id="support-zoom-reset" type="button" class="btn btn-outline-secondary btn-sm">100%</button>
                        <button id="support-zoom-in" type="button" class="btn btn-outline-secondary btn-sm" aria-label="{{ __('Zoom in') }}"><i class="ti ti-zoom-in"></i></button>
                    </div>
                    <a id="support-lightbox-download" class="btn btn-primary btn-sm" href="#" download>
                        <i class="ti ti-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if($canManage)
        <div class="modal modal-blur fade" id="support-action-modal" tabindex="-1" aria-labelledby="support-action-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <form id="support-action-form" class="modal-content" autocomplete="off">
                    <div id="support-action-status" class="modal-status bg-primary"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="modal-body py-4">
                        <div class="d-flex align-items-start gap-3 mb-4">
                            <span id="support-action-icon" class="avatar avatar-lg bg-primary-lt text-primary">
                                <i class="ti ti-check fs-1"></i>
                            </span>
                            <div>
                                <h3 id="support-action-title" class="mb-1">Update conversation</h3>
                                <p id="support-action-description" class="text-secondary mb-0"></p>
                            </div>
                        </div>
                        <input id="support-action-type" type="hidden">
                        <input id="support-action-message-id" type="hidden">
                        <div id="support-action-summary-group">
                            <label id="support-action-summary-label" class="form-label" for="support-action-summary">Summary</label>
                            <textarea id="support-action-summary" class="form-control" rows="4" maxlength="2000"></textarea>
                            <div class="form-hint">Visible to agents reviewing this support history.</div>
                        </div>
                        <div id="support-action-call-fields" class="d-none">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="support-call-status">Call status</label>
                                    <select id="support-call-status" class="form-select">
                                        <option value="attempted">Attempted</option>
                                        <option value="connected">Connected</option>
                                        <option value="no_answer">No answer</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="support-call-phone">Phone</label>
                                    <input id="support-call-phone" class="form-control" type="tel" maxlength="32">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="support-call-outcome">Outcome</label>
                                    <textarea id="support-call-outcome" class="form-control" rows="3" maxlength="2000" placeholder="What happened during the call?"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="support-call-note">Private agent note</label>
                                    <textarea id="support-call-note" class="form-control" rows="3" maxlength="4000" placeholder="Only support agents can see this note."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button id="support-action-submit" class="btn btn-primary" type="submit">
                            <i id="support-action-submit-icon" class="ti ti-check me-1"></i>
                            <span id="support-action-submit-label">Confirm</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if($canManage)
            <div class="modal modal-blur fade" id="support-availability-modal" tabindex="-1" aria-labelledby="support-availability-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <form id="support-availability-form" class="modal-content form-submit" action="{{ route('admin.support.availability.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title" id="support-availability-title">{{ __('Support availability') }}</h2>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body p-3">
                            <x-weekly-schedule name="" :schedule="$availability" />
                            <div id="support-away-message-fields" class="mt-3 {{ !$availability['enabled'] ? 'd-none' : '' }}">
                                <label class="form-label" for="support-away-message">{{ __('Outside-hours message') }}</label>
                                <textarea id="support-away-message" name="message" class="form-control" rows="2" maxlength="2000" @required($availability['enabled']) @disabled(!$availability['enabled'])>{{ $availability['message'] }}</textarea>
                                <div class="form-hint">{{ __('Sent once per ticket outside support hours. Customers can still send messages.') }}</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-ghost-secondary" type="button" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button id="support-availability-save" class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>{{ __('Save availability') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="modal modal-blur fade" id="support-topics-modal" tabindex="-1" aria-labelledby="support-topics-title" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title" id="support-topics-title">Manage customer guidance</h2>
                            <div class="text-secondary">Organize topics, pre-chat instructions, and agent quick replies.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <div>
                                            <h3 class="card-title">Topics</h3>
                                            <div class="text-secondary small">Select a topic to edit.</div>
                                        </div>
                                        <div class="card-actions">
                                            <button id="support-topic-new" class="btn btn-sm btn-primary" type="button">
                                                <i class="ti ti-plus"></i> New
                                            </button>
                                        </div>
                                    </div>
                                    <div id="support-topic-list" class="list-group list-group-flush support-scrollbar overflow-auto" aria-live="polite"></div>
                                </div>
                            </div>
                            <form id="support-topic-form" class="col-lg-8" autocomplete="off">
                                <input id="support-topic-id" type="hidden">
                                <div class="card mb-3">
                                    <div class="card-header"><h3 class="card-title">Topic details</h3></div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-7">
                                                <label class="form-label required" for="support-topic-title">Topic name</label>
                                                <input id="support-topic-title" class="form-control" type="text" maxlength="191" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label required" for="support-topic-context">Available for</label>
                                                <select id="support-topic-context" class="form-select" required>
                                                    <option value="both">Orders and general help</option>
                                                    <option value="order">Orders only</option>
                                                    <option value="general">General help only</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label" for="support-topic-sort">Display order</label>
                                                <input id="support-topic-sort" class="form-control" type="number" min="0" max="65535" value="0" required>
                                            </div>
                                            <div class="col-sm-6 d-flex align-items-end">
                                                <label class="form-check form-switch mb-2">
                                                    <input id="support-topic-active" class="form-check-input" type="checkbox" checked>
                                                    <span class="form-check-label">Visible to customers</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header"><h3 class="card-title">Conversation guidance</h3></div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <label class="form-label" for="support-topic-guidance">Pre-chat guidance</label>
                                            <select id="support-topic-guidance" class="form-select support-topic-tags" multiple data-max-items="12" data-max-item-length="500" data-placeholder="Type an instruction and press Enter"></select>
                                            <div class="form-hint">Add each instruction separately. They are shown before a customer starts this support session.</div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="support-topic-customer-quick-replies">Customer prompts</label>
                                                <select id="support-topic-customer-quick-replies" class="form-select support-topic-tags" multiple data-max-items="12" data-max-item-length="191" data-placeholder="Type a customer prompt and press Enter"></select>
                                                <div class="form-hint">Add up to 12 prompts shown to customers before starting a chat.</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="support-topic-agent-quick-replies">Agent quick replies</label>
                                                <select id="support-topic-agent-quick-replies" class="form-select support-topic-tags" multiple data-max-items="12" data-max-item-length="191" data-placeholder="Type an agent reply and press Enter"></select>
                                                <div class="form-hint">Add up to 12 replies shown only in the admin composer.</div>
                                            </div>
                                        </div>
                                        <div class="form-hint mt-2">Press Enter to add an option. Select an option and press Backspace to remove it.</div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button id="support-topic-reset" class="btn btn-ghost-secondary" type="button">Discard changes</button>
                                    <button id="support-topic-save" class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i> Save topic</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('script')
    <script src="{{ asset('assets/vendor/pusher/pusher.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/laravel-echo/echo.iife.js') }}"></script>
    <script src="{{ hyperAsset('assets/js/support-chat.js') }}?v={{ filemtime(public_path('assets/js/support-chat.js')) }}"></script>
@endpush

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/support-chat.css') }}?v={{ filemtime(public_path('assets/css/support-chat.css')) }}">
@endpush
