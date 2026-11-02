<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('vehicle_class')->nullable();
            $table->boolean('warranty_included')->default(false);
            $table->boolean('towing_available')->default(false);
            $table->decimal('towing_price', 12, 2)->nullable();
            $table->decimal('base_price', 12, 2);
            $table->json('inclusions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['workshop_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
