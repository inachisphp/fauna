<?php

declare(strict_types=1);

namespace Inachis\Fauna\Tests\Entity;

use DateTimeImmutable;
use Inachis\Fauna\Entity\Trip;
use PHPUnit\Framework\TestCase;

class TripTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $trip = new Trip();

        $this->assertNull($trip->getId());
        $this->assertSame('', $trip->getName());
        $this->assertNull($trip->getUser());
        $this->assertNull($trip->getStartDate());
        $this->assertNull($trip->getEndDate());
        $this->assertNull($trip->getLocation());
    }

    public function testNameConstructor(): void
    {
        $trip = new Trip('Kenya Safari 2025');

        $this->assertSame('Kenya Safari 2025', $trip->getName());
    }

    public function testDateRange(): void
    {
        $trip = new Trip('Scottish Highlands');
        $start = new DateTimeImmutable('2025-06-01');
        $end = new DateTimeImmutable('2025-06-07');

        $trip->setStartDate($start);
        $trip->setEndDate($end);

        $this->assertSame($start, $trip->getStartDate());
        $this->assertSame($end, $trip->getEndDate());
        $this->assertSame('2025-06-01', $trip->getStartDate()->format('Y-m-d'));
        $this->assertSame('2025-06-07', $trip->getEndDate()->format('Y-m-d'));
    }

    public function testNullableDates(): void
    {
        $trip = new Trip('Short Trip');
        $trip->setStartDate(null);
        $trip->setEndDate(null);

        $this->assertNull($trip->getStartDate());
        $this->assertNull($trip->getEndDate());
    }

    public function testLocation(): void
    {
        $trip = new Trip('Autumn Walk');
        $trip->setLocation('Yorkshire Dales, England');

        $this->assertSame('Yorkshire Dales, England', $trip->getLocation());
    }

    public function testFluentSetters(): void
    {
        $trip = new Trip();
        $result = $trip
            ->setName('Test Trip')
            ->setLocation('Test Location');

        $this->assertSame($trip, $result);
        $this->assertSame('Test Trip', $trip->getName());
    }
}
