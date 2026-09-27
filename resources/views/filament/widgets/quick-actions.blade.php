<x-filament-widgets::widget>
    <x-filament::section heading="Quick actions">
        <div class="flex flex-wrap gap-2">
            <x-filament::button tag="a" :href="\App\Filament\Resources\Articles\ArticleResource::getUrl('create')" icon="heroicon-o-document-plus">New article</x-filament::button>
            <x-filament::button tag="a" :href="\App\Filament\Resources\Photos\PhotoResource::getUrl('index')" icon="heroicon-o-photo" color="gray">Upload photos</x-filament::button>
            <x-filament::button tag="a" :href="\App\Filament\Resources\JourneyDays\JourneyDayResource::getUrl('create')" icon="heroicon-o-calendar-days" color="gray">Add day</x-filament::button>
            <x-filament::button tag="a" :href="\App\Filament\Resources\JourneyEvents\JourneyEventResource::getUrl('create')" icon="heroicon-o-flag" color="gray">Add event</x-filament::button>
            <x-filament::button tag="a" :href="\App\Filament\Resources\CountryRoutes\CountryRouteResource::getUrl('create')" icon="heroicon-o-map" color="gray">Add route</x-filament::button>
            <x-filament::button tag="a" :href="\App\Filament\Resources\Expenses\ExpenseResource::getUrl('create')" icon="heroicon-o-banknotes" color="gray">Add expense</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
