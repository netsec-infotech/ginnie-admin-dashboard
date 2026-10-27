@php
    $formatDuration = static function (int $seconds): string {
        if ($seconds < 60) return $seconds.' sec';
        if ($seconds < 3600) return intdiv($seconds, 60).' min';
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours.' hr'.($minutes > 0 ? ' '.$minutes.' min' : '');
    };
    $isLate = $queue === 'late';
@endphp

@forelse($items as $item)
    <a class="list-group-item list-group-item-action support-dashboard-queue-item"
       href="{{ route('admin.support.index', ['thread' => $item['thread_uuid'], 'scope' => 'all']) }}">
        <div class="d-flex align-items-center gap-3">
            @if($isLate)
                <span class="avatar avatar-sm bg-danger-lt text-danger"><i class="ti ti-alarm"></i></span>
            @else
                <span class="avatar avatar-sm bg-primary-lt">{{ collect(explode(' ', $item['customer']))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}</span>
            @endif
            <div class="flex-fill min-w-0">
                <div class="d-flex justify-content-between gap-2">
                    <span class="{{ $isLate ? 'fw-semibold' : 'fw-medium' }} text-truncate">{{ $item['customer'] }}</span>
                    <span class="support-dashboard-wait {{ $isLate ? 'badge bg-danger-lt text-danger' : 'small text-warning' }} text-nowrap"
                          data-wait-since="{{ $item['waiting_since'] }}">{{ $formatDuration($item['wait_seconds']) }}</span>
                </div>
                <div class="small text-secondary text-truncate">
                    {{ $item['slug'] }}@unless($isLate) · {{ $item['topic'] }}@endunless · {{ $item['assignee'] ?? 'Unassigned' }}
                </div>
                <div class="small text-truncate mt-1">{{ $item['preview'] }}</div>
            </div>
        </div>
    </a>
@empty
    @if($showEmpty ?? true)
        <div class="empty py-5">
            <div class="empty-icon">
                <i class="ti {{ $isLate ? 'ti-clock-check' : 'ti-circle-check' }} text-success"></i>
            </div>
            <p class="empty-title">{{ $isLate ? 'No late replies' : 'No customers waiting' }}</p>
            <p class="empty-subtitle text-secondary">
                {{ $isLate ? 'No customer has crossed the '.$lateReplyMinutes.'-minute threshold.' : 'Every active customer message has a reply.' }}
            </p>
        </div>
    @endif
@endforelse
