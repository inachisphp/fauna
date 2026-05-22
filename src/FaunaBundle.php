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

    /**
     * Returns the bundle's container extension class name.
     * Symfony uses this to locate the DI Extension automatically.
     */
    public function getContainerExtensionClass(): string
    {
        return \Inachis\Fauna\DependencyInjection\FaunaExtension::class;
    }
}
