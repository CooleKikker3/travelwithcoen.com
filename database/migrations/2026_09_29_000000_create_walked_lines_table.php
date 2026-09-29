<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The walked route, stored ready-made per day at several levels of detail (App\Support\WalkedTrack),
     * so maps never load all GPS points: level 1 ≈ 1 km, 2 ≈ 200 m, 3 ≈ 40 m (level 4 = raw points).
     */
    public function up(): void
    {
        Schema::create('walked_lines', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->unsignedTinyInteger('level');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('line'); // encoded polyline (App\Support\Polyline)
            $table->unsignedInteger('points');
            $table->decimal('min_lat', 10, 7);
            $table->decimal('max_lat', 10, 7);
            $table->decimal('min_lng', 10, 7);
            $table->decimal('max_lng', 10, 7);
            $table->timestamp('first_at');
            $table->timestamp('last_at');
            $table->timestamps();
            $table->unique(['day', 'level']);
            $table->index(['level', 'last_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walked_lines');
    }
};
