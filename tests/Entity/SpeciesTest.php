<?php

declare(strict_types=1);

namespace Inachis\Fauna\Tests\Entity;

use Inachis\Fauna\Entity\Country;
use Inachis\Fauna\Entity\Species;
use Inachis\Fauna\Entity\Taxonomy;
use Inachis\Fauna\Enum\IucnStatus;
use PHPUnit\Framework\TestCase;

class SpeciesTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $species = new Species();

        $this->assertSame('', $species->getName());
        $this->assertSame('', $species->getLatin());
        $this->assertNull($species->getId());
        $this->assertNull($species->getIucn());
        $this->assertNull($species->getDescription());
        $this->assertNull($species->getGenus());
        $this->assertEmpty($species->getImages());
        $this->assertEmpty($species->getCountries());
    }

    public function testNameSetterAndGetter(): void
    {
        $species = new Species('House Sparrow', 'Passer domesticus');

        $this->assertSame('House Sparrow', $species->getName());
        $this->assertSame('Passer domesticus', $species->getLatin());
    }

    public function testIucnStatus(): void
    {
        $species = new Species('Lion', 'Panthera leo', IucnStatus::VU);

        $this->assertSame('VU', $species->getIucn());

        $species->setIucn(IucnStatus::EN);
        $this->assertSame('EN', $species->getIucn());

        $species->setIucn(null);
        $this->assertNull($species->getIucn());
    }

    public function testDescriptionSetterAndGetter(): void
    {
        $species = new Species();
        $species->setDescription('A small brown bird common in urban areas.');

        $this->assertSame('A small brown bird common in urban areas.', $species->getDescription());
    }

    public function testGenusRelationship(): void
    {
        $genus = new Taxonomy('Passer', 'genus', 'Sparrows');
        $species = new Species('House Sparrow', 'Passer domesticus', IucnStatus::LC, $genus);

        $this->assertSame($genus, $species->getGenus());
    }

    public function testCountryCollection(): void
    {
        $species = new Species('Red Fox', 'Vulpes vulpes');
        $gb = new Country('United Kingdom', 'GB');
        $fr = new Country('France', 'FR');

        $species->addCountry($gb);
        $species->addCountry($fr);

        $this->assertCount(2, $species->getCountries());
        $this->assertTrue($species->getCountries()->contains($gb));
        $this->assertTrue($species->getCountries()->contains($fr));

        $species->removeCountry($gb);
        $this->assertCount(1, $species->getCountries());
        $this->assertFalse($species->getCountries()->contains($gb));
    }

    public function testFluentSetters(): void
    {
        $species = new Species();
        $result = $species
            ->setName('Common Blackbird')
            ->setLatin('Turdus merula')
            ->setIucn(IucnStatus::LC)
            ->setDescription('A widespread European thrush.');

        $this->assertSame($species, $result);
        $this->assertSame('Common Blackbird', $species->getName());
    }
}
