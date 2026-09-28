<?php

namespace App\Support;

/**
 * Encoded polyline (Google format, precision 5 ≈ 1 m): about four times smaller than JSON coordinates,
 * so route edits stay small on a weak connection. Mirrors encode/decode in resources/js/route-planner.js.
 */
class Polyline
{
    /** @param  array<int, array{0: float, 1: float}>  $points  [lat, lng] */
    public static function encode(array $points): string
    {
        $result = '';
        $previous = [0, 0];

        foreach ($points as $point) {
            foreach ([0, 1] as $i) {
                $value = (int) round($point[$i] * 1e5);
                $delta = $value - $previous[$i];
                $previous[$i] = $value;
                $delta = $delta < 0 ? ~($delta << 1) : $delta << 1;
                while ($delta >= 0x20) {
                    $result .= chr((0x20 | ($delta & 0x1F)) + 63);
                    $delta >>= 5;
                }
                $result .= chr($delta + 63);
            }
        }

        return $result;
    }

    /** @return array<int, array{0: float, 1: float}> [lat, lng] */
    public static function decode(string $encoded): array
    {
        $points = [];
        $index = 0;
        $current = [0, 0];
        $length = strlen($encoded);

        while ($index < $length) {
            foreach ([0, 1] as $i) {
                $shift = $result = 0;
                do {
                    $byte = ord($encoded[$index++]) - 63;
                    $result |= ($byte & 0x1F) << $shift;
                    $shift += 5;
                } while ($byte >= 0x20 && $index < $length);
                $current[$i] += ($result & 1) ? ~($result >> 1) : ($result >> 1);
            }
            $points[] = [$current[0] / 1e5, $current[1] / 1e5];
        }

        return $points;
    }
}
