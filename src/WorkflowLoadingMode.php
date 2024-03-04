<?php

declare(strict_types=1);

namespace Highcore\TemporalBundle;

enum WorkflowLoadingMode: string
{
    /**
     * Loading from file config/workflows.php
     *
     * @var string
     */
    case FileMode = 'file';

    /**
     * Loading from file symfony di container by WorkflowInterface attribute.
     *
     * @var string
     */
    case ContainerMode = 'container';
}