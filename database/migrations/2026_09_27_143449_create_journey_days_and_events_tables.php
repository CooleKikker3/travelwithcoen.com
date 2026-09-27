<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per day on the road: the basis for all walking statistics.
        Schema::create('journey_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('walk');
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->unsignedSmallInteger('walking_minutes')->nullable();
            $table->string('overnight')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('journey_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            $table->string('type');
            $table->json('title');
            $table->json('description')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('journey_day_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journey_day_id');
        });
        Schema::dropIfExists('journey_events');
        Schema::dropIfExists('journey_days');
    }
};
