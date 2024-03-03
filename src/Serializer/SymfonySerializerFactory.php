<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Serializer;

use Symfony\Component\Serializer\Serializer;

interface SymfonySerializerFactory
{
    public function create(): Serializer;
}