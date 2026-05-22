<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Enum;

/**
 * Enum for IUCN status codes
 */
enum IucnStatus: string
{
    case LC = 'LC';
    case NT = 'NT';
    case VU = 'VU';
    case EN = 'EN';
    case CR = 'CR';
    case EW = 'EW';
    case EX = 'EX';

    /**
     * Returns the IUCN status from a given string value
     *
     * @param string $value
     * @return self
     */
    public static function fromValue(string $value): self
    {
        return self::from(strtoupper(trim($value)));
    }

    /**
     * Returns the IUCN status from a given string value, or null if not found
     *
     * @param string|null $value
     * @return self|null
     */
    public static function tryFromValue(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtoupper(trim($value)));
    }
}
