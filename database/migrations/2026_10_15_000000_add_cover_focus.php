<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The spot of a cover photo that must stay in view when it is cropped ("x% y%", e.g. "50% 20%"; empty = the middle).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['articles', 'equipment_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('cover_focus', 16)->nullable());
        }
    }

    public function down(): void
    {
        foreach (['articles', 'equipment_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('cover_focus'));
        }
    }
};
