<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\DataConverter;

use Highcore\TemporalBundle\Serializer\DefaultSymfonySerializerFactory;
use Highcore\TemporalBundle\Serializer\SymfonySerializerFactory;
use Symfony\Component\Serializer\Serializer;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\Converter;
use Temporal\DataConverter\Type;
use Temporal\Exception\DataConverterException;

final class SymfonySerializerJsonClassObjectConverter extends Converter
{
    private ?Serializer $serializer = null;

    public function __construct(private readonly SymfonySerializerFactory $serializerFactory = new DefaultSymfonySerializerFactory())
    {
    }

    public function getEncodingType(): string
    {
        return MetadataType::SYMFONY_SERIALIZER_CLASS_OBJECT_JSON;
    }

    public function toPayload($value): ?Payload
    {
        if (!is_object($value)) {
            return null;
        }

        if ('stdClass' === get_debug_type($value)) {
            return $this->create(json_encode($value, JSON_THROW_ON_ERROR));
        }

        return $this->create($this->getSerializer()->serialize($value, 'json'));
    }

    public function fromPayload(Payload $payload, Type $type)
    {
        if (!$type->isClass()) {
            throw new DataConverterException('Unable to decode value using class object converter - ');
        }

        $dataToHydrate = json_decode($payload->getData(), true, 512, JSON_THROW_ON_ERROR);

        return $this->getSerializer()->denormalize($dataToHydrate, $type->getName());
    }

    private function getSerializer(): Serializer
    {
        return $this->serializer ?? ($this->serializer = $this->serializerFactory->create());
    }
}
