<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Width/height let the gallery wall reserve the right space before an image has loaded. */
    public function up(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('path');
            $table->unsignedInteger('height')->nullable()->after('width');
        });

        // Backfill existing photos.
        DB::table('gallery_items')->where('kind', 'image')->whereNotNull('path')->get()->each(function ($item) {
            $size = @getimagesize(Storage::disk('public')->path($item->path));
            if ($size) {
                DB::table('gallery_items')->where('id', $item->id)->update(['width' => $size[0], 'height' => $size[1]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('gallery_items', fn (Blueprint $table) => $table->dropColumn(['width', 'height']));
    }
};
