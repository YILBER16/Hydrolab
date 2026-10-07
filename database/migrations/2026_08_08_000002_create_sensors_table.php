<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // temperature, humidity, ph, ec, water_level
            $table->string('name');
            $table->string('unit', 20);
            $table->decimal('min_threshold', 8, 2)->nullable();
            $table->decimal('max_threshold', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['device_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
