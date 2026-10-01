<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Clicks on the tracking link of an Instagram story (/s/{article}/{locale}). Only a count: no IP or other personal data.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->timestamp('clicked_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_clicks');
    }
};
