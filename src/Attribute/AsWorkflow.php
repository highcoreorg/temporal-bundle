<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Attribute;


use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;
use Highcore\Component\Registry\Attribute\ServiceAttributeInterface;

#[\Attribute(\Attribute::TARGET_CLASS)]
#[NamedArgumentConstructor]
final class AsWorkflow implements ServiceAttributeInterface
{
}