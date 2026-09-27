<?php

namespace App\Http\Controllers;

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
        $last = TrackingPoint::visibleTo($user)->latest('recorded_at')->first();

        return view('pages.live', [
            'live' => $live,
            'last' => $last,
            'isLive' => $live && $last && $last->recorded_at->gt(now()->subMinutes(TrackingPrivacy::LIVE_MINUTES)),
            'delayDays' => round(TrackingPrivacy::delayHours() / 24),
            'map' => RouteGeometry::withTracking(['type' => 'FeatureCollection', 'features' => []], $user, null, RouteGeometry::DETAILED),
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
        $points = TrackingPoint::recordedBefore($cutoff)->orderBy('recorded_at')->get();
        $last = $points->last();

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
            'points' => $points->map(fn (TrackingPoint $p) => [$p->latitude, $p->longitude, $p->recorded_at->toIso8601String()])->all(),
        ];
    }
}
