<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crops', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->decimal('temperature_min', 8, 2);
            $table->decimal('temperature_max', 8, 2);
            $table->decimal('ph_min', 8, 2);
            $table->decimal('ph_max', 8, 2);
            $table->unsignedSmallInteger('harvest_days')->default(40);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crops');
    }
};
