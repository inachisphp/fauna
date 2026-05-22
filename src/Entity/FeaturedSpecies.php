<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: 'Inachis\Fauna\Repository\FeaturedSpeciesRepository')]
#[ORM\Table(name: 'fauna_featured_species')]
class FeaturedSpecies
{
    /**
     * Unique identifier for the featured species entry
     *
     * @var UuidInterface|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    /**
     * The species that is featured
     *
     * @var Species|null
     */
    #[ORM\ManyToOne(targetEntity: Species::class)]
    #[ORM\JoinColumn(name: 'species_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Species $species = null;

    /**
     * The title for the featured species entry
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $title;

    /**
     * The image link for the featured species entry
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $imageLink = null;

    /**
     * The description for the featured species entry
     *
     * @var string|null
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * The date when the featured species entry was added
     *
     * @var DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $dateAdded;

    /**
     * The start date for the featured species entry's schedule
     *
     * @var DateTimeImmutable|null
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $scheduleStartDate = null;

    /**
     * The end date for the featured species entry's schedule
     *
     * @var DateTimeImmutable|null
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $scheduleEndDate = null;

    /**
     * The live status for the featured species entry
     *
     * @var boolean
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isLive = false;

    /**
     * Constructor for the FeaturedSpecies entity
     *
     * @param string $title
     * @param Species|null $species
     */
    public function __construct(string $title = '', ?Species $species = null)
    {
        $this->title = $title;
        $this->species = $species;
        $this->dateAdded = new DateTimeImmutable();
    }

    /**
     * Gets the unique identifier for the featured species entry
     *
     * @return UuidInterface|null
     */
    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    /**
     * Returns the species that is featured
     *
     * @return Species|null
     */
    public function getSpecies(): ?Species
    {
        return $this->species;
    }

    /**
     * Sets the species that is featured
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
     * Returns the title for the featured species entry
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Sets the title for the featured species entry
     *
     * @param string $title
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Returns the image link for the featured species entry
     *
     * @return string|null
     */
    public function getImageLink(): ?string
    {
        return $this->imageLink;
    }

    /**
     * Sets the image link for the featured species entry
     *
     * @param string|null $imageLink
     * @return self
     */
    public function setImageLink(?string $imageLink): self
    {
        $this->imageLink = $imageLink;
        return $this;
    }

    /**
     * Returns the description for the featured species entry
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Sets the description for the featured species entry
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
     * Returns the date when the featured species entry was added
     *
     * @return DateTimeImmutable
     */
    public function getDateAdded(): DateTimeImmutable
    {
        return $this->dateAdded;
    }
    /*
     * Sets the date when the featured species entry was added
     *
     * @param DateTimeImmutable $dateAdded
     * @return self
     */
    public function setDateAdded(DateTimeImmutable $dateAdded): self
    {
        $this->dateAdded = $dateAdded;
        return $this;
    }

    /**
     * Returns the start date for the featured species entry's schedule
     *
     * @return DateTimeImmutable|null
     */
    public function getScheduleStartDate(): ?DateTimeImmutable
    {
        return $this->scheduleStartDate;
    }

    /**
     * Sets the start date for the featured species entry's schedule
     *
     * @param DateTimeImmutable|null $scheduleStartDate
     * @return self
     */
    public function setScheduleStartDate(?DateTimeImmutable $scheduleStartDate): self
    {
        $this->scheduleStartDate = $scheduleStartDate;
        return $this;
    }

    /**
     * Returns the end date for the featured species entry's schedule
     *
     * @return DateTimeImmutable|null
     */
    public function getScheduleEndDate(): ?DateTimeImmutable
    {
        return $this->scheduleEndDate;
    }

    /**
     * Sets the end date for the featured species entry's schedule
     * @param DateTimeImmutable|null $scheduleEndDate
     * @return self
     */
    public function setScheduleEndDate(?DateTimeImmutable $scheduleEndDate): self
    {
        $this->scheduleEndDate = $scheduleEndDate;
        return $this;
    }

    /**
     * Returns whether the featured species entry is live
     *
     * @return boolean
     */
    public function isLive(): bool
    {
        return $this->isLive;
    }

    /**
     * Sets whether the featured species entry is live
     *
     * @param boolean $isLive
     * @return self
     */
    public function setIsLive(bool $isLive): self
    {
        $this->isLive = $isLive;
        return $this;
    }
}
