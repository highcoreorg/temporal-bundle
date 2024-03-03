<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Serializer;
use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class DefaultSymfonySerializerFactory implements SymfonySerializerFactory
{
    public function create(): Serializer
    {
        $extractor = new ReflectionExtractor();
        $typeExtractor = new PropertyInfoExtractor(
            typeExtractors: [new ConstructorExtractor([$extractor]), $extractor,]
        );

        return new Serializer(
            normalizers: [
                new BackedEnumNormalizer(),
                new ObjectNormalizer(propertyTypeExtractor: $typeExtractor),
                new ArrayDenormalizer(),
            ],
            encoders: ['json' => new JsonEncoder()]
        );
    }
}