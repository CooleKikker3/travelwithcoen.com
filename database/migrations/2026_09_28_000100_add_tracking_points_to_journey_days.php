<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** GPS location (tracking point) where a journey day started and ended. */
    public function up(): void
    {
        Schema::table('journey_days', function (Blueprint $table) {
            $table->foreignId('start_point_id')->nullable()->after('ended_at')->constrained('tracking_points')->nullOnDelete();
            $table->foreignId('end_point_id')->nullable()->after('start_point_id')->constrained('tracking_points')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journey_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('start_point_id');
            $table->dropConstrainedForeignId('end_point_id');
        });
    }
};
