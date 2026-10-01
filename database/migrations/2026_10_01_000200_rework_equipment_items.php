<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Gear items work like articles: own page (slug), cover photo, short + long description (with photos), and free
// specifications. Status, weights, price and "worn" are gone; "why" and "review" move into the long description.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->json('slug')->nullable()->after('name');
            $table->json('excerpt')->nullable()->after('model');
            $table->string('cover_image')->nullable()->after('excerpt');
            $table->json('specs')->nullable()->after('cover_image');
        });
        Schema::table('equipment_items', fn (Blueprint $table) => $table->renameColumn('story', 'body'));

        // Keep what was written: "why" and "review" become paragraphs of the long description; slugs from the name.
        $taken = [];
        foreach (DB::table('equipment_items')->get() as $item) {
            $decode = fn ($value) => is_string($value) ? (json_decode($value, true) ?? []) : [];
            [$names, $reasons, $reviews, $bodies] = [$decode($item->name), $decode($item->reason), $decode($item->review), $decode($item->body)];
            $slugs = [];
            foreach (array_unique([...array_keys($names), ...array_keys($reasons), ...array_keys($reviews), ...array_keys($bodies)]) as $locale) {
                $parts = array_filter([
                    filled($reasons[$locale] ?? null) ? '<p>'.e($reasons[$locale]).'</p>' : null,
                    filled($reviews[$locale] ?? null) ? '<p>'.e($reviews[$locale]).'</p>' : null,
                    $bodies[$locale] ?? null,
                ]);
                if ($parts) {
                    $bodies[$locale] = implode('', $parts);
                }
            }
            foreach (['nl', 'en'] as $locale) {
                $name = $names[$locale] ?? $names['nl'] ?? $names['en'] ?? null;
                if (filled($name)) {
                    $slug = Str::slug($name) ?: 'item';
                    $slugs[$locale] = in_array("{$locale}:{$slug}", $taken) ? "{$slug}-{$item->id}" : $slug;
                    $taken[] = "{$locale}:{$slugs[$locale]}";
                }
            }
            DB::table('equipment_items')->where('id', $item->id)->update(['body' => json_encode($bodies ?: null), 'slug' => json_encode($slugs)]);
        }

        Schema::table('equipment_items', function (Blueprint $table) {
            $table->dropColumn(['status', 'weight_g', 'price', 'is_worn', 'reason', 'review']);
        });
    }

    public function down(): void
    {
        Schema::table('equipment_items', function (Blueprint $table) {
            $table->string('status')->default('planned');
            $table->unsignedInteger('weight_g')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_worn')->default(false);
            $table->json('reason')->nullable();
            $table->json('review')->nullable();
            $table->dropColumn(['slug', 'excerpt', 'cover_image', 'specs']);
        });
        Schema::table('equipment_items', fn (Blueprint $table) => $table->renameColumn('body', 'story'));
    }
};
