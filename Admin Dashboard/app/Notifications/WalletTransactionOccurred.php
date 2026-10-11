<?php

namespace App\Notifications;

use App\Broadcasting\FirebaseChannel;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Notification\NotificationRoleTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\WalletTransaction;
use App\Services\CurrencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WalletTransactionOccurred extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected WalletTransaction $transaction) {}

    public function via(object $notifiable): array
    {
        return [\App\Notifications\Channels\RoleAwareDatabaseChannel::class, 'mail', FirebaseChannel::class];
    }

    public function toMail(object $notifiable): ?MailMessage
    {
        try {
            $t = $this->transaction;

            return (new MailMessage)
                ->subject('Wallet Transaction '.ucfirst((string) $t->status))
                ->greeting('Hello '.($notifiable->name ?? ''))
                ->line('A wallet transaction has been recorded on your account:')
                ->line('Type: '.(string) $t->transaction_type)
                ->line('Amount: '.app(CurrencyService::class)->getSymbol().number_format((float) $t->amount, 2))
                ->line('Status: '.ucfirst((string) $t->status))
                ->line('Description: '.($t->description ?: '-'))
                ->line('Date: '.optional($t->created_at)->toDateTimeString());
        } catch (\Throwable $e) {
            Log::error('WalletTransactionOccurred mail failed: '.$e->getMessage());

            return null;
        }
    }

    public function toFirebase($notifiable): array
    {
        $t = $this->transaction;
        $transactionMethod = str_replace('_', ' ', (string) $t->payment_method);
        $roleType = $this->resolveRoleType($notifiable);

        return [
            'title' => $transactionMethod == 'referral' ? 'Referral amount'.ucfirst((string) $t->transaction_type) : 'Wallet '.ucfirst((string) $t->transaction_type),
            'body' => 'Amount '.app(CurrencyService::class)->getSymbol().number_format((float) $t->amount, 2).' | '.ucfirst((string) $t->status),
            'image' => null,
            'data' => [
                'type' => $transactionMethod == 'referral' ? NotificationTypeEnum::REFER_TRANSACTION() : NotificationTypeEnum::WALLET_TRANSACTION(),
                'wallet_transaction_id' => $t->id,
                'transaction_type' => (string) $t->transaction_type,
                'status' => (string) $t->status,
                'role_type' => $roleType->value,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        $t = $this->transaction;

        return [
            'wallet_transaction_id' => $t->id,
            'amount' => (float) $t->amount,
            'transaction_type' => (string) $t->transaction_type,
            'status' => (string) $t->status,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $t = $this->transaction;
        $transactionPaymentMethod = (string) $t->payment_method;
        $transactionMethod = str_replace('_', ' ', (string) $t->payment_method);

         // default message
        $message = 'A wallet transaction has occurred. Amount: '
            . app(CurrencyService::class)->getSymbol()
            . number_format((float) $t->amount, 2);

        // if wallet transfer
        if ($transactionPaymentMethod == 'earning_wallet_transfer')
            {
                $message = 'Amount ' .  app(CurrencyService::class)->getSymbol()
            . number_format((float) $t->amount, 2) .  ' transferred from Earning Wallet to Ad Wallet.';
            }

        if ($transactionPaymentMethod == 'ad_wallet_transfer') {
            $message = "Amount " . app(CurrencyService::class)->getSymbol() . number_format((float) $t->amount, 2) . " received in Ad Wallet from Earning Wallet.";
        }
        $roleType = $this->resolveRoleType($notifiable);

        return [
            'title' => $transactionMethod == 'referral' ? 'Referral amount'.ucfirst((string) $t->transaction_type) : 'Wallet '.ucfirst((string) $t->transaction_type),
            'message' => $message,
            'type' => $transactionMethod == 'referral' ? NotificationTypeEnum::REFER_TRANSACTION() : NotificationTypeEnum::WALLET_TRANSACTION(),
            'sent_to' => $this->sentToFor($roleType),
            'role_type' => $roleType->value,
            'user_id' => $notifiable->id ?? null,
            'store_id' => $t->store_id ?? null,
            'order_id' => $t->order_id ?? null,
            'metadata' => [
                'wallet_transaction_id' => $t->id,
                'transaction_type' => (string) $t->transaction_type,
                'status' => (string) $t->status,
            ],
        ];
    }

    /**
     * Derive recipient role from the notifiable. Wallet transactions can target
     * sellers, customers, or (rarely) admins — the static 'seller' default was
     * a bug.
     */
    protected function resolveRoleType(object $notifiable): NotificationRoleTypeEnum
    {
        if (method_exists($notifiable, 'hasRole')) {
            if ($notifiable->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
                return NotificationRoleTypeEnum::ADMIN;
            }
            if ($notifiable->hasRole(DefaultSystemRolesEnum::SELLER())) {
                return NotificationRoleTypeEnum::SELLER;
            }
        }

        return NotificationRoleTypeEnum::CUSTOMER;
    }

    protected function sentToFor(NotificationRoleTypeEnum $roleType): string
    {
        return match ($roleType) {
            NotificationRoleTypeEnum::ADMIN => 'admin',
            NotificationRoleTypeEnum::SELLER => 'seller',
            NotificationRoleTypeEnum::RIDER => 'delivery_boy',
            default => 'customer',
        };
    }
}
