<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Articles are organised by tags now, not by a fixed preparation topic.
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['topic']);
            $table->dropColumn('topic');
        });

        // The photo table becomes the gallery: photos and uploaded videos, from uploads or from articles.
        Schema::rename('photos', 'gallery_items');
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->string('kind')->default('image')->after('path');
            $table->string('source')->default('upload')->after('kind');
            $table->index(['article_id', 'source']);
        });

        // Routes drawn in the route planner keep their waypoints so they can be edited later.
        Schema::table('country_routes', function (Blueprint $table) {
            $table->json('waypoints')->nullable()->after('gpx_path');
            $table->string('routing')->nullable()->after('waypoints');
        });

        // Videos are synced from YouTube, one row per YouTube video.
        Schema::table('videos', function (Blueprint $table) {
            $table->unique('youtube_id');
        });
    }

    public function down(): void
    {
        Schema::table('videos', fn (Blueprint $table) => $table->dropUnique(['youtube_id']));
        Schema::table('country_routes', fn (Blueprint $table) => $table->dropColumn(['waypoints', 'routing']));
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->dropIndex(['article_id', 'source']);
            $table->dropColumn(['kind', 'source']);
        });
        Schema::rename('gallery_items', 'photos');
        Schema::table('articles', fn (Blueprint $table) => $table->string('topic')->nullable()->index());
    }
};
