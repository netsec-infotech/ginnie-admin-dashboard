<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class BroadcastDriverSettingType implements SettingInterface
{
    use SettingTrait;

    // Default broadcast driver set to pusher as requested
    public string $broadcastDriver = 'pusher';

    public string $allowedOrigins = 'localhost';

    public string $reverbAppId = '';

    public string $reverbKey = '';

    public string $reverbSecret = '';

    public string $reverbHost = '';

    public int $reverbPort = 443;

    public string $reverbScheme = 'https';

    public string $reverbServerHost = '0.0.0.0';

    public int $reverbServerPort = 8080;

    public string $pusherAppId = '';

    public string $pusherKey = '';

    public string $pusherSecret = '';

    public string $pusherCluster = 'mt1';

    protected static function getValidationRules(): array
    {
        return [
            'broadcastDriver' => 'required|in:reverb,pusher',
            'allowedOrigins' => 'required_if:broadcastDriver,reverb|nullable|string|max:2000',
            'reverbAppId' => 'required_if:broadcastDriver,reverb|nullable|string|max:191',
            'reverbKey' => 'required_if:broadcastDriver,reverb|nullable|string|max:191',
            'reverbSecret' => 'required_if:broadcastDriver,reverb|nullable|string|max:500',
            'reverbHost' => ['required_if:broadcastDriver,reverb', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.-]+$/'],
            'reverbPort' => 'required_if:broadcastDriver,reverb|nullable|integer|between:1,65535',
            'reverbScheme' => 'required_if:broadcastDriver,reverb|nullable|in:http,https',
            'reverbServerHost' => ['required_if:broadcastDriver,reverb', 'nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.:-]+$/'],
            'reverbServerPort' => 'required_if:broadcastDriver,reverb|nullable|integer|between:1,65535',
            'pusherAppId' => 'required_if:broadcastDriver,pusher|nullable|string|max:191',
            'pusherKey' => 'required_if:broadcastDriver,pusher|nullable|string|max:191',
            'pusherSecret' => 'required_if:broadcastDriver,pusher|nullable|string|max:500',
            'pusherCluster' => ['required_if:broadcastDriver,pusher', 'nullable', 'string', 'max:30', 'regex:/^[a-zA-Z0-9-]+$/'],
        ];
    }
}
