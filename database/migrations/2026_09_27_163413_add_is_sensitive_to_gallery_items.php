<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Sensitive/graphic images (e.g. injuries) are shown blurred with a warning until the visitor chooses to see them. */
    public function up(): void
    {
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->boolean('is_sensitive')->default(false)->after('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_items', fn (Blueprint $table) => $table->dropColumn('is_sensitive'));
    }
};
