@php
    use Illuminate\Support\Str;
    /**
     * Renders a single order_audit_logs entry. Database stores old_value and
     * new_value as JSON; this partial unpacks them into human-readable lines
     * per action so the UI never shows raw braces / quotes to admins.
     *
     * @var \App\Models\OrderAuditLog $log
     */

    $action = (string) $log->action;
    $old = is_array($log->old_value) ? $log->old_value : (array) ($log->old_value ?? []);
    $new = is_array($log->new_value) ? $log->new_value : (array) ($log->new_value ?? []);

    $badgeClass = match ($action) {
        'force_cancel'         => 'bg-red-lt text-red',
        'force_status'         => 'bg-orange-lt text-orange',
        'force_refund'         => 'bg-green-lt text-green',
        'reassign_rider'       => 'bg-blue-lt text-blue',
        'add_note'             => 'bg-purple-lt text-purple',
        'escalation_flagged'   => 'bg-red-lt text-red',
        default                => 'bg-secondary-lt text-secondary',
    };
    $iconClass = match ($action) {
        'force_cancel'         => 'ti-ban',
        'force_status'         => 'ti-arrows-exchange',
        'force_refund'         => 'ti-receipt-refund',
        'reassign_rider'       => 'ti-users',
        'add_note'             => 'ti-note',
        'escalation_flagged'   => 'ti-alert-triangle',
        default                => 'ti-history',
    };
    $actionLabel = Str::title(str_replace('_', ' ', $action));

    $details = [];

    if ($action === 'force_status') {
        $oldStatus = $old['status'] ?? null;
        $newStatus = $new['status'] ?? null;
        if ($oldStatus || $newStatus) {
            $details[] = [
                'label' => __('labels.status'),
                'value' => Str::title(str_replace('_', ' ', (string) $oldStatus))
                    . ' → ' . Str::title(str_replace('_', ' ', (string) $newStatus)),
            ];
        }
    } elseif ($action === 'force_cancel') {
        if (!empty($old['status'])) {
            $details[] = [
                'label' => __('labels.previous_status'),
                'value' => Str::title(str_replace('_', ' ', (string) $old['status'])),
            ];
        }
    } elseif ($action === 'force_refund') {
        if (isset($new['amount'])) {
            $sym = $systemSettings['currencySymbol'] ?? '';
            $details[] = [
                'label' => __('labels.amount'),
                'value' => $sym . number_format((float) $new['amount'], 2),
            ];
        }
    } elseif ($action === 'reassign_rider') {
        $oldRider = $old['delivery_boy_id'] ?? null;
        $newRider = $new['delivery_boy_id'] ?? null;
        $details[] = [
            'label' => __('labels.rider'),
            'value' => ($oldRider ? '#' . $oldRider : __('labels.none'))
                . ' → ' . ($newRider ? '#' . $newRider : __('labels.none')),
        ];
        $resetIds = $old['reset_collected_item_ids'] ?? null;
        if (!empty($resetIds) && is_array($resetIds)) {
            $details[] = [
                'label' => __('labels.items_reset'),
                'value' => '#' . implode(', #', $resetIds),
            ];
        }
    } elseif ($action === 'escalation_flagged') {
        $reasons = $new['new_reasons'] ?? ($new['all_reasons'] ?? []);
        if (!empty($reasons)) {
            $reasonText = collect((array) $reasons)
                ->map(fn ($r) => __('labels.' . $r) !== 'labels.' . $r ? __('labels.' . $r) : Str::title(str_replace('_', ' ', $r)))
                ->join(', ');
            $details[] = [
                'label' => __('labels.reason'),
                'value' => $reasonText,
            ];
        }
        // If the seller_unresponsive payload carried store_ids on the order
        // (we only know about it via $order in the parent view), include the
        // raw ids here for posterity. Names are resolved on the live banner;
        // the audit log keeps the underlying ids so it stays self-contained.
        if (!empty($new['store_ids']) && is_array($new['store_ids'])) {
            $details[] = [
                'label' => __('labels.stores'),
                'value' => '#' . implode(', #', $new['store_ids']),
            ];
        }
    }
@endphp

<div class="list-group-item">
    <div class="row g-3 align-items-start">
        <div class="col-auto">
            <span class="avatar avatar-sm {{ $badgeClass }}">
                <i class="ti {{ $iconClass }}"></i>
            </span>
        </div>
        <div class="col">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge {{ $badgeClass }} text-uppercase">{{ $actionLabel }}</span>
                <span class="text-secondary small">
                    {{ optional($log->admin)->name ?? __('labels.system') }}
                    · <span title="{{ $log->created_at?->toDateTimeString() }}">{{ $log->created_at?->diffForHumans() }}</span>
                </span>
            </div>

            @if(!empty($log->reason))
                <div class="mt-1">{{ $log->reason }}</div>
            @endif

            @if(!empty($details))
                <ul class="list-unstyled small text-secondary mt-1 mb-0">
                    @foreach($details as $detail)
                        <li>
                            <span class="me-1">{{ $detail['label'] }}:</span>
                            <span class="text-body">{{ $detail['value'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
