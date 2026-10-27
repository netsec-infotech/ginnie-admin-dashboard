<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_types', function (Blueprint $table) {
            $table->string('slug', 191)->nullable()->after('title');
            $table->string('context', 30)->default('both')->after('slug');
            $table->text('guidance')->nullable()->after('context');
            $table->boolean('is_active')->default(true)->after('guidance');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });

        foreach (DB::table('support_ticket_types')->orderBy('id')->get(['id', 'title']) as $type) {
            $base = Str::slug($type->title) ?: 'topic-'.$type->id;
            $slug = $base;
            $suffix = 2;

            while (DB::table('support_ticket_types')->where('slug', $slug)->where('id', '!=', $type->id)->exists()) {
                $slug = $base.'-'.$suffix++;
            }

            DB::table('support_ticket_types')->where('id', $type->id)->update(['slug' => $slug]);
        }

        Schema::table('support_ticket_types', function (Blueprint $table) {
            $table->unique('slug', 'support_ticket_types_slug_unique');
        });

        if (DB::table('support_ticket_types')->count() === 0) {
            $now = now();
            DB::table('support_ticket_types')->insert([
                [
                    'title' => 'Delivery or tracking',
                    'slug' => 'delivery-tracking',
                    'context' => 'order',
                    'guidance' => 'Check the latest tracking status first. If the delivery window has passed, tell us whether it is late, missing, or marked delivered.',
                    'is_active' => true,
                    'sort_order' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'title' => 'Damaged or wrong item',
                    'slug' => 'damaged-wrong-item',
                    'context' => 'order',
                    'guidance' => 'Choose what went wrong and attach clear photos of the item, packaging, and label when available.',
                    'is_active' => true,
                    'sort_order' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'title' => 'Return or refund',
                    'slug' => 'return-refund',
                    'context' => 'order',
                    'guidance' => 'Select the return or refund stage. Keep the item and packaging until support confirms the next step.',
                    'is_active' => true,
                    'sort_order' => 30,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'title' => 'Payment issue',
                    'slug' => 'payment-issue',
                    'context' => 'both',
                    'guidance' => 'Do not share card numbers, CVV, OTP, or banking passwords. Tell us only what happened to the payment.',
                    'is_active' => true,
                    'sort_order' => 40,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'title' => 'Account or other help',
                    'slug' => 'general-help',
                    'context' => 'general',
                    'guidance' => 'Choose the closest option or describe the issue in your own words. Never share passwords or verification codes.',
                    'is_active' => true,
                    'sort_order' => 50,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        Schema::create('support_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('active_ticket_id')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('thread_id')->nullable()->after('id');
            $table->unsignedBigInteger('parent_ticket_id')->nullable()->after('thread_id');
            $table->unsignedBigInteger('order_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('order_item_id')->nullable()->after('order_id');
            $table->unsignedBigInteger('assigned_to')->nullable()->after('order_item_id');
            $table->text('resolution_summary')->nullable()->after('description');
            $table->unsignedBigInteger('resolved_by')->nullable()->after('resolution_summary');
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
            $table->timestamp('last_message_at')->nullable()->after('closed_at');
            $table->index(['thread_id', 'status'], 'support_tickets_thread_status_idx');
            $table->index(['assigned_to', 'status'], 'support_tickets_assignee_status_idx');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('status', 30)->default('open')->change();
        });

        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->string('send_by', 20)->change();
            $table->uuid('client_message_id')->nullable()->after('ticket_id');
            $table->string('type', 30)->default('text')->after('client_message_id');
            $table->json('metadata')->nullable()->after('message');
            $table->unique(['ticket_id', 'client_message_id'], 'support_messages_ticket_client_unique');
            $table->index(['ticket_id', 'id'], 'support_messages_ticket_cursor_idx');
        });

        Schema::create('support_thread_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['thread_id', 'user_id']);
            $table->foreign('thread_id')->references('id')->on('support_threads')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('support_ticket_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status', 30)->default('requested');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->text('outcome')->nullable();
            $table->text('private_note')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
            $table->foreign('ticket_id')->references('id')->on('support_tickets')->cascadeOnDelete();
        });

        Schema::create('support_ticket_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('score');
            $table->text('feedback')->nullable();
            $table->timestamps();
            $table->foreign('ticket_id')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        $tickets = DB::table('support_tickets')->orderBy('user_id')->orderBy('id')->get();
        foreach ($tickets->groupBy('user_id') as $userId => $userTickets) {
            if (! DB::table('users')->where('id', $userId)->exists()) {
                continue;
            }

            $threadId = DB::table('support_threads')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'last_message_at' => $userTickets->max('updated_at'),
                'created_at' => $userTickets->min('created_at') ?: now(),
                'updated_at' => now(),
            ]);

            $previousTicketId = null;
            foreach ($userTickets as $ticket) {
                $status = match ($ticket->status) {
                    'reopen' => 'in_progress',
                    'pending_review' => 'waiting_customer',
                    default => $ticket->status,
                };
                DB::table('support_tickets')->where('id', $ticket->id)->update([
                    'thread_id' => $threadId,
                    'parent_ticket_id' => $previousTicketId,
                    'status' => $status,
                    'last_message_at' => $ticket->updated_at,
                    'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? $ticket->updated_at : null,
                    'closed_at' => $status === 'closed' ? $ticket->updated_at : null,
                ]);
                $previousTicketId = $ticket->id;
            }

            $active = DB::table('support_tickets')
                ->where('thread_id', $threadId)
                ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
                ->orderByDesc('id')
                ->first();

            if ($active) {
                DB::table('support_tickets')
                    ->where('thread_id', $threadId)
                    ->whereIn('status', ['open', 'in_progress', 'waiting_customer'])
                    ->where('id', '!=', $active->id)
                    ->update(['status' => 'resolved', 'resolved_at' => now()]);
                DB::table('support_threads')->where('id', $threadId)->update(['active_ticket_id' => $active->id]);
            }
        }

        Schema::table('support_threads', function (Blueprint $table) {
            $table->foreign('active_ticket_id')->references('id')->on('support_tickets')->nullOnDelete();
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreign('thread_id')->references('id')->on('support_threads')->cascadeOnDelete();
            $table->foreign('parent_ticket_id')->references('id')->on('support_tickets')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('order_item_id')->references('id')->on('order_items')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('support_ticket_messages')->where('send_by', 'system')->delete();
        DB::table('support_tickets')->where('status', 'waiting_customer')->update(['status' => 'pending_review']);
        Schema::table('support_threads', fn (Blueprint $table) => $table->dropForeign(['active_ticket_id']));
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['thread_id']);
            $table->dropForeign(['parent_ticket_id']);
            $table->dropForeign(['order_id']);
            $table->dropForeign(['order_item_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['resolved_by']);
        });
        Schema::dropIfExists('support_ticket_ratings');
        Schema::dropIfExists('support_ticket_calls');
        Schema::dropIfExists('support_thread_reads');
        Schema::dropIfExists('support_threads');
        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->dropUnique('support_messages_ticket_client_unique');
            $table->dropIndex('support_messages_ticket_cursor_idx');
            $table->dropColumn(['client_message_id', 'type', 'metadata']);
            $table->enum('send_by', ['admin', 'user'])->change();
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex('support_tickets_thread_status_idx');
            $table->dropIndex('support_tickets_assignee_status_idx');
            $table->dropColumn([
                'thread_id', 'parent_ticket_id', 'order_id', 'order_item_id', 'assigned_to',
                'resolution_summary', 'resolved_by', 'resolved_at', 'closed_at', 'last_message_at',
            ]);
            $table->enum('status', ['open', 'in_progress', 'reopen', 'pending_review', 'resolved', 'closed'])->default('open')->change();
        });
        Schema::table('support_ticket_types', function (Blueprint $table) {
            $table->dropUnique('support_ticket_types_slug_unique');
            $table->dropColumn(['slug', 'context', 'guidance', 'is_active', 'sort_order']);
        });
    }
};
