<?php

namespace App\Filament\Support;

use Filament\Forms\Components\ViewField;
use Illuminate\Database\Eloquent\Model;

/** Click on the saved cover photo to choose what stays in view when it is cropped (Concerns\HasCoverFocus). */
class CoverFocus
{
    public static function make(): ViewField
    {
        return ViewField::make('cover_focus')
            ->label('Wat moet in beeld blijven?')
            ->helperText('Klik op de foto, bijvoorbeeld op je gezicht. Daaronder zie je hoe hij op de computer, op een telefoon en als kaartje wordt bijgesneden. Een nieuwe foto? Eerst opslaan.')
            ->view('filament.forms.cover-focus')
            ->visible(fn (?Model $record) => filled($record?->cover_image));
    }
}
