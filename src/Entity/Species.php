<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Inachis\Fauna\Enum\IucnStatus;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: 'Inachis\Fauna\Repository\SpeciesRepository')]
#[ORM\Table(name: 'fauna_species')]
#[ORM\Index(columns: ['external_id'])]
#[ORM\Index(columns: ['latin'])]
class Species
{
    /**
     * Unique identifier for the species
     *
     * @var UuidInterface|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    /**
     * Common name of the species
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $name;

    /**
     * Scientific name of the species
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: false)]
    private string $latin;

    /**
     * IUCN status of the species
     *
     * @var IucnStatus
     */
    #[ORM\Column(type: 'string', length: 2, nullable: true, enumType: IucnStatus::class)]
    private ?IucnStatus $iucn;

    /**
     * Description of the species
     *
     * @var string|null
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Genus of the species
     *
     * @var Taxonomy|null
     */
    #[ORM\ManyToOne(targetEntity: Taxonomy::class)]
    #[ORM\JoinColumn(name: 'genus_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Taxonomy $genus = null;

    /**
     * Images associated with the species
     * 
     * @var Collection<int, SpeciesImage>
     */
    #[ORM\OneToMany(targetEntity: SpeciesImage::class, mappedBy: 'species', cascade: ['persist', 'remove'])]
    private Collection $images;

    /**
     * Countries where the species is found
     * 
     * @var Collection<int, Country>
     */
    #[ORM\ManyToMany(targetEntity: Country::class)]
    #[ORM\JoinTable(name: 'fauna_species_to_country')]
    private Collection $countries;

    #[ORM\Column(type: 'integer', unique: true, nullable: true)]
    private ?int $externalId = null;

    /**
     * Date when the species was added to the database
     *
     * @var DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $dateAdded;

    /**
     * Date when the species was last updated
     *
     * @var DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $dateUpdated;

    /**
     * Constructor for the Species entity
     *
     * @param string $name
     * @param string $latin
     * @param IucnStatus|null $iucn
     * @param Taxonomy|null $genus
     */
    public function __construct(string $name = '', string $latin = '', ?IucnStatus $iucn = null, ?Taxonomy $genus = null)
    {
        $this->name = $name;
        $this->latin = $latin;
        $this->iucn = $iucn;
        $this->genus = $genus;
        $this->images = new ArrayCollection();
        $this->countries = new ArrayCollection();
        $this->dateAdded = new DateTimeImmutable();
        $this->dateUpdated = new DateTimeImmutable();
    }

    /**
     * Gets the unique identifier for the species
     *
     * @return UuidInterface|null
     */
    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    /**
     * Gets the common name of the species
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the common name of the species
     *
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Gets the scientific name of the species
     *
     * @return string
     */
    public function getLatin(): string
    {
        return $this->latin;
    }

    /**
     * Sets the scientific name of the species
     *
     * @param string $latin
     * @return self
     */
    public function setLatin(string $latin): self
    {
        $this->latin = $latin;
        return $this;
    }

    /**
     * Gets the IUCN status code of the species
     *
     * @return string|null
     */
    public function getIucn(): ?string
    {
        return $this->iucn?->value;
    }

    /**
     * Gets the IUCN status enum for the species
     *
     * @return IucnStatus|null
     */
    public function getIucnStatus(): ?IucnStatus
    {
        return $this->iucn;
    }

    /**
     * Sets the IUCN status of the species
     *
     * @param IucnStatus|null $iucn
     * @return self
     */
    public function setIucn(?IucnStatus $iucn): self
    {
        $this->iucn = $iucn;
        return $this;
    }

    /**
     * Gets the description of the species
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Sets the description of the species
     *
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Gets the genus of the species
     *
     * @return Taxonomy|null
     */
    public function getGenus(): ?Taxonomy
    {
        return $this->genus;
    }

    /**
     * Sets the genus of the species
     *
     * @param Taxonomy|null $genus
     * @return self
     */
    public function setGenus(?Taxonomy $genus): self
    {
        $this->genus = $genus;
        return $this;
    }

    /**
     * Gets the images associated with the species
     * 
     * @return Collection<int, SpeciesImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    /**
     * Adds an image to the species
     *
     * @param SpeciesImage $image
     * @return self
     */
    public function addImage(SpeciesImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setSpecies($this);
        }
        return $this;
    }

    /**
     * Removes an image from the species
     *
     * @param SpeciesImage $image
     * @return self
     */
    public function removeImage(SpeciesImage $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getSpecies() === $this) {
                $image->setSpecies(null);
            }
        }
        return $this;
    }

    /**
     * Gets the countries where the species is found
     * 
     * @return Collection<int, Country>
     */
    public function getCountries(): Collection
    {
        return $this->countries;
    }

    /**
     * Gets the countries where the species is found
     *
     * @param Country $country
     * @return self
     */
    public function addCountry(Country $country): self
    {
        if (!$this->countries->contains($country)) {
            $this->countries->add($country);
        }
        return $this;
    }

    /**
     * Removes a country from the species
     *
     * @param Country $country
     * @return self
     */
    public function removeCountry(Country $country): self
    {
        $this->countries->removeElement($country);
        return $this;
    }

    public function getExternalId(): ?int
    {
        return $this->externalId;
    }

    public function setExternalId(?int $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getDateAdded(): DateTimeImmutable
    {
        return $this->dateAdded;
    }

    public function setDateAdded(DateTimeImmutable $dateAdded): self
    {
        $this->dateAdded = $dateAdded;
        return $this;
    }

    public function getDateUpdated(): DateTimeImmutable
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated(DateTimeImmutable $dateUpdated): self
    {
        $this->dateUpdated = $dateUpdated;
        return $this;
    }
}
