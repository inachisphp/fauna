<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: 'Inachis\Fauna\Repository\SpeciesImageRepository')]
#[ORM\Table(name: 'fauna_species_image')]
class SpeciesImage
{
    /**
     * Unique identifier for the species image
     *
     * @var UuidInterface|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    /**
     * The species associated with the image
     *
     * @var Species|null
     */
    #[ORM\ManyToOne(targetEntity: Species::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'species_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Species $species = null;

    /**
     * The URL of the species image
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 512, nullable: false)]
    private string $url;

    /**
     * The author of the species image
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $author = null;

    /**
     * Constructor for the SpeciesImage entity
     *
     * @param string $url
     * @param string|null $author
     * @param Species|null $species
     */
    public function __construct(string $url = '', ?string $author = null, ?Species $species = null)
    {
        $this->url = $url;
        $this->author = $author;
        $this->species = $species;
    }

    /**
     * Gets the ID of the species image
     *
     * @return UuidInterface|null
     */
    public function getId(): ?UuidInterface
    {
        return $this->id;
    }
    
    /**
     * Gets the species associated with the image
     *
     * @return Species|null
     */
    public function getSpecies(): ?Species
    {
        return $this->species;
    }

    /**
     * Sets the species associated with the image
     *
     * @param Species|null $species
     * @return self
     */
    public function setSpecies(?Species $species): self
    {
        $this->species = $species;
        return $this;
    }

    /**
     * Gets the URL of the species image
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }
    
    /**
     * Sets the URL of the species image
     *
     * @param string $url
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Gets the author of the species image
     *
     * @return string|null
     */
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    /**
     * Sets the author of the species image
     *
     * @param string|null $author
     * @return self
     */
    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }
}
