@props([
    'status' => null,
    'id' => null,
])

@php
    $value = is_object($status) ? ($status->value ?? 'pending') : ($status ?: 'pending');
    $class = match ($value) {
        'completed'  => 'bg-success-lt',
        'processing' => 'bg-info-lt',
        'failed'     => 'bg-danger-lt',
        'cancelled'  => 'bg-warning-lt',
        default      => 'bg-secondary-lt',
    };
@endphp

<span @if($id) id="{{ $id }}" @endif class="badge {{ $class }}">
    {{ __('labels.bulk_upload_status_' . $value) }}
</span>
