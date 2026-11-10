<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->date('planted_at')->nullable()->after('active_crop');
            $table->unsignedSmallInteger('harvest_days')->nullable()->after('planted_at');
            $table->text('daily_advice')->nullable()->after('last_ai_alert_types');
            $table->string('daily_advice_source', 20)->nullable()->after('daily_advice');
            $table->date('daily_advice_date')->nullable()->after('daily_advice_source');
            $table->timestamp('daily_advice_at')->nullable()->after('daily_advice_date');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'planted_at',
                'harvest_days',
                'daily_advice',
                'daily_advice_source',
                'daily_advice_date',
                'daily_advice_at',
            ]);
        });
    }
};
