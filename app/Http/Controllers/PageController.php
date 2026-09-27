<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentCategory;
use App\Enums\EquipmentStatus;
use App\Models\Country;
use App\Models\EquipmentItem;
use App\Models\GalleryItem;
use App\Support\JourneyStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function statistics(Request $request): View
    {
        $user = $request->user();

        return view('pages.statistics', [
            'stats' => JourneyStats::for($user),
            'countries' => Country::published()->get()
                ->map(fn (Country $country) => ['country' => $country, 'stats' => JourneyStats::for($user, $country)]),
        ]);
    }

    public function equipment(): View
    {
        $items = EquipmentItem::where('is_public', true)->orderBy('sort_order')->get();
        $inPack = $items->filter(fn ($item) => $item->status->isInPack());

        return view('pages.equipment', [
            'categories' => collect(EquipmentCategory::cases())
                ->mapWithKeys(fn ($category) => [$category->value => $items->where('category', $category)])
                ->filter->isNotEmpty(),
            'baseWeight' => $inPack->where('is_worn', false)->sum('weight_g'),
            'wornWeight' => $inPack->where('is_worn', true)->sum('weight_g'),
            'replaced' => EquipmentStatus::Replaced,
        ]);
    }

    /** Gallery: photos, uploaded videos and YouTube videos mixed, newest first (?kind=image|video). */
    public function gallery(Request $request): View
    {
        $kind = in_array($request->query('kind'), ['image', 'video'], true) ? $request->query('kind') : null;

        return view('pages.gallery', [
            'kind' => $kind,
            'items' => GalleryItem::public()->ofKind($kind)->with(['country', 'article'])->paginate(24)->withQueryString(),
        ]);
    }
}
