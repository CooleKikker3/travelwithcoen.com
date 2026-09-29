<?php

namespace App\Http\Controllers;

use App\Models\CountryRoute;
use App\Models\TrackingPoint;
use App\Services\TrackingRecorder;
use App\Support\RouteGeometry;
use App\Support\TrackingPrivacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TrackingController extends Controller
{
    /** Live page: guests see the delayed location, trusted viewers the latest one. */
    public function live(Request $request): View
    {
        $user = $request->user();
        $live = TrackingPrivacy::canSeeLive($user);
        $last = TrackingPoint::visibleTo($user)->with('country')->latest('recorded_at')->first();

        return view('pages.live', [
            'live' => $live,
            'last' => $last,
            'isLive' => $live && $last && $last->recorded_at->gt(now()->subMinutes(TrackingPrivacy::LIVE_MINUTES)),
            'delayDays' => round(TrackingPrivacy::delayHours() / 24),
            'map' => RouteGeometry::withOpenPlan(RouteGeometry::withTracking(
                RouteGeometry::featureCollection(CountryRoute::whereHas('country', fn ($query) => $query->where('is_published', true))->get(), RouteGeometry::OVERVIEW),
                $user, null, 2,
            )),
        ]);
    }

    /**
     * GET /api/track?level=1-4&bbox=west,south,east,north[&country=id] — the walked route (and from level 3 the route pieces) at a level of detail,
     * for the part of the map in view (maps fetch this when zooming in). Delay and privacy zone apply per user.
     */
    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'between:1,4'],
            'bbox' => ['required', 'regex:/^-?\d+(\.\d+)?(,-?\d+(\.\d+)?){3}$/'],
            'country' => ['nullable', 'integer'],
        ]);
        $bbox = array_map('floatval', explode(',', $data['bbox']));
        $level = (int) $data['level'];

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => [
                // Zoomed in, the route pieces follow every path too (pages embed them simplified).
                ...($level >= 3 ? RouteGeometry::routesIn($bbox, $data['country'] ?? null, $level >= 4 ? RouteGeometry::FINE : RouteGeometry::DETAILED) : []),
                ...\App\Support\WalkedTrack::features($request->user(), $level, $bbox, $data['country'] ?? null),
            ],
        ]);
    }

    /** GET /api/public/tracking — never returns anything newer than the public delay, even when logged in. */
    public function publicIndex(): JsonResponse
    {
        return response()->json($this->payload(TrackingPrivacy::publicCutoff(), private: false));
    }

    /** GET /api/private/tracking — trusted viewers and admins only (route middleware). */
    public function privateIndex(): JsonResponse
    {
        return response()->json($this->payload(null, private: true));
    }

    /**
     * POST /api/tracking — ingest from a device/app. Authorization: Bearer {TRACKING_INGEST_TOKEN}
     * Body: {"source": "phone", "points": [{"lat": 52.1, "lng": 4.5, "time": "2027-07-01T10:00:00Z", "ele": 3}]}
     */
    public function ingest(Request $request, TrackingRecorder $recorder): JsonResponse
    {
        $token = (string) config('travel.tracking_ingest_token');

        if ($token === '' || ! hash_equals($token, (string) $request->bearerToken())) {
            Log::warning('Rejected tracking ingest', ['ip' => $request->ip()]);
            abort(401);
        }

        $data = $request->validate([
            'source' => ['nullable', 'string', 'alpha_dash', 'max:30'],
            'points' => ['required', 'array', 'max:5000'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.time' => ['required', 'date'],
            'points.*.ele' => ['nullable', 'numeric', 'between:-500,9000'],
        ]);

        $stored = $recorder->store($data['points'], $data['source'] ?? 'api');

        return response()->json(['stored' => $stored], 201);
    }

    private function payload(?Carbon $cutoff, bool $private): array
    {
        // The last point, never near home for the public (privacy zone around the start).
        $last = TrackingPoint::recordedBefore($cutoff)->latest('recorded_at')->first();
        if ($last && ! $private && \App\Support\RouteGeometry::isPrivate($last->latitude, $last->longitude, evenWhenLoggedIn: true)) {
            $last = null;
        }

        return [
            'delay_hours' => $private ? 0 : TrackingPrivacy::delayHours(),
            'generated_at' => now()->toIso8601String(),
            'last' => $last ? array_filter([
                'lat' => $last->latitude,
                'lng' => $last->longitude,
                'recorded_at' => $last->recorded_at->toIso8601String(),
                'received_at' => $private ? $last->received_at->toIso8601String() : null,
                'public_from' => $last->publicFrom()->toIso8601String(),
            ]) : null,
            // The route as lines ([lat, lng]), ~200 m detail: light, however many points there are.
            'lines' => \App\Support\WalkedTrack::lines($private ? request()->user() : null, 2),
        ];
    }
}
