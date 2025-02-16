<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle\Registry;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\LocalActivityInterface;

final class ActivityRegistry
{
    private array $activities = [];

    public function add($activity): void
    {
        $reflection = new \ReflectionObject($activity);

        if (!$this->isActivity($reflection)) {
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

    private function isActivity(\ReflectionObject $reflection): bool
    {
        if (\count($reflection->getAttributes(ActivityInterface::class)) >= 1 || \count($reflection->getAttributes(LocalActivityInterface::class)) >= 1) {
            return true;
        }

        foreach ($reflection->getInterfaces() as $interface) {
            if (\count($interface->getAttributes(ActivityInterface::class)) >= 1) {
                return true;
            }
        }

        return false;
    }
}
