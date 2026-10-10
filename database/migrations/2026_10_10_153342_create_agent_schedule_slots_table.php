<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_week_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('slot_date');
            $table->unsignedTinyInteger('hour');
            $table->string('channel');
            $table->string('source')->default('self');
            $table->timestamps();

            $table->unique(['user_id', 'slot_date', 'hour', 'channel']);
            $table->index(['schedule_week_id', 'slot_date', 'hour', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_schedule_slots');
    }
};
