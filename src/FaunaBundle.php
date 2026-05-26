<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class FaunaBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
