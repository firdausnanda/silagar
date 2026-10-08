<?php

namespace Tests\Unit;

use App\Helpers\UtmConverter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UtmConverterTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_wgs84_utm_matches_reference_coordinates_in_southern_hemisphere(): void
    {
        $coordinates = UtmConverter::fromCoordinates(-36.841, 174.740);

        $this->assertSame(32760, $coordinates['utm_epsg']);
        $this->assertEqualsWithDelta(298481.34, $coordinates['utm_x'], 0.1);
        $this->assertEqualsWithDelta(5920382.04, $coordinates['utm_y'], 0.1);
    }

    public function test_utm_zone_changes_at_114_degrees_east(): void
    {
        $west = UtmConverter::fromCoordinates(0, 111);
        $east = UtmConverter::fromCoordinates(0, 114);

        $this->assertSame(['utm_x' => 500000.0, 'utm_y' => 0.0, 'utm_epsg' => 32649], $west);
        $this->assertSame(32650, $east['utm_epsg']);
    }

    public function test_latitude_outside_utm_coverage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UtmConverter::fromCoordinates(85, 112);
    }
}
