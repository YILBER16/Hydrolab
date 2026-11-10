<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('active_crop', 40)->default('general')->after('pump_on');
            $table->text('last_ai_advice')->nullable()->after('active_crop');
            $table->string('last_ai_source', 20)->nullable()->after('last_ai_advice');
            $table->timestamp('last_ai_alert_at')->nullable()->after('last_ai_source');
            $table->json('last_ai_alert_types')->nullable()->after('last_ai_alert_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'active_crop',
                'last_ai_advice',
                'last_ai_source',
                'last_ai_alert_at',
                'last_ai_alert_types',
            ]);
        });
    }
};
