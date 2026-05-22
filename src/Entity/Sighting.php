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
use Inachis\Entity\User;

#[ORM\Entity(repositoryClass: 'Inachis\Fauna\Repository\SightingRepository')]
#[ORM\Table(name: 'fauna_sighting')]
class Sighting
{
    /**
     * Unique identifier for the sighting
     *
     * @var UuidInterface|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    /**
     * The user who made the sighting
     *
     * @var User|null
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * The trip during which the sighting was made
     *
     * @var Trip|null
     */
    #[ORM\ManyToOne(targetEntity: Trip::class)]
    #[ORM\JoinColumn(name: 'trip_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Trip $trip = null;

    /**
     * The species that was sighted
     *
     * @var Species|null
     */
    #[ORM\ManyToOne(targetEntity: Species::class)]
    #[ORM\JoinColumn(name: 'species_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Species $species = null;

    /**
     * The country where the sighting was made
     *
     * @var Country|null
     */
    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(name: 'country_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Country $country = null;

    /**
     * The location where the sighting was made
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    /**
     * The latitude of the sighting
     *
     * @var float|null
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    /**
     * The longitude of the sighting
     *
     * @var float|null
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    /**
     * The notes about the sighting
     *
     * @var string|null
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * The count of the sighting
     *
     * @var int
     */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $count = 1;

    /**
     * The photograph of the sighting
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $photograph = null;

    /**
     * The date when the sighting was made
     *
     * @var DateTimeImmutable
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $date;

    /**
     * Constructor for Sighting entity
     *
     * @param Species|null $species
     * @param User|null $user
     */
    public function __construct(?Species $species = null, ?User $user = null)
    {
        $this->species = $species;
        $this->user = $user;
        $this->date = new DateTimeImmutable();
    }
    
    /**
     * Returns the unique identifier for the sighting
     *
     * @return UuidInterface|null
     */
    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    /**
     * Returns the user who made the sighting
     *
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Sets the user who made the sighting
     *
     * @param User|null $user
     * @return self
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Returns the trip during which the sighting was made
     *
     * @return Trip|null
     */
    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    /**
     * Sets the trip during which the sighting was made
     * 
     * @param Trip|null $trip
     * @return self
     */
    public function setTrip(?Trip $trip): self
    {
        $this->trip = $trip;
        return $this;
    }

    /**
     * Returns the species that was sighted
     *
     * @return Species|null
     */
    public function getSpecies(): ?Species
    {
        return $this->species;
    }

    /**
     * Sets the species that was sighted
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
     * Returns the country where the sighting was made
     *
     * @return Country|null
     */
    public function getCountry(): ?Country
    {
        return $this->country;
    }

    /**
     * Sets the country where the sighting was made
     *
     * @param Country|null $country
     * @return self
     */
    public function setCountry(?Country $country): self
    {
        $this->country = $country;
        return $this;
    }

    /**
     * Returns the location where the sighting was made
     *
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * Sets the location where the sighting was made
     *
     * @param string|null $location
     * @return self
     */ 
    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Returns the latitude where the sighting was made
     *
     * @return float|null
     */
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    /**
     * Sets the latitude where the sighting was made
     * @param float|null $latitude
     * @return self
     */
    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    /**
     * Returns the longitude where the sighting was made
     *
     * @return float|null
     */
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    /**
     * Sets the longitude where the sighting was made
     * 
     * @param float|null $longitude
     * @return self
     */
    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    /**
     * Returns the notes for the sighting
     *
     * @return string|null
     */
    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * Sets the notes for the sighting
     *
     * @param string|null $notes
     * @return self
     */
    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * Returns the count for the sighting
     *
     * @return integer
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Sets the count for the sighting
     *
     * @param integer $count
     * @return self
     */
    public function setCount(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Returns the photograph for the sighting
     *
     * @return string|null
     */
    public function getPhotograph(): ?string
    {
        return $this->photograph;
    }

    /**
     * Sets the photograph for the sighting
     *
     * @param string|null $photograph
     * @return self
     */
    public function setPhotograph(?string $photograph): self
    {
        $this->photograph = $photograph;
        return $this;
    }

    /**
     * Returns the date when the sighting was made
     *
     * @return DateTimeImmutable
     */
    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Sets the date when the sighting was made
     *
     * @param DateTimeImmutable $date
     * @return self
     */
    public function setDate(DateTimeImmutable $date): self
    {
        $this->date = $date;
        return $this;
    }
}
