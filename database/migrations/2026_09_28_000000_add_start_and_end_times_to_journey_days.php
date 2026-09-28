<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Started / ended times of a walking day (dashboard buttons); a started day without an end is "open". */
    public function up(): void
    {
        Schema::table('journey_days', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('type');
            $table->timestamp('ended_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('journey_days', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'ended_at']);
        });
    }
};
