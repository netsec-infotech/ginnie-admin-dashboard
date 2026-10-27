<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('support_ticket_ratings', 'agent_id')) {
            Schema::table('support_ticket_ratings', function (Blueprint $table) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('user_id');
                $table->index(['agent_id', 'created_at'], 'support_ratings_agent_created_idx');
                $table->foreign('agent_id', 'support_ratings_agent_foreign')->references('id')->on('users')->nullOnDelete();
            });
        }

        DB::table('support_ticket_ratings')
            ->whereNull('agent_id')
            ->orderBy('id')
            ->chunkById(200, function ($ratings) {
                $tickets = DB::table('support_tickets')
                    ->whereIn('id', $ratings->pluck('ticket_id'))
                    ->get(['id', 'assigned_to', 'resolved_by'])
                    ->keyBy('id');
                $adminIds = DB::table('users')
                    ->whereIn('id', $tickets->flatMap(fn ($ticket) => [$ticket->assigned_to, $ticket->resolved_by])->filter()->unique())
                    ->where('access_panel', 'admin')
                    ->pluck('id')
                    ->flip();

                foreach ($ratings as $rating) {
                    $ticket = $tickets->get($rating->ticket_id);
                    $agentId = collect([$ticket?->assigned_to, $ticket?->resolved_by])
                        ->filter()
                        ->first(fn ($userId) => $adminIds->has($userId));

                    if ($agentId) {
                        DB::table('support_ticket_ratings')->where('id', $rating->id)->update(['agent_id' => $agentId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_ticket_ratings', 'agent_id')) {
            Schema::table('support_ticket_ratings', function (Blueprint $table) {
                $table->dropForeign('support_ratings_agent_foreign');
                $table->dropIndex('support_ratings_agent_created_idx');
                $table->dropColumn('agent_id');
            });
        }
    }
};
