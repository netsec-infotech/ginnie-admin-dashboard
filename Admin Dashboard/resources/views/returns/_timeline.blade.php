@php
    /** @var array<int, array{key:string,label:string,at:?\Carbon\CarbonInterface,done:bool}> $timelineSteps */

    $lastDoneIndex = -1;
    foreach ($timelineSteps as $i => $s) {
        if ($s['done']) {
            $lastDoneIndex = $i;
        }
    }
@endphp

<div class="return-timeline">
    @foreach ($timelineSteps as $i => $step)
        @php
            $isDone = $step['done'];
            $isCurrent = $i === $lastDoneIndex;
            $isLast = $loop->last;
            // Connector colored only between done steps (and not after the last done step).
            $nextDone = ! $isLast && ($timelineSteps[$i + 1]['done'] ?? false);
            $connectorClass = $nextDone ? 'return-timeline__line return-timeline__line--done' : 'return-timeline__line';
            $dotClass = $isDone
                ? 'return-timeline__dot return-timeline__dot--done'
                : 'return-timeline__dot';
        @endphp
        <div class="return-timeline__row">
            <div class="return-timeline__rail">
                <span class="{{ $dotClass }} {{ $isCurrent ? 'return-timeline__dot--current' : '' }}"></span>
                @unless ($isLast)
                    <span class="{{ $connectorClass }}"></span>
                @endunless
            </div>
            <div class="return-timeline__body">
                <div class="fw-medium {{ $isDone ? '' : 'text-muted' }}">{{ $step['label'] }}</div>
                @if ($step['at'])
                    <small class="text-secondary">
                        {{ $step['at']->diffForHumans() }}
                        · {{ $step['at']->format('Y-m-d H:i') }}
                    </small>
                @elseif ($isDone)
                    {{-- Comment: If step is done without timestamp (e.g., rejected or no refund to customer), show completed action status --}}
                    <small class="text-secondary">{{ __('labels.action_taken') ?? 'Completed' }}</small>
                @else
                    <small class="text-muted">{{ __('labels.pending') }}</small>
                @endif
            </div>
        </div>
    @endforeach
</div>

@once
    @push('styles')
        <style>
            .return-timeline { display: flex; flex-direction: column; }
            .return-timeline__row { display: flex; gap: 0.75rem; }
            .return-timeline__rail {
                display: flex;
                flex-direction: column;
                align-items: center;
                flex-shrink: 0;
                width: 1rem;
            }
            .return-timeline__dot {
                width: 0.75rem; height: 0.75rem; border-radius: 50%;
                background: var(--tblr-secondary-bg-subtle, #e9ecef);
                border: 2px solid var(--tblr-border-color, #dee2e6);
                margin-top: 0.25rem;
                flex-shrink: 0;
            }
            .return-timeline__dot--done {
                background: var(--tblr-success, #2fb344);
                border-color: var(--tblr-success, #2fb344);
            }
            .return-timeline__dot--current {
                box-shadow: 0 0 0 4px rgba(47, 179, 68, 0.2);
            }
            .return-timeline__line {
                flex: 1;
                width: 2px;
                background: var(--tblr-border-color, #dee2e6);
                margin: 0.25rem 0;
                min-height: 1.25rem;
            }
            .return-timeline__line--done {
                background: var(--tblr-success, #2fb344);
            }
            .return-timeline__body {
                padding-bottom: 1rem;
                flex: 1;
            }
            .return-timeline__row:last-child .return-timeline__body {
                padding-bottom: 0;
            }
        </style>
    @endpush
@endonce
