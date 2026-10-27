<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_ticket_messages')) {
            if (! Schema::hasColumn('support_ticket_messages', 'deleted_by')) {
                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->unsignedBigInteger('deleted_by')->nullable()->after('metadata');
                    $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('support_ticket_messages', 'deleted_at')) {
                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }

        if (! Schema::hasTable('support_ticket_types')) {
            return;
        }

        if (! Schema::hasColumn('support_ticket_types', 'customer_quick_replies')) {
            Schema::table('support_ticket_types', function (Blueprint $table) {
                $table->json('customer_quick_replies')->nullable()->after('guidance');
            });
        }

        if (! Schema::hasColumn('support_ticket_types', 'agent_quick_replies')) {
            Schema::table('support_ticket_types', function (Blueprint $table) {
                $table->json('agent_quick_replies')->nullable()->after('customer_quick_replies');
            });
        }

        if (! Schema::hasColumn('support_ticket_types', 'slug')) {
            return;
        }

        foreach ($this->customerDefaults() as $slug => $quickReplies) {
            DB::table('support_ticket_types')
                ->where('slug', $slug)
                ->whereNull('customer_quick_replies')
                ->update(['customer_quick_replies' => json_encode($quickReplies, JSON_THROW_ON_ERROR)]);
        }

        foreach ($this->agentDefaults() as $slug => $quickReplies) {
            DB::table('support_ticket_types')
                ->where('slug', $slug)
                ->whereNull('agent_quick_replies')
                ->update(['agent_quick_replies' => json_encode($quickReplies, JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('support_ticket_messages')) {
            if (Schema::hasColumn('support_ticket_messages', 'deleted_by')) {
                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->dropForeign(['deleted_by']);
                    $table->dropColumn('deleted_by');
                });
            }

            if (Schema::hasColumn('support_ticket_messages', 'deleted_at')) {
                Schema::table('support_ticket_messages', function (Blueprint $table) {
                    $table->dropColumn('deleted_at');
                });
            }
        }

        if (! Schema::hasTable('support_ticket_types')) {
            return;
        }

        foreach (['agent_quick_replies', 'customer_quick_replies'] as $column) {
            if (Schema::hasColumn('support_ticket_types', $column)) {
                Schema::table('support_ticket_types', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function customerDefaults(): array
    {
        return [
            'delivery-tracking' => [
                'My order is late',
                'Tracking has not updated',
                'Order is marked delivered but I did not receive it',
                'I received only part of my order',
            ],
            'damaged-wrong-item' => [
                'I received a damaged item',
                'I received the wrong item',
                'An item is missing',
                'The item quality is not acceptable',
            ],
            'return-refund' => [
                'I want to return an item',
                'My return pickup is delayed',
                'My refund is pending',
                'I need a replacement',
            ],
            'payment-issue' => [
                'I was charged but the order failed',
                'I was charged more than once',
                'My payment is still pending',
                'I cannot complete payment',
            ],
            'general-help' => [
                'I need help with my account',
                'I have a promo or gift card issue',
                'I need help with an address',
                'My issue is not listed',
            ],
        ];
    }

    private function agentDefaults(): array
    {
        return [
            'delivery-tracking' => [
                'I am checking the latest delivery update for you.',
                'Please allow me a moment to review the tracking details.',
            ],
            'damaged-wrong-item' => [
                'Please share a clear photo of the item and packaging.',
                'I am checking the available replacement or refund options.',
            ],
            'return-refund' => [
                'I am checking your return and refund status.',
                'Please allow me a moment to review the available next steps.',
            ],
            'payment-issue' => [
                'I am checking the payment status for you.',
                'Please do not share card details, CVV, OTP, or banking passwords.',
            ],
            'general-help' => [
                'Thank you for the details. I am checking this for you.',
                'Please allow me a moment to review your account.',
            ],
        ];
    }
};
