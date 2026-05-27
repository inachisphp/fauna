<?php

declare(strict_types=1);

namespace Inachis\Fauna\Tests\Entity;

use Inachis\Fauna\Entity\Taxonomy;
use Inachis\Fauna\Enum\TaxonomyType;
use PHPUnit\Framework\TestCase;

class TaxonomyTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $taxonomy = new Taxonomy();

        $this->assertNull($taxonomy->getId());
        $this->assertSame('', $taxonomy->getName());
        $this->assertSame('', $taxonomy->getType());
        $this->assertNull($taxonomy->getCommon());
        $this->assertNull($taxonomy->getParent());
        $this->assertEmpty($taxonomy->getChildren());
    }

    public function testConstructorArguments(): void
    {
        $taxonomy = new Taxonomy('Aves', TaxonomyType::CLASS_, 'Birds');

        $this->assertSame('Aves', $taxonomy->getName());
        $this->assertSame('class', $taxonomy->getType());
        $this->assertSame(TaxonomyType::CLASS_, $taxonomy->getTypeEnum());
        $this->assertSame('Birds', $taxonomy->getCommon());
        $this->assertNull($taxonomy->getParent());
    }

    public function testParentChildRelationship(): void
    {
        $parent = new Taxonomy('Aves', 'class', 'Birds');
        $child = new Taxonomy('Passeriformes', 'order', 'Perching Birds', $parent);

        $this->assertSame($parent, $child->getParent());

        $parent->addChild($child);
        $this->assertCount(1, $parent->getChildren());
        $this->assertTrue($parent->getChildren()->contains($child));
    }

    public function testAddChildSetsParent(): void
    {
        $parent = new Taxonomy('Mammalia', 'class', 'Mammals');
        $child = new Taxonomy('Carnivora', 'order', 'Carnivores');

        $parent->addChild($child);

        $this->assertSame($parent, $child->getParent());
    }

    public function testRemoveChild(): void
    {
        $parent = new Taxonomy('Aves', 'class');
        $child = new Taxonomy('Passer', 'genus');

        $parent->addChild($child);
        $this->assertCount(1, $parent->getChildren());

        $parent->removeChild($child);
        $this->assertCount(0, $parent->getChildren());
    }

    public function testAddChildIdempotent(): void
    {
        $parent = new Taxonomy('Aves', 'class');
        $child = new Taxonomy('Passer', 'genus');

        $parent->addChild($child);
        $parent->addChild($child); // adding twice should not duplicate

        $this->assertCount(1, $parent->getChildren());
    }

    public function testFluentSetters(): void
    {
        $taxonomy = new Taxonomy();
        $result = $taxonomy
            ->setName('Passeridae')
            ->setType('family')
            ->setCommon('True Sparrows');

        $this->assertSame($taxonomy, $result);
        $this->assertSame('Passeridae', $taxonomy->getName());
        $this->assertSame('family', $taxonomy->getType());
        $this->assertSame('True Sparrows', $taxonomy->getCommon());
    }
}
