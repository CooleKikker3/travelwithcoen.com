<?php

namespace App\Services;

use App\Models\GalleryItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Adds new videos of the YouTube channel in .env (YOUTUBE_CHANNEL) to the gallery.
 * Reads the channel's public RSS feed (no API key needed), looks at the newest videos only
 * and adds the ones that are not in the database yet. Existing items are left untouched,
 * so changes made in the CMS (visibility, country, article, captions) are kept.
 * Items from a previously configured channel are removed.
 */
class YouTubeSync
{
    public const NEWEST = 10;

    // Skips the EU cookie consent page when resolving a channel handle.
    private const HEADERS = ['Cookie' => 'SOCS=CAI; CONSENT=YES+1', 'Accept-Language' => 'en'];

    /** @return int number of videos added */
    public function sync(): int
    {
        $channel = trim((string) config('travel.youtube_channel'));

        if ($channel === '') {
            return 0;
        }

        $channelId = $this->channelId($channel);

        GalleryItem::where('kind', 'youtube')
            ->where(fn ($q) => $q->whereNull('youtube_channel_id')->orWhere('youtube_channel_id', '!=', $channelId))
            ->delete();

        $newest = array_slice($this->fromRss($channelId), 0, self::NEWEST);
        $known = GalleryItem::whereIn('youtube_id', array_column($newest, 'id'))->pluck('youtube_id')->all();
        $added = 0;

        foreach ($newest as $video) {
            if (in_array($video['id'], $known, true)) {
                continue;
            }

            GalleryItem::create([
                'kind' => 'youtube',
                'source' => 'youtube',
                'youtube_id' => $video['id'],
                'youtube_channel_id' => $channelId,
                // The YouTube title is the (English) caption; a Dutch caption can be added in the CMS.
                'caption' => ['en' => Str::limit($video['title'], 300)],
                'taken_at' => $video['published'],
                'is_public' => true,
            ]);
            $added++;
        }

        return $added;
    }

    /** Accepts a channel id (UC…), a handle (@name) or a channel URL. */
    public function channelId(string $channel): string
    {
        if (preg_match('~(UC[\w-]{22})~', $channel, $m)) {
            return $m[1];
        }

        $handle = '@'.ltrim(Str::afterLast(rtrim($channel, '/'), '/'), '@');

        return Cache::rememberForever("youtube_channel:{$handle}", function () use ($handle) {
            $html = Http::withHeaders(self::HEADERS)->get("https://www.youtube.com/{$handle}")->throw()->body();

            // Only the channel's own id: the canonical link, else "externalId". The page also mentions
            // other channels ("channelId"), so those must not be used.
            if (preg_match('~<link rel="canonical" href="https://www\.youtube\.com/channel/(UC[\w-]{22})"~', $html, $m)
                || preg_match('~"externalId":"(UC[\w-]{22})"~', $html, $m)) {
                return $m[1];
            }

            throw new RuntimeException("YouTube channel {$handle} not found.");
        });
    }

    /** Newest first. @return array<int, array{id: string, title: string, published: Carbon}> */
    private function fromRss(string $channelId): array
    {
        $xml = simplexml_load_string(
            Http::withHeaders(self::HEADERS)->get('https://www.youtube.com/feeds/videos.xml', ['channel_id' => $channelId])->throw()->body(),
            options: LIBXML_NONET
        );

        $videos = [];
        foreach ($xml->entry as $entry) {
            $videos[] = [
                'id' => (string) $entry->children('http://www.youtube.com/xml/schemas/2015')->videoId,
                'title' => (string) $entry->title,
                'published' => Carbon::parse((string) $entry->published),
            ];
        }

        usort($videos, fn ($a, $b) => $b['published'] <=> $a['published']);

        return $videos;
    }
}
