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

#[ORM\Entity(repositoryClass: 'Inachis\Fauna\Repository\CountryRepository')]
#[ORM\Table(name: 'fauna_country')]
class Country
{
    /**
     * Unique identifier for the country
     *
     * @var UuidInterface|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;
    
    /**
     * The name of the country
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private string $name;

    /**
     * The code of the country
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 2, unique: true, nullable: false)]
    private string $code;

    /**
     * Constructor for Country entity
     * 
     * @param string $name
     * @param string $code
     */
    public function __construct(string $name = '', string $code = '')
    {
        $this->name = $name;
        $this->code = strtoupper($code);
    }

    /**
     * Returns the unique identifier of the country
     *
     * @return UuidInterface|null
     */
    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    /**
     * Returns the name of the country
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name of the country
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
     * Returns the code of the country
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Sets the code of the country
     *
     * @param string $code
     * @return self
     */
    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);
        return $this;
    }
}
