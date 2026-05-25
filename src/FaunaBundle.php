<?php

declare(strict_types=1);

namespace Inachis\Fauna;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class FaunaBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
