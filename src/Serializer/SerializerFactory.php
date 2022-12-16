<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Serializer;

use Symfony\Component\Serializer\Serializer;

interface SerializerFactory
{
    public function create(): Serializer;
}