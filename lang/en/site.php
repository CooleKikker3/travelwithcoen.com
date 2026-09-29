<?php

return [
    'name' => 'Travel with Coen',
    'tagline' => 'Walking from the Netherlands to Hanoi',
    'departure' => 'late June 2027',
    'description' => 'Coen walks from the Netherlands to Hanoi, Vietnam: mostly on foot, over land, with a light backpack and a tent.',
    'skip' => 'Skip to content',
    'menu' => 'Menu',
    'language' => 'Language',

    'nav' => [
        'stories' => 'Stories',
        'home' => 'Home',
        'journey' => 'Journey',
        'about' => 'About',
        'live' => 'Live',
        'equipment' => 'Gear',
        'media' => 'Gallery',
    ],


    'home' => [
        'hand_note' => 'yes, on foot!',
        'eyebrow' => 'The Netherlands → Hanoi, on foot',
        'title' => 'Walking from the Netherlands to Hanoi',
        'lead' => 'From :departure I walk east from the Netherlands to Hanoi, Vietnam: as much as possible on foot and over land, with a tent on my back.',
        'cta_preparation' => 'Road to Hanoi',
        'cta_about' => 'Why I’m doing this',
        'facts' => [
            'departure' => 'Departure',
            'duration' => 'Expected duration',
            'duration_value' => '16–20 months',
            'daily' => 'Base distance',
            'daily_value' => '~25 km a day',
            'pack' => 'Target base weight',
            'pack_value' => '8–10 kg',
        ],
        'photos_title' => 'Latest from the gallery',
        'photos_hand' => 'snapshots from the road',
        'video_title' => 'Latest video',
        'direction_title' => 'The rough plan',
        'direction_hand' => 'more or less...',
        'direction' => "Netherlands\nGermany\nEastern Europe / Balkans\nTurkey\nIran / Central Asia\nChina\nVietnam\nHanoi",
        'news_title' => 'Latest news',
        'news_hand' => 'fresh from the trail!',
        'all_updates' => 'All updates',
        'empty' => 'Nothing published yet. The first stories are on their way.',
        'map_hand' => 'follow along!',
        'last_seen' => 'Last seen :date',
        'map_title' => 'The route',
    ],

    'journey' => [
        'title' => 'The journey',
        'lead' => 'Every country on the way gets its own page, with the route, the kilometres and the stories.',
        'overview' => 'The whole journey',
        'timeline' => 'Country by country',
        'stories_title' => 'Stories',
        'all_stories' => 'All stories',
        'empty' => 'No countries added yet.',
        'stories' => '{0} No stories yet|{1} 1 story|[2,*] :count stories',
    ],

    'country' => [
        'back' => 'All countries',
        'story' => 'My story',
        'articles' => 'Stories from :country',
        'no_story' => 'I haven’t written about this country yet.',
    ],

    'articles' => [
        'read_more' => 'Read more',
        'not_translated' => 'This article is not yet available in English. You are reading the original version.',
        'published' => 'Published :date',
        'country' => 'Country',
        'tags' => 'Tags',
        'back' => 'Back to all stories',
        'empty' => 'No articles yet.',
        'all_tags' => 'All',
        'tagged' => 'Tagged “:tag”',
    ],


    'preparation' => [
        'title' => 'Road to Hanoi',
        'lead' => 'Everything that happens before departure: planning, training, gear, visas and the first nights outside.',
    ],

    'about' => [
        'hand' => "hi, I'm Coen!",
        'youtube' => "Watch my videos on YouTube",
        'title' => 'About the project',
        'lead' => 'Why walk from the Netherlands to Hanoi?',
        'body' => "I’m Coen, from Lisse in the Netherlands. From :departure I walk from my front door to Hanoi, Vietnam: as much as possible on foot and over land, using transport only when a border, visa, safety or geography really requires it.\n\nIt isn’t about ticking off tourist sights. I want countryside, nature, quiet roads, dirt tracks and small villages. I camp a lot, cook for myself and keep the trip as cheap as it can be without making it unsafe.\n\nI’ve walked the Nijmegen Four Days Marches and finished an 80 km Kennedymars — and learned from my knees afterwards that walking far is one thing, and walking far day after day is another. That’s what the preparation is for.\n\nThis website is the home of the project: preparation first, then the journey itself, and afterwards the complete archive of the walk.",
        'uncertain' => 'Route, countries, distances and dates are plans, not promises. Where something is uncertain, this site says so.',
    ],

    'map' => [
        'exit' => 'Crossed the border',
        'zoom_hint' => 'Use Ctrl + scroll to zoom',
        'delay_note' => 'My location runs :days days behind, for my safety.',
        'pieces_title' => 'The plan, piece by piece',
        'legend' => 'Legend',
        'open' => 'Still to be planned',
        'position' => 'Latest location',
        'delay' => ':days days delay',
        'endpoints' => 'Start & finish',
        'label' => 'Map of the route',
        'planned' => 'Planned route',
        'actual' => 'Walked route',
        'planned_note' => 'Planned routes are not final.',
        'no_route' => 'No route data yet.',
    ],

    'stats' => [
        'planned' => 'Planned',
        'walked' => 'Walked',
        'km' => ':km km',
        'countries' => 'Countries',
    ],
    'live' => [
        'title' => 'Live location',
        'lead_public' => 'For safety, the public location runs :days days behind. Family can follow along live.',
        'lead_private' => 'You are logged in and see the latest received location.',
        'last_location' => 'Last location',
        'recorded' => 'Recorded',
        'received' => 'Received by the website',
        'public_from' => 'Public from',
        'live_badge' => 'Live',
        'ago' => 'Last location received :time',
        'stale' => 'This is not a live location: the last update is more than an hour old.',
        'unavailable' => 'No location data available yet.',
        'family' => 'Family login',
    ],

    'login' => [
        'title' => 'Log in',
        'lead' => 'For family and friends with an account.',
        'email' => 'Email',
        'password' => 'Password',
        'remember' => 'Remember me',
        'submit' => 'Log in',
        'failed' => 'These details are incorrect.',
        'logout' => 'Log out',
        'logged_in_as' => 'Logged in as :name',
    ],

    'statistics' => [
        'days' => 'Days on the road',
        'walking_days' => 'Walking days',
        'rest_days' => 'Rest days',
        'average' => 'Average per walking day',
        'hours' => 'Walking hours',
        'countries' => 'Countries walked',
    ],

    'equipment' => [
        'hand' => 'everything I carry',
        'title' => 'Gear',
        'lead' => 'What I carry, why I chose it, and how it holds up.',
        'base_weight' => 'Base weight (in the pack)',
        'worn_weight' => 'Worn',
        'target' => 'Target: 8–10 kg',
        'why' => 'Why',
        'review' => 'Experience',
        'worn' => 'worn',
        'empty' => 'The gear list is still being put together.',
    ],

    'media' => [
        'title' => 'Gallery',
        'lead' => 'Pictures and films from the preparation and the road.',
        'photos' => 'Photos',
        'videos' => 'Videos',
        'all' => 'All',
        'youtube' => 'On YouTube',
        'from_article' => 'From: :title',
        'more' => 'Show older',
        'sensitive_label' => 'Sensitive content',
        'sensitive_text' => 'This image may be upsetting to some viewers (for example an injury).',
        'sensitive_show' => 'Show anyway',
        'open' => 'Open',
        'play' => 'Play video',
        'previous' => 'Previous',
        'next' => 'Next',
        'close' => 'Close',
        'empty' => 'No photos or videos yet.',
    ],

    'day_type' => [
        'walk' => 'Walking day',
        'rest' => 'Rest day',
        'transport' => 'Transport',
    ],

    'overnight' => [
        'wild_camping' => 'Wild camping',
        'campsite' => 'Campsite',
        'hotel' => 'Hotel',
        'hostel' => 'Hostel',
        'host' => 'With a host',
        'other' => 'Other',
    ],

    'event_type' => [
        'border_crossing' => 'Border crossing',
        'milestone' => 'Milestone',
        'rest_day' => 'Rest day',
        'meeting' => 'Encounter',
        'special_walk' => 'Special walk',
        'other' => 'Other',
    ],

    'gear_category' => [
        'shelter' => 'Shelter',
        'sleep' => 'Sleep system',
        'pack' => 'Backpack',
        'clothing' => 'Clothing',
        'footwear' => 'Footwear',
        'cooking' => 'Cooking',
        'water' => 'Water',
        'electronics' => 'Electronics',
        'navigation' => 'Navigation & communication',
        'safety' => 'Safety & first aid',
        'hygiene' => 'Hygiene',
        'other' => 'Other',
    ],

    'gear_status' => [
        'planned' => 'Considering',
        'testing' => 'Testing',
        'carried' => 'In the pack',
        'replaced' => 'Replaced',
        'retired' => 'No longer used',
    ],

    'day_name' => 'Day :number',

    'stories' => [
        'lead' => 'Everything I write about the walk: the preparation and the journey itself.',
    ],

    'errors' => [
        'not_found_title' => 'Wrong turn',
        'not_found_hand' => 'oops, this path leads nowhere!',
        'not_found_lead' => 'This page does not exist (any more). Maybe the route changed, or the link has a typo. Back to familiar ground?',
        'back_home' => 'Back home',
    ],

    'status_block' => [
        'country' => 'Current country',
        'live' => 'Live',
        'to_hanoi' => 'To Hanoi (as the crow flies)',
        'walking' => 'on the road',
        'resting' => 'rest day',
        'walked_km' => 'Walked',
        'days' => 'Days on the road',
        'as_of' => 'As of :date',
    ],

    'day_log' => [
        'title' => 'Day by day',
        'hand' => 'my logbook',
    ],

    'back_to_top' => 'Back to top',
    'coming_soon' => [
        'title' => 'Something is coming...',
        'hand' => 'lacing up my boots!',
        'lead' => 'This is where I will share my walk from the Netherlands to Hanoi: the route, the stories and the photos. Almost ready, come back soon!',
    ],

    'footer' => [
        'note' => 'A walk from the Netherlands to Hanoi.',
        'hand' => 'see you on the road!',
        'made_by' => 'Made by',
        'sitemap' => 'Sitemap',
    ],
];
