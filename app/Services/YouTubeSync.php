<?php

namespace App\Services;

use App\Models\Video;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the videos of the configured YouTube channel (Settings → YouTube channel).
 * Without an API key the public RSS feed is used (newest ~15 videos, which is enough when
 * syncing hourly); with YOUTUBE_API_KEY the complete upload history is fetched.
 * Existing videos keep their CMS settings (visibility, country, article, Dutch title).
 */
class YouTubeSync
{
    // Skips the EU cookie consent page when resolving a channel handle.
    private const HEADERS = ['Cookie' => 'SOCS=CAI; CONSENT=YES+1', 'Accept-Language' => 'en'];

    public function sync(): int
    {
        $channel = trim((string) Settings::get('youtube_channel'));

        if ($channel === '') {
            return 0;
        }

        $channelId = $this->channelId($channel);
        $videos = config('travel.youtube_api_key') ? $this->fromApi($channelId) : $this->fromRss($channelId);

        foreach ($videos as $v) {
            $video = Video::firstOrNew(['youtube_id' => $v['id']]);
            $video->title = array_merge($video->title ?? [], ['en' => $v['title']]);
            $video->description = array_merge($video->description ?? [], ['en' => Str::limit($v['description'], 1000)]);
            $video->published_on = $v['published'];
            $video->is_public ??= true;
            $video->save();
        }

        return count($videos);
    }

    /** Accepts a channel id (UC…), a handle (@name) or a channel URL. */
    public function channelId(string $channel): string
    {
        if (preg_match('~(UC[\w-]{22})~', $channel, $m)) {
            return $m[1];
        }

        $handle = '@'.ltrim(Str::afterLast(rtrim($channel, '/'), '/'), '@');

        return Cache::rememberForever("youtube_channel_id:{$handle}", function () use ($handle) {
            if ($key = config('travel.youtube_api_key')) {
                $id = Http::get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'id', 'forHandle' => $handle, 'key' => $key])
                    ->throw()->json('items.0.id');
            } else {
                $html = Http::withHeaders(self::HEADERS)->get("https://www.youtube.com/{$handle}")->throw()->body();
                $id = preg_match('~"(?:externalId|channelId)":"(UC[\w-]{22})"~', $html, $m) ? $m[1] : null;
            }

            return $id ?? throw new RuntimeException("YouTube channel {$handle} not found.");
        });
    }

    /** @return array<int, array{id: string, title: string, description: string, published: Carbon}> */
    private function fromRss(string $channelId): array
    {
        $xml = simplexml_load_string(
            Http::withHeaders(self::HEADERS)->get('https://www.youtube.com/feeds/videos.xml', ['channel_id' => $channelId])->throw()->body(),
            options: LIBXML_NONET
        );

        $videos = [];
        foreach ($xml->entry as $entry) {
            $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
            $media = $entry->children('http://search.yahoo.com/mrss/')->group;
            $videos[] = [
                'id' => (string) $yt->videoId,
                'title' => (string) $entry->title,
                'description' => (string) $media?->description,
                'published' => Carbon::parse((string) $entry->published),
            ];
        }

        return $videos;
    }

    private function fromApi(string $channelId): array
    {
        $videos = [];
        $pageToken = null;

        do {
            $response = Http::get('https://www.googleapis.com/youtube/v3/playlistItems', array_filter([
                'part' => 'snippet',
                'playlistId' => 'UU'.substr($channelId, 2), // the channel's "uploads" playlist
                'maxResults' => 50,
                'pageToken' => $pageToken,
                'key' => config('travel.youtube_api_key'),
            ]))->throw();

            foreach ($response->json('items', []) as $item) {
                $videos[] = [
                    'id' => $item['snippet']['resourceId']['videoId'],
                    'title' => $item['snippet']['title'],
                    'description' => $item['snippet']['description'] ?? '',
                    'published' => Carbon::parse($item['snippet']['publishedAt']),
                ];
            }
        } while (($pageToken = $response->json('nextPageToken')) && count($videos) < 2000);

        return $videos;
    }
}
