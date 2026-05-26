<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Inachis\Fauna\Enum\TaxonomyType;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: 'Inachis\Fauna\Repository\TaxonomyRepository')]
#[ORM\Table(name: 'fauna_taxonomy')]
#[ORM\Index(columns: ['external_id'])]
#[ORM\Index(columns: ['parent_id'])]
#[ORM\Index(columns: ['type'])]
class Taxonomy
{
    /**
     * Unique identifier for the taxonomy entry
     *
     * @var UuidInterface|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    /**
     * The name of the taxonomy entry
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 100, nullable: false)]
    private string $name;

    /**
     * The type of the taxonomy entry, e.g. domain, kingdom, phylum, class, order, family, genus
     *
     * @var TaxonomyType|null
     */
    #[ORM\Column(type: 'string', length: 10, nullable: false, enumType: TaxonomyType::class)]
    private ?TaxonomyType $type = null;

    /**
     * The common name of the taxonomy entry, if applicable
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $common = null;

    /**
     * Whether the taxonomy entry is accepted or not
     *
     * @var boolean
     */
    #[ORM\Column(type: 'boolean')]
    private bool $accepted = true;

    /**
     * The canonical name of the taxonomy entry, if applicable
     *
     * @var string|null
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $canonicalName = null;

    /**
     * An optional external identifier for the taxonomy entry, e.g. from an external database
     *
     * @var integer|null
     */
    #[ORM\Column(type: 'integer', unique: true, nullable: true)]
    private ?int $externalId = null;

    /**
     * The parent taxonomy entry, if applicable
     *
     * @var Taxonomy|null
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Taxonomy $parent = null;

    /**
     * The child taxonomy entries, if applicable
     * 
     * @var Collection<int, Taxonomy>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['remove'])]
    private Collection $children;

    /**
     * Constructor for the Taxonomy entity
     *
     * @param string $name
     * @param TaxonomyType|null $type
     * @param string|null $common
     * @param Taxonomy|null $parent
     */
    public function __construct(string $name = '', ?TaxonomyType $type = null, ?string $common = null, ?Taxonomy $parent = null)
    {
        $this->name = $name;
        $this->type = is_string($type) ? TaxonomyType::fromValue($type) : $type;
        $this->common = $common;
        $this->parent = $parent;
        $this->children = new ArrayCollection();
    }

    /**
     * Gets the unique identifier for the taxonomy entry
     *
     * @return UuidInterface|null
     */
    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    /**
     * Gets the name of the taxonomy entry
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the name of the taxonomy entry
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
     * Gets the type of the taxonomy entry as a string
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type?->value ?? '';
    }

    /**
     * Gets the type of the taxonomy entry as an enum
     *
     * @return TaxonomyType|null
     */
    public function getTypeEnum(): ?TaxonomyType
    {
        return $this->type;
    }

    /**
     * Sets the type of the taxonomy entry
     *
     * @param TaxonomyType|string|null $type
     * @return self
     */
    public function setType(TaxonomyType|string|null $type): self
    {
        $this->type = is_string($type) ? TaxonomyType::fromValue($type) : $type;
        return $this;
    }

    /**
     * Gets the common name of the taxonomy entry
     *
     * @return string|null
     */
    public function getCommon(): ?string
    {
        return $this->common;
    }

    /**
     * Sets the common name of the taxonomy entry
     *
     * @param string|null $common
     * @return self
     */
    public function setCommon(?string $common): self
    {
        $this->common = $common;
        return $this;
    }

    /**
     * Gets the parent taxonomy entry, if applicable
     *
     * @return Taxonomy|null
     */
    public function getParent(): ?Taxonomy
    {
        return $this->parent;
    }

    /**
     * Sets the parent taxonomy entry, if applicable
     *
     * @param Taxonomy|null $parent
     * @return self
     */
    public function setParent(?Taxonomy $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Gets the child taxonomy entries, if applicable
     * 
     * @return Collection<int, Taxonomy>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Adds a child taxonomy entry to this taxonomy entry
     *
     * @param Taxonomy $child
     * @return self
     */
    public function addChild(Taxonomy $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    /**
     * Removes a child taxonomy entry from this taxonomy entry
     *
     * @param Taxonomy $child
     * @return self
     */
    public function removeChild(Taxonomy $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    /**
     * Gets the external identifier for the taxonomy entry
     *
     * @return int|null
     */
    public function getExternalId(): ?int
    {
        return $this->externalId;
    }

    /**
     * Sets the external identifier for the taxonomy entry
     *
     * @param int|null $externalId
     * @return self
     */
    public function setExternalId(?int $externalId): self
        {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * Gets the acceptance status of the taxonomy entry
     *
     * @return boolean
     */
    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    /**
     * Sets the acceptance status of the taxonomy entry
     *
     * @param boolean $accepted
     * @return self
     */
    public function setAccepted(bool $accepted): self
    {
        $this->accepted = $accepted;
        return $this;
    }

    /**
     * Gets the canonical name of the taxonomy entry
     *
     * @return string|null
     */
    public function getCanonicalName(): ?string
    {
        return $this->canonicalName;
    }

    /**
     * Sets the canonical name of the taxonomy entry
     *
     * @param string|null $canonicalName
     * @return self
     */
    public function setCanonicalName(?string $canonicalName): self
    {
        $this->canonicalName = $canonicalName;
        return $this;
    }
}
