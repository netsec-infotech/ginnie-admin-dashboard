<div class="row row-deck row-cards">
    @foreach([
        ['label' => 'Active conversations', 'value' => $metrics['active'], 'hint' => 'Open, in progress, or waiting for customer', 'icon' => 'ti-message-circle', 'color' => 'primary'],
        ['label' => 'Unassigned', 'value' => $metrics['unassigned'], 'hint' => 'Active chats without an assigned executive', 'icon' => 'ti-user-question', 'color' => 'warning'],
        ['label' => 'Resolved in period', 'value' => $metrics['resolved'], 'hint' => 'Based on resolved_at from '.$from->format('d M').' to '.$to->format('d M Y'), 'icon' => 'ti-circle-check', 'color' => 'success'],
        ['label' => 'Callbacks waiting', 'value' => $metrics['callbacks'], 'hint' => 'Requested callbacks on active chats only', 'icon' => 'ti-phone-calling', 'color' => 'danger'],
    ] as $metric)
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <span class="avatar bg-{{ $metric['color'] }}-lt text-{{ $metric['color'] }} me-3">
                            <i class="ti {{ $metric['icon'] }} fs-2"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="h2 mb-0">{{ number_format($metric['value']) }}</div>
                            <div class="fw-medium">{{ $metric['label'] }}</div>
                            <div class="small text-secondary mt-1">{{ $metric['hint'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Customer rating</h3>
                    <div class="text-secondary">
                        @if($metrics['rating_average'] !== null)
                            {{ number_format($metrics['rating_average'], 2) }} average from {{ number_format($metrics['rating_count']) }} ratings
                        @else
                            No ratings in this period
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body">
                @for($score = 5; $score >= 1; $score--)
                    @php
                        $count = (int) ($ratingDistribution[$score] ?? 0);
                        $percentage = $metrics['rating_count'] > 0 ? round(($count / $metrics['rating_count']) * 100) : 0;
                    @endphp
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="text-nowrap">{{ $score }} <i class="ti ti-star-filled text-yellow"></i></span>
                        <div class="progress progress-sm flex-fill" role="progressbar" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-yellow" style="width: {{ $percentage }}%"></div>
                        </div>
                        <span class="text-secondary text-end" style="min-width: 2rem">{{ $count }}</span>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Customers waiting</h3>
                    <div class="text-secondary">{{ number_format($metrics['waiting']) }} active chats whose latest message is from the customer.</div>
                </div>
            </div>
            <div class="support-dashboard-list"
                 data-support-queue="waiting"
                 data-current-page="{{ $waitingPagination['current_page'] }}"
                 data-last-page="{{ $waitingPagination['last_page'] }}">
                <div class="list-group list-group-flush" data-queue-items>
                    @include('admin.support.partials.dashboard-queue-items', [
                        'items' => $waitingCustomers,
                        'queue' => 'waiting',
                        'showEmpty' => true,
                    ])
                </div>
                <div class="d-none py-3 text-center" data-queue-loading>
                    <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                    <span class="text-secondary small ms-2">Loading more customers…</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100 {{ $metrics['late'] > 0 ? 'border-danger' : '' }}">
            <div class="card-header flex-wrap gap-2">
                <div>
                    <h3 class="card-title">Late replies</h3>
                    <div class="text-secondary">Customers waiting beyond the selected reply time.</div>
                </div>
                <div class="card-actions d-flex align-items-center gap-2 ms-auto">
                    <span class="badge bg-danger-lt text-danger" data-queue-total="late">{{ $metrics['late'] }} late</span>
                    <label class="visually-hidden" for="support-late-minutes">Late reply time</label>
                    <select id="support-late-minutes" class="form-select form-select-sm w-auto" data-late-reply-filter>
                        @foreach($lateReplyOptions as $minutes)
                            <option value="{{ $minutes }}" @selected($lateReplyMinutes === $minutes)>{{ $minutes }} min</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="support-dashboard-list"
                 data-support-queue="late"
                 data-current-page="{{ $latePagination['current_page'] }}"
                 data-last-page="{{ $latePagination['last_page'] }}"
                 data-late-minutes="{{ $lateReplyMinutes }}">
                <div class="list-group list-group-flush" data-queue-items>
                    @include('admin.support.partials.dashboard-queue-items', [
                        'items' => $lateReplies,
                        'queue' => 'late',
                        'lateReplyMinutes' => $lateReplyMinutes,
                        'showEmpty' => true,
                    ])
                </div>
                <div class="d-none py-3 text-center" data-queue-loading>
                    <span class="spinner-border spinner-border-sm text-danger" role="status" aria-hidden="true"></span>
                    <span class="text-secondary small ms-2">Loading more late replies…</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Conversation topics</h3></div>
            <div class="card-body">
                @php $largestTopic = max(1, (int) ($topics->max('total') ?? 1)); @endphp
                @forelse($topics as $topic)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-truncate me-3">{{ $topic->topic }}</span>
                            <strong>{{ number_format($topic->total) }}</strong>
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar" style="width: {{ round(($topic->total / $largestTopic) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="empty py-5"><p class="empty-subtitle text-secondary">No conversations in this period.</p></div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title">Recent conversations</h3></div>
            <div class="list-group list-group-flush support-dashboard-list">
                @forelse($recent as $ticket)
                    @php
                        $badge = match ($ticket->status) {
                            'open' => 'bg-blue-lt',
                            'in_progress' => 'bg-green-lt',
                            'waiting_customer' => 'bg-yellow-lt',
                            default => 'bg-secondary-lt',
                        };
                    @endphp
                    <a class="list-group-item list-group-item-action" href="{{ route('admin.support.index', ['thread' => $ticket->thread?->uuid, 'scope' => 'all']) }}">
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar avatar-sm">{{ collect(explode(' ', $ticket->user?->name ?? '?'))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}</span>
                            <div class="flex-fill min-w-0">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="fw-medium text-truncate">{{ $ticket->user?->name ?? 'Customer' }}</span>
                                    <span class="text-secondary small text-nowrap">{{ $ticket->last_message_at?->diffForHumans() }}</span>
                                </div>
                                <div class="small text-secondary text-truncate">
                                    {{ $ticket->slug }} · {{ $ticket->ticketType?->title ?? 'Support' }} · {{ $ticket->assignee?->name ?? 'Unassigned' }}
                                </div>
                            </div>
                            <span class="badge {{ $badge }} d-none d-sm-inline-flex">{{ str($ticket->status)->replace('_', ' ')->title() }}</span>
                        </div>
                    </a>
                @empty
                    <div class="empty py-5"><p class="empty-subtitle text-secondary">No conversations yet.</p></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
