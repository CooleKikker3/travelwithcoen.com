<?php

namespace App\Models;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category', 'name', 'brand', 'model', 'weight_g', 'price', 'reason', 'review', 'status', 'is_worn', 'is_public', 'sort_order'])]
class EquipmentItem extends Model
{
    use HasTranslations;

    protected array $translatable = ['name', 'reason', 'review'];

    protected string $slugSource = 'name';

    protected function casts(): array
    {
        return [
            'category' => EquipmentCategory::class,
            'status' => EquipmentStatus::class,
            'price' => 'float',
            'is_worn' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    // Equipment has no slug column.
    protected function fillMissingSlugs(): void {}
}
