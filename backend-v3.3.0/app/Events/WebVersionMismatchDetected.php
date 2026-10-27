<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an outdated web application version is detected during a version check.
 */
class WebVersionMismatchDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $clientVersion Version reported by the web client.
     * @param string $latestVersion Current active version on the server.
     * @param string|null $ipAddress Client IP address.
     * @param string|null $userAgent Client browser User-Agent header.
     */
    public function __construct(
        public string $clientVersion,
        public string $latestVersion,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {}
}
