<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Private budget tracking: admin only, never shown on the public site. */
#[Fillable(['date', 'amount_eur', 'category', 'country_id', 'description'])]
class Expense extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount_eur' => 'float',
            'category' => ExpenseCategory::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
