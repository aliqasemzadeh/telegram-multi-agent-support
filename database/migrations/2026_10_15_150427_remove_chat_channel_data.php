<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('agent_schedule_slots')
            ->where('channel', 'chat')
            ->delete();

        if (Schema::hasColumn('users', 'supports_chat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('supports_chat');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'supports_chat')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('supports_chat')->default(true)->after('supports_phone');
            });
        }
    }
};
