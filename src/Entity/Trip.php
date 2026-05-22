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

#[ORM\Entity(repositoryClass: 'Inachis\Fauna\Repository\TripRepository')]
#[ORM\Table(name: 'fauna_trip')]
class Trip
{
    /**
     * Unique identifier for the trip
     *
     * @var UuidInterface|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    /**
     * The user who created the trip
     *
     * @var User|null
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?User $user = null;
    
    /**
     * The name of the trip
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $name;

    /**
     * The start date of the trip
     *
     * @var DateTimeImmutable|null
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $startDate = null;

    /**
     * The end date of the trip
     *
     * @var DateTimeImmutable|null
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $endDate = null;

    /**
     * The location of the trip
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    /**
     * Constructor for the Trip object
     *
     * @param string $name
     * @param User|null $user
     */
    public function __construct(string $name = '', ?User $user = null)
    {
        $this->name = $name;
        $this->user = $user;
    }

    /**
     * Returns the unique identifier for the trip
     *
     * @return UuidInterface|null
     */
    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    /**
     * Returns the user who created the trip
     *
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Sets the user who created the trip
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
     * Returns the name of the trip
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name of the trip
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
     * Returns the start date of the trip
     *
     * @return DateTimeImmutable|null
     */
    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * Sets the start date for the trip
     *
     * @param DateTimeImmutable|null $startDate
     * @return self
     */
    public function setStartDate(?DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * Returns the end date of the trip
     *
     * @return DateTimeImmutable|null
     */
    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * Sets the end date for the trip
     *
     * @param DateTimeImmutable|null $endDate
     * @return self
     */
    public function setEndDate(?DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * Returns the location of the trip
     *
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * Sets the location of the trip
     *
     * @param string|null $location
     * @return self
     */
    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }
}
