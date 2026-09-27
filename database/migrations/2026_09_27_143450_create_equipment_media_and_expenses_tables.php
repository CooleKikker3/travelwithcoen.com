<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_items', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->json('name');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('weight_g')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->json('reason')->nullable();
            $table->json('review')->nullable();
            $table->string('status')->default('planned');
            $table->boolean('is_worn')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->json('caption')->nullable();
            $table->timestamp('taken_at')->nullable()->index();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journey_day_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journey_event_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_id', 20);
            $table->json('title');
            $table->json('description')->nullable();
            $table->date('published_on')->nullable()->index();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        // Private: only ever shown to the admin.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->decimal('amount_eur', 10, 2);
            $table->string('category');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('videos');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('equipment_items');
    }
};
