<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Highcore\TemporalBundle\Pass\ActivityCompilerPass;

final class TemporalBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ActivityCompilerPass());
    }
}