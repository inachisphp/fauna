<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Enum;

/**
 * Enum for taxonomy types
 */
enum TaxonomyType: string
{
    case DOMAIN = 'domain';
    case KINGDOM = 'kingdom';
    case PHYLUM = 'phylum';
    case CLASS_ = 'class';
    case ORDER = 'order';
    case FAMILY = 'family';
    case GENUS = 'genus';

    /**
     * Returns the taxonomy type from a given string value
     *
     * @param string $value
     * @return self
     */
    public static function fromValue(string $value): self
    {
        return self::from(strtolower(trim($value)));
    }

    /**
     * Returns the taxonomy type from a given string value, or null if not found
     *
     * @param string|null $value
     * @return self|null
     */
    public static function tryFromValue(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }
}
