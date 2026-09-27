<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Raw location history. Public visibility is decided server-side (see TrackingPrivacy).
        Schema::create('tracking_points', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('altitude', 7, 1)->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamp('received_at');
            $table->string('source', 30);
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['source', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_points');
        Schema::dropIfExists('settings');
    }
};
