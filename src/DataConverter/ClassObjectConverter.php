<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\DataConverter;

use Highcore\TemporalBundle\Serializer\DefaultSerializerFactory;
use Highcore\TemporalBundle\Serializer\SerializerFactory;
use Symfony\Component\Serializer\Serializer;
use Temporal\Api\Common\V1\Payload;
use Temporal\DataConverter\Converter;
use Temporal\DataConverter\Type;
use Temporal\Exception\DataConverterException;

final class ClassObjectConverter extends Converter
{
    public const METADATA_ENCODING_CLASS_OBJECT_JSON_KEY = 'php/json-class-object';

    private ?Serializer $serializer = null;

    public function __construct(private readonly SerializerFactory $serializerFactory = new DefaultSerializerFactory())
    {
    }

    public function getEncodingType(): string
    {
        return self::METADATA_ENCODING_CLASS_OBJECT_JSON_KEY;
    }

    public function toPayload($value): ?Payload
    {
        if (!is_object($value) || 'stdClass' === get_debug_type($value) || is_array($value)) {
            return null;
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
