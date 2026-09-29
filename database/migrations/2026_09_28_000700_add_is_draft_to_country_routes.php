<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Concept route pieces: only in the CMS (route planner, "Concepten" tab), never on the website. */
    public function up(): void
    {
        Schema::table('country_routes', fn (Blueprint $table) => $table->boolean('is_draft')->default(false)->index()->after('type'));
    }

    public function down(): void
    {
        Schema::table('country_routes', fn (Blueprint $table) => $table->dropColumn('is_draft'));
    }
};