<?php

declare(strict_types=1);

namespace Inachis\Fauna\Tests\Entity;

use Inachis\Fauna\Entity\Country;
use PHPUnit\Framework\TestCase;

class CountryTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $country = new Country();

        $this->assertNull($country->getId());
        $this->assertSame('', $country->getName());
        $this->assertSame('', $country->getCode());
    }

    public function testConstructorArguments(): void
    {
        $country = new Country('United Kingdom', 'GB');

        $this->assertSame('United Kingdom', $country->getName());
        $this->assertSame('GB', $country->getCode());
    }

    public function testCodeIsUppercased(): void
    {
        $country = new Country('France', 'fr');
        $this->assertSame('FR', $country->getCode());

        $country->setCode('de');
        $this->assertSame('DE', $country->getCode());
    }

    public function testFluentSetters(): void
    {
        $country = new Country();
        $result = $country
            ->setName('Germany')
            ->setCode('DE');

        $this->assertSame($country, $result);
        $this->assertSame('Germany', $country->getName());
        $this->assertSame('DE', $country->getCode());
    }
}
