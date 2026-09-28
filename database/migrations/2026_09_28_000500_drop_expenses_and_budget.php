<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Budget and expenses are not kept in this system (Coen's choice). */
    public function up(): void
    {
        Schema::dropIfExists('expenses');
        DB::table('settings')->whereIn('key', ['budget_total_eur', 'budget_reserve_eur'])->delete();
    }

    public function down(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->decimal('amount_eur', 10, 2);
            $table->string('category');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }
};