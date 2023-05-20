# Symfony Temporal Bundle

## Description

This is a wrapper package for the [official PHP SDK](https://github.com/temporalio/sdk-php) with Activity Registry and full-configurable worker and workflow client.

## Table of Contents (Optional)

If your README is long, add a table of contents to make it easy for users to find what they need.

- [Installation](#installation)
- [Usage](#usage)
- [Credits](#credits)
- [License](#license)

## Installation

Use this command to install
`composer require highcore/temporal-bundle`

## Usage

Simple configuration:
```yaml
# config/packages/temporal.yaml
temporal:
  # Use address from ENV variable, by default will be localhost:7233
  address: '%env(TEMPORAL_ADDRESS)%'
  worker:
    # Set up custom worker factory if you want to use custom WorkerFactory, 
    # accepts symfony service factory format 
    #
    # Details - https://symfony.com/doc/current/service_container/factories.html
    factory: Highcore\TemporalBundle\WorkerFactory
    # Set up your own consumption queue for your Temporal Worker
    queue: default
    data-converter:
      # Set up your custom Temporal\DataConverter\DataConverterInterface implementation
      class: Temporal\DataConverter\DataConverter
      # Customize the data converters, DO NOT CHANGE if you do not know what it is
      # Details - https://legacy-documentation-sdks.temporal.io/typescript/data-converters
      #
      # Sorting order from top to bottom is very, very important
      converters:
        - Temporal\DataConverter\NullConverter
        - Temporal\DataConverter\BinaryConverter
        - Temporal\DataConverter\ProtoJsonConverter
        - Highcore\TemporalBundle\DataConverter\ClassObjectConverter
        - Temporal\DataConverter\JsonConverter
  workflow-client:
    options:
      # Set up custom namespace, by default will be used 'default' namespace
      namespace: monoplace

    # Set up custom workflow client factory
    # accepts any class which implements Highcore\TemporalBundle\WorkflowClientFactoryInterface
    factory: Highcore\TemporalBundle\WorkflowClientFactory
```

Example activity:
```php
<?php
# https://github.com/temporalio/samples-php/blob/master/app/src/FileProcessing/StoreActivity.php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Samples\FileProcessing;

use Psr\Log\LoggerInterface;
use Temporal\SampleUtils\Logger;

class StoreActivity implements StoreActivitiesInterface
{
    private static string $taskQueue;
    private LoggerInterface $logger;

    public function __construct(string $taskQueue = FileProcessingWorkflow::DEFAULT_TASK_QUEUE)
    {
        self::$taskQueue = $taskQueue;
        $this->logger = new Logger();
    }

    public function upload(string $localFileName, string $url): void
    {
        if (!is_file($localFileName)) {
            throw new \InvalidArgumentException("Invalid file type: " . $localFileName);
        }

        // Faking upload to simplify sample implementation.
        $this->log('upload activity: uploaded from %s to %s', $localFileName, $url);
    }

    public function process(string $inputFileName): string
    {
        try {
            $this->log('process activity: sourceFile=%s', $inputFileName);
            $processedFile = $this->processFile($inputFileName);
            $this->log('process activity: processed file=%s', $processedFile);

            return $processedFile;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function download(string $url): TaskQueueFilenamePair
    {
        try {
            $this->log('download activity: downloading %s', $url);

            $data = file_get_contents($url);
            $file = tempnam(sys_get_temp_dir(), 'demo');

            file_put_contents($file, $data);

            $this->log('download activity: downloaded from %s to %s', $url, realpath($file));

            return new TaskQueueFilenamePair(self::$taskQueue, $file);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function processFile(string $filename): string
    {
        // faking processing for simplicity
        return $filename;
    }

    /**
     * @param string $message
     * @param mixed ...$arg
     */
    private function log(string $message, ...$arg)
    {
        // by default all error logs are forwarded to the application server log and docker log
        $this->logger->debug(sprintf($message, ...$arg));
    }
}
```

Register with symfony service container:
```php
<?php

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $services->defaults()
        ->public()
        ->autowire(true)
        ->autoconfigure(true);

    $services->set(Temporal\Samples\FileProcessing\StoreActivity::class)
        // Setting a "label to your activity" will add the activity to the ActivityRegistry,
        // allowing your employee to use this activity in your Workflow
        ->tag('temporal.activity.registry');
```

## Credits

- [Official PHP SDK](https://github.com/temporalio/sdk-php)
- [Symfony Framework](https://github.com/symfony/symfony)

## License

MIT License

Copyright (c) 2023 Highcore.org

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

