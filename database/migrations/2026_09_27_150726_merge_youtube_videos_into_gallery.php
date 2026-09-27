<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** YouTube videos become ordinary gallery items (kind "youtube"), mixed with photos and uploaded videos. */
    public function up(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->string('path')->nullable()->change();
            $table->string('youtube_id', 20)->nullable()->unique()->after('path');
        });

        foreach (DB::table('videos')->get() as $video) {
            DB::table('gallery_items')->insert([
                'path' => null,
                'youtube_id' => $video->youtube_id,
                'kind' => 'youtube',
                'source' => 'youtube',
                'caption' => $video->title,
                'taken_at' => $video->published_on,
                'country_id' => $video->country_id,
                'article_id' => $video->article_id,
                'is_public' => $video->is_public,
                'created_at' => $video->created_at,
                'updated_at' => $video->updated_at,
            ]);
        }

        Schema::drop('videos');
    }

    public function down(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_id', 20)->unique();
            $table->json('title');
            $table->json('description')->nullable();
            $table->date('published_on')->nullable()->index();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        DB::table('gallery_items')->where('kind', 'youtube')->delete();

        Schema::table('gallery_items', function (Blueprint $table) {
            $table->dropUnique(['youtube_id']);
            $table->dropColumn('youtube_id');
        });
    }
};
