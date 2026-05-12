<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_important_dates', function (Blueprint $table) {
            $table->foreignId('calendar_event_id')
                  ->nullable()
                  ->after('due_date')
                  ->constrained('events')
                  ->nullOnDelete();
            $table->index(['case_id', 'calendar_event_id'], 'idx_cid_ceid');
        });
    }

    public function down(): void
    {
        Schema::table('case_important_dates', function (Blueprint $table) {
            $table->dropForeign(['calendar_event_id']);
            $table->dropIndex('idx_cid_ceid');
            $table->dropColumn('calendar_event_id');
        });
    }
};
