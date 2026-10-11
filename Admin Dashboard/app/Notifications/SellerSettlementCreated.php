<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Models\SellerStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SellerSettlementCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected SellerStatement $statement) {}

    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\RoleAwareDatabaseChannel::class, 'mail', FirebaseChannel::class];
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            $s = $this->statement;
            $currencySymbol = app(\App\Services\CurrencyService::class)->getSymbol();
            $sellerName = $s->seller?->user?->name ?? ('Seller #'.$s->seller_id);
            $isAdmin = method_exists($notifiable, 'hasRole') && $notifiable->hasRole(\App\Enums\DefaultSystemRolesEnum::SUPER_ADMIN());
            $isDebit = (string) $s->entry_type === SellerSettlementTypeEnum::DEBIT();

            $mail = (new MailMessage)
                ->subject($isDebit ? 'Seller Amount Due to Admin' : ($isAdmin ? 'New Seller Settlement Entry' : 'Earning Added to Settlement'))
                ->greeting('Hello '.($notifiable->name ?? ''));

            if ($isAdmin) {
                $mail->line($isDebit ? 'A seller amount is pending collection.' : 'A new seller settlement entry has been created.')
                    ->line('Seller: '.$sellerName)
                    ->line('Amount: '.$currencySymbol.number_format((float) $s->amount, 2))
                    ->line('Reference: '.($s->description ?: '-'))
                    ->line('Order ID: '.($s->order_id ?? '-'))
                    ->line('Created At: '.optional($s->created_at)->toDateTimeString());
            } else {
                $mail->line($isDebit ? 'An amount is due to admin for this order.' : 'A new earning entry has been added to your settlement.')
                    ->line('Amount: '.$currencySymbol.number_format((float) $s->amount, 2))
                    ->line('Reference: '.($s->description ?: '-'))
                    ->line('Order ID: '.($s->order_id ?? '-'));

                if ($isDebit) {
                    $mail->line('Please settle this amount with admin.');
                } else {
                    $mail->line('We will notify you when it is settled to your wallet.');
                }
            }

            return $mail;
        } catch (\Throwable $e) {
            Log::error('SellerSettlementCreated mail failed: '.$e->getMessage());

            return null;
        }
    }

    public function toFirebase($notifiable): array
    {
        $s = $this->statement;
        $currencySymbol = app(\App\Services\CurrencyService::class)->getSymbol();
        $isAdmin = method_exists($notifiable, 'hasRole') && $notifiable->hasRole(\App\Enums\DefaultSystemRolesEnum::SUPER_ADMIN());
        $sellerName = $s->seller?->user?->name ?? ('Seller #'.$s->seller_id);
        $isDebit = (string) $s->entry_type === SellerSettlementTypeEnum::DEBIT();

        return [
            'title' => $isDebit ? 'Amount Due to Admin' : ($isAdmin ? 'New Settlement Entry' : 'New Earning Added'),
            'body' => $isAdmin
                ? ($sellerName.' | '.$currencySymbol.number_format((float) $s->amount, 2).($isDebit ? ' pending collection.' : ''))
                : ($isDebit
                    ? ('Please settle '.$currencySymbol.number_format((float) $s->amount, 2).' with admin.')
                    : ('Amount '.$currencySymbol.number_format((float) $s->amount, 2).' added.')),
            'image' => null,
            'data' => [
                'type' => NotificationTypeEnum::SETTLEMENT_CREATE(),
                'seller_statement_id' => $s->id,
                'order_id' => $s->order_id,
                'status' => (string) $s->settlement_status,
                'role_type' => $isAdmin ? NotificationRoleTypeEnum::ADMIN() : NotificationRoleTypeEnum::SELLER(),
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        $s = $this->statement;

        return [
            'seller_statement_id' => $s->id,
            'amount' => (float) $s->amount,
            'status' => (string) $s->settlement_status,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $s = $this->statement;
        $currencySymbol = app(\App\Services\CurrencyService::class)->getSymbol();
        $isAdmin = method_exists($notifiable, 'hasRole') && $notifiable->hasRole(\App\Enums\DefaultSystemRolesEnum::SUPER_ADMIN());
        $sellerName = $s->seller?->user?->name ?? ('Seller #'.$s->seller_id);
        $isDebit = (string) $s->entry_type === SellerSettlementTypeEnum::DEBIT();

        return [
            'title' => $isDebit ? 'Amount Due to Admin' : ($isAdmin ? 'New Settlement Entry' : 'New Earning Added'),
            'message' => $isAdmin
                ? ($isDebit
                    ? ('Collect '.$currencySymbol.number_format((float) $s->amount, 2).' from '.$sellerName.'.')
                    : ('New settlement for '.$sellerName.' amount '.$currencySymbol.number_format((float) $s->amount, 2).'.'))
                : ($isDebit
                    ? ('Please settle '.$currencySymbol.number_format((float) $s->amount, 2).' with admin.')
                    : ('Earning amount '.$currencySymbol.number_format((float) $s->amount, 2).' added to your settlement.')),
            'type' => NotificationTypeEnum::SETTLEMENT_CREATE(),
            'sent_to' => $isAdmin ? 'admin' : 'seller',
            'role_type' => $isAdmin ? NotificationRoleTypeEnum::ADMIN() : NotificationRoleTypeEnum::SELLER(),
            'user_id' => $notifiable->id ?? null,
            'store_id' => $s->seller_id ?? null,
            'order_id' => $s->order_id ?? null,
            'metadata' => [
                'seller_statement_id' => $s->id,
                'entry_type' => (string) $s->entry_type,
                'description' => $s->description,
            ],
        ];
    }
}
