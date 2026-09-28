<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A route piece (country_routes) gets a public title and story, and is built from parts (route_segments):
     * GPX files and drawn pieces, in order. The route's points are rebuilt from its parts on save.
     */
    public function up(): void
    {
        Schema::table('country_routes', function (Blueprint $table) {
            $table->json('title')->nullable()->after('name');
            $table->json('description')->nullable()->after('title');
        });

        Schema::create('route_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_route_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('kind'); // gpx | drawn
            $table->string('label')->nullable();
            $table->string('routing')->nullable(); // drawn: hiking | straight
            $table->json('waypoints')->nullable(); // drawn: [[lat, lng, index in line], ...]
            $table->longText('line'); // encoded polyline (precision 5)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_segments');
        Schema::table('country_routes', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });
    }
};
