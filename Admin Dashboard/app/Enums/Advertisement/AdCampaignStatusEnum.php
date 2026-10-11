<?php

namespace App\Enums\Advertisement;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Lifecycle of an ad campaign.
 *
 *  draft            → Seller saved but not submitted for review.
 *  pending_approval → Seller submitted; awaiting admin review.
 *  approved         → Admin approved; budget deducted from ad wallet; campaign enters serving.
 *  rejected         → Admin rejected; budget NOT deducted; seller sees rejection reason.
 *  running          → Actively serving impressions and clicks.
 *  paused           → Seller voluntarily paused; resumes from the same budget.
 *  paused_by_admin  → Admin / master-toggle paused; seller cannot unpause without admin action.
 *  completed        → Budget fully spent; campaign archived.
 *  force_stopped    → Admin force-stopped with a mandatory reason; final state.
 *
 * @method static DRAFT()
 * @method static PENDING_APPROVAL()
 * @method static APPROVED()
 * @method static REJECTED()
 * @method static RUNNING()
 * @method static PAUSED()
 * @method static PAUSED_BY_ADMIN()
 * @method static COMPLETED()
 * @method static FORCE_STOPPED()
 */
enum AdCampaignStatusEnum: string
{
    use InvokableCases, Values, Names;

    case DRAFT           = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED        = 'approved';
    case REJECTED        = 'rejected';
    case RUNNING         = 'running';
    case PAUSED          = 'paused';
    case PAUSED_BY_ADMIN = 'paused_by_admin';
    case COMPLETED       = 'completed';
    case FORCE_STOPPED   = 'force_stopped';

    /** Statuses the seller can see as "active" (budget is locked in). */
    public static function activeLocked(): array
    {
        return [
            self::APPROVED(),
            self::RUNNING(),
            self::PAUSED(),
            self::PAUSED_BY_ADMIN(),
        ];
    }

    /** Statuses considered terminal (no further transitions allowed). */
    public static function terminal(): array
    {
        return [
            self::REJECTED(),
            self::COMPLETED(),
            self::FORCE_STOPPED(),
        ];
    }

    /** Human-readable label for UI badges. */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT            => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED         => 'Approved',
            self::REJECTED         => 'Rejected',
            self::RUNNING          => 'Running',
            self::PAUSED           => 'Paused',
            self::PAUSED_BY_ADMIN  => 'Paused by Admin',
            self::COMPLETED        => 'Completed',
            self::FORCE_STOPPED    => 'Force Stopped',
        };
    }

    /** Bootstrap colour class for the status badge. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT            => 'secondary',
            self::PENDING_APPROVAL => 'warning',
            self::APPROVED         => 'info',
            self::REJECTED         => 'danger',
            self::RUNNING          => 'success',
            self::PAUSED           => 'warning',
            self::PAUSED_BY_ADMIN  => 'danger',
            self::COMPLETED        => 'secondary',
            self::FORCE_STOPPED    => 'danger',
        };
    }
}
