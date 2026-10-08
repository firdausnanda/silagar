<?php

namespace App\Helpers;

use InvalidArgumentException;

class UtmConverter
{
    /**
     * Convert WGS84 latitude and longitude to UTM meters and its EPSG code.
     *
     * @return array{utm_x: float, utm_y: float, utm_epsg: int}
     */
    public static function fromCoordinates(float $latitude, float $longitude): array
    {
        if (! is_finite($latitude) || ! is_finite($longitude) || $latitude < -80 || $latitude > 84 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Koordinat berada di luar cakupan UTM.');
        }

        $zone = min(60, (int) floor(($longitude + 180) / 6) + 1);

        if ($latitude >= 56 && $latitude < 64 && $longitude >= 3 && $longitude < 12) {
            $zone = 32;
        } elseif ($latitude >= 72 && $latitude < 84 && $longitude >= 0 && $longitude < 42) {
            $zone = match (true) {
                $longitude < 9 => 31,
                $longitude < 21 => 33,
                $longitude < 33 => 35,
                default => 37,
            };
        }

        $semiMajorAxis = 6378137.0;
        $flattening = 1 / 298.257223563;
        $eccentricitySquared = $flattening * (2 - $flattening);
        $secondEccentricitySquared = $eccentricitySquared / (1 - $eccentricitySquared);
        $scale = 0.9996;
        $latitudeRadians = deg2rad($latitude);
        $longitudeRadians = deg2rad($longitude);
        $centralMeridian = deg2rad(($zone - 1) * 6 - 180 + 3);
        $sinLatitude = sin($latitudeRadians);
        $cosLatitude = cos($latitudeRadians);
        $tangentSquared = tan($latitudeRadians) ** 2;
        $curvature = $secondEccentricitySquared * $cosLatitude ** 2;
        $longitudeOffset = $cosLatitude * ($longitudeRadians - $centralMeridian);
        $radius = $semiMajorAxis / sqrt(1 - $eccentricitySquared * $sinLatitude ** 2);
        $eccentricityFourth = $eccentricitySquared ** 2;
        $eccentricitySixth = $eccentricitySquared ** 3;

        $meridianArc = $semiMajorAxis * (
            (1 - $eccentricitySquared / 4 - 3 * $eccentricityFourth / 64 - 5 * $eccentricitySixth / 256) * $latitudeRadians
            - (3 * $eccentricitySquared / 8 + 3 * $eccentricityFourth / 32 + 45 * $eccentricitySixth / 1024) * sin(2 * $latitudeRadians)
            + (15 * $eccentricityFourth / 256 + 45 * $eccentricitySixth / 1024) * sin(4 * $latitudeRadians)
            - 35 * $eccentricitySixth / 3072 * sin(6 * $latitudeRadians)
        );

        $easting = 500000 + $scale * $radius * (
            $longitudeOffset
            + (1 - $tangentSquared + $curvature) * $longitudeOffset ** 3 / 6
            + (5 - 18 * $tangentSquared + $tangentSquared ** 2 + 72 * $curvature - 58 * $secondEccentricitySquared) * $longitudeOffset ** 5 / 120
        );
        $northing = $scale * (
            $meridianArc + $radius * tan($latitudeRadians) * (
                $longitudeOffset ** 2 / 2
                + (5 - $tangentSquared + 9 * $curvature + 4 * $curvature ** 2) * $longitudeOffset ** 4 / 24
                + (61 - 58 * $tangentSquared + $tangentSquared ** 2 + 600 * $curvature - 330 * $secondEccentricitySquared) * $longitudeOffset ** 6 / 720
            )
        );

        if ($latitude < 0) {
            $northing += 10000000;
        }

        return [
            'utm_x' => round($easting, 2),
            'utm_y' => round($northing, 2),
            'utm_epsg' => ($latitude < 0 ? 32700 : 32600) + $zone,
        ];
    }
}
