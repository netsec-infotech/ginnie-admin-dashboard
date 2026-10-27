<?php

namespace App\Jobs;

use App\Services\AdEventService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessAdEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param array<int, array{campaign_id: int, visitor_key: string, timestamp: string|null}> $events
     * @param string $eventType  AdEventTypeEnum value ('click' or 'impression').
     */
    public function __construct(
        public array $events,
        public string $eventType,
    ) {
    }

    public function handle(AdEventService $adEventService): void
    {
        $adEventService->processEvents($this->events, $this->eventType);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessAdEventsJob failed permanently', [
            'event_type'  => $this->eventType,
            'event_count' => count($this->events),
            'error'       => $e->getMessage(),
        ]);
    }
}
