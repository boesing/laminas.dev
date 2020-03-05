<?php

declare(strict_types=1);

namespace App\Factory;

use App\UrlHelper;
use Mezzio\Helper\ServerUrlHelper;
use Mezzio\Helper\UrlHelper as MezzioUrlHelper;
use Psr\Container\ContainerInterface;

class UrlHelperFactory
{
    public function __invoke(ContainerInterface $container): UrlHelper
    {
        return new UrlHelper(
            $container->get(MezzioUrlHelper::class),
            $container->get(ServerUrlHelper::class)
        );
    }
}
