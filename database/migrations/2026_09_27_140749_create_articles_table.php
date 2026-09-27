<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('topic')->nullable()->index();
            // Translatable fields are JSON objects keyed by locale: {"en": "...", "nl": "..."}
            $table->json('title');
            $table->json('slug');
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cover_image')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
