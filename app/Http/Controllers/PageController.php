<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentCategory;
use App\Models\EquipmentItem;
use App\Models\GalleryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function equipment(): View
    {
        $items = EquipmentItem::public()->get();

        return view('pages.equipment', [
            'categories' => collect(EquipmentCategory::cases())
                ->mapWithKeys(fn ($category) => [$category->value => $items->where('category', $category)])
                ->filter->isNotEmpty(),
        ]);
    }

    /** One piece of gear, like an article page. */
    public function equipmentItem(string $slug): View|RedirectResponse
    {
        $locale = app()->getLocale();
        $item = EquipmentItem::public()
            ->where(fn ($query) => $query->whereSlug($slug, $locale)->orWhere(fn ($query) => $query->whereSlug($slug, 'nl'))->orWhere(fn ($query) => $query->whereSlug($slug, 'en')))
            ->firstOrFail();

        // Always serve an item on its own slug for this locale.
        if ($item->translate('slug', $locale) !== $slug) {
            return redirect($item->url(), 301);
        }

        return view('pages.equipment-item', [
            'item' => $item,
            'isTranslated' => $item->isTranslated($locale),
            'alternates' => collect(array_keys(config('travel.locales')))->filter(fn (string $l) => $item->isTranslated($l))
                ->mapWithKeys(fn (string $l) => [$l => $item->url($l)])->all(),
        ]);
    }

    /** Gallery: photos, uploaded videos and YouTube videos mixed, newest first. */
    public function gallery(): View
    {
        return view('pages.gallery', [
            'items' => GalleryItem::public()->with(['country', 'article'])->paginate(24),
        ]);
    }
}
