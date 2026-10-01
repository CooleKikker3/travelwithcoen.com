<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Automatic Dutch → English translations waiting to be checked (App\Services\AutoTranslation, page "Vertalingen").
// One row per field: translatable_type is a model class, or "site_text" (id 0, field = the text key).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type');
            $table->unsignedBigInteger('translatable_id');
            $table->string('field');
            $table->longText('source');
            $table->longText('suggestion')->nullable();
            $table->string('status')->default('pending')->index(); // pending → ready (to check) | failed
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
            $table->unique(['translatable_type', 'translatable_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
