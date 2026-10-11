<?php

namespace App\Models;

use App\Enums\CommandRunStatusEnum;
use App\Enums\CommandRunTriggerEnum;
use Illuminate\Database\Eloquent\Model;

class CommandRunLog extends Model
{
    protected $fillable = [
        'command',
        'status',
        'triggered_by',
        'started_at',
        'finished_at',
        'duration_ms',
        'output',
    ];

    protected $casts = [
        'status' => CommandRunStatusEnum::class,
        'triggered_by' => CommandRunTriggerEnum::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
    ];

    public function scopeForCommand($query, string $command)
    {
        return $query->where('command', $command);
    }

    public function scopeLatestRuns($query, int $limit = 10)
    {
        return $query->orderByDesc('started_at')->limit($limit);
    }
}
