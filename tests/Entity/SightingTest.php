<?php

declare(strict_types=1);

namespace Inachis\Fauna\Tests\Entity;

use DateTimeImmutable;
use Inachis\Fauna\Entity\Country;
use Inachis\Fauna\Entity\Sighting;
use Inachis\Fauna\Entity\Species;
use Inachis\Fauna\Enum\IucnStatus;
use PHPUnit\Framework\TestCase;

class SightingTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $sighting = new Sighting();

        $this->assertNull($sighting->getId());
        $this->assertNull($sighting->getUser());
        $this->assertNull($sighting->getSpecies());
        $this->assertNull($sighting->getTrip());
        $this->assertNull($sighting->getCountry());
        $this->assertNull($sighting->getLocation());
        $this->assertNull($sighting->getLatitude());
        $this->assertNull($sighting->getLongitude());
        $this->assertNull($sighting->getNotes());
        $this->assertSame(1, $sighting->getCount());
        $this->assertNull($sighting->getPhotograph());
        $this->assertInstanceOf(DateTimeImmutable::class, $sighting->getDate());
    }

    public function testSpeciesAssignment(): void
    {
        $species = new Species('Red Kite', 'Milvus milvus', IucnStatus::LC);
        $sighting = new Sighting($species);

        $this->assertSame($species, $sighting->getSpecies());
    }

    public function testCountryAssignment(): void
    {
        $country = new Country('United Kingdom', 'GB');
        $sighting = new Sighting();
        $sighting->setCountry($country);

        $this->assertSame($country, $sighting->getCountry());
    }

    public function testCoordinates(): void
    {
        $sighting = new Sighting();
        $sighting->setLatitude(51.5074);
        $sighting->setLongitude(-0.1278);

        $this->assertEqualsWithDelta(51.5074, $sighting->getLatitude(), 0.0001);
        $this->assertEqualsWithDelta(-0.1278, $sighting->getLongitude(), 0.0001);
    }

    public function testNullableCoordinates(): void
    {
        $sighting = new Sighting();
        $sighting->setLatitude(null);
        $sighting->setLongitude(null);

        $this->assertNull($sighting->getLatitude());
        $this->assertNull($sighting->getLongitude());
    }

    public function testCount(): void
    {
        $sighting = new Sighting();
        $sighting->setCount(5);

        $this->assertSame(5, $sighting->getCount());
    }

    public function testDateAssignment(): void
    {
        $sighting = new Sighting();
        $date = new DateTimeImmutable('2025-07-15 09:30:00');
        $sighting->setDate($date);

        $this->assertSame($date, $sighting->getDate());
        $this->assertSame('2025-07-15', $sighting->getDate()->format('Y-m-d'));
    }

    public function testNotes(): void
    {
        $sighting = new Sighting();
        $sighting->setNotes('Spotted near the river feeding on fish.');

        $this->assertSame('Spotted near the river feeding on fish.', $sighting->getNotes());
    }

    public function testFluentSetters(): void
    {
        $sighting = new Sighting();
        $result = $sighting
            ->setLocation('Hyde Park, London')
            ->setCount(3)
            ->setNotes('Test observation');

        $this->assertSame($sighting, $result);
    }
}
