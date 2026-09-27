<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A planned or actual route (segment) through one country. The global map is built from these.
        Schema::create('country_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('gpx_path')->nullable();
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_route_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('elevation', 7, 1)->nullable();
            $table->timestamp('recorded_at')->nullable();

            $table->index(['country_route_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_points');
        Schema::dropIfExists('country_routes');
    }
};
