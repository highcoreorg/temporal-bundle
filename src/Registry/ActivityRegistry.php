<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Registry;

use Temporal\Activity\ActivityInterface;

final class ActivityRegistry
{
    private array $activities = [];

    public function add($activity): void
    {
        $reflection = new \ReflectionObject($activity);
        $attributes = $this->getAllAttributes($reflection);

        if (!\in_array(ActivityInterface::class, $attributes, true)) {
            throw new \LogicException(\sprintf(
                'Class "%s" does not have "%s" attribute.',
                $activity::class, ActivityInterface::class));
        }

        $this->activities[] = $activity;
    }

    public function all(): array
    {
        return $this->activities;
    }

    private function getAllAttributes(\ReflectionObject $reflection): array
    {
        $attributes = [];
        $attributes[] = $reflection->getAttributes();

        foreach ($reflection->getInterfaces() as $interface) {
            $attributes[] = $interface->getAttributes();
        }


        $attributes = \array_merge([], ...$attributes);

        return \array_map(static fn (\ReflectionAttribute $attribute) => $attribute?->getName(), $attributes);
    }
}