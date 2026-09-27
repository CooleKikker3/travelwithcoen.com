<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Remember which channel a YouTube item came from, so switching channels cleans up the old one. */
    public function up(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->string('youtube_channel_id', 30)->nullable()->index()->after('youtube_id');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->dropIndex(['youtube_channel_id']);
            $table->dropColumn('youtube_channel_id');
        });
    }
};
