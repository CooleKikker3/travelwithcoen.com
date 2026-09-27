<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('iso_code', 2)->unique();
            // Translatable fields are JSON objects keyed by locale: {"en": "...", "nl": "..."}
            $table->json('name');
            $table->json('slug');
            $table->json('intro')->nullable();
            $table->json('story')->nullable();
            $table->string('status')->default('tentative');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
