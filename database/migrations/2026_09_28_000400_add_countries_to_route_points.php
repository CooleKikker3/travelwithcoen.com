<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The country each route point lies in (CountryLocator), and a route piece's kilometres per country. */
    public function up(): void
    {
        Schema::table('route_points', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('country_route_id')->constrained()->nullOnDelete();
            $table->index(['country_id', 'country_route_id']);
        });
        Schema::table('country_routes', function (Blueprint $table) {
            $table->json('country_km')->nullable()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('country_routes', fn (Blueprint $table) => $table->dropColumn('country_km'));
        Schema::table('route_points', function (Blueprint $table) {
            $table->dropIndex(['country_id', 'country_route_id']);
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
