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

Create config/workflows.php

And register here your workflows, like a config/bundles.php for symfony

Example config/workflows.php:
```php
<?php

declare(strict_types=1);

return [
    // ...
    Temporal\Samples\FileProcessing\FileProcessingWorkflow::class,
    // ...
];

```

Create rr.yaml:
```yaml
version: "3"

server:
  command: "php public/index.php"
  relay: pipes
  env:
    - APP_RUNTIME: Highcore\TemporalBundle\Runtime\Runtime 

temporal:
  address: "localhost:7233"
  namespace: 'default' # Configure a temporal namespace (you must create a namespace manually or use the default namespace named "default")
  activities:
    num_workers: 4 # Set up your worker count

# Set up your values
logs:
  mode: production
  output: stdout
  err_output: stderr
  encoding: json
  level: error

rpc:
  listen: tcp://0.0.0.0:6001
```

Example configuration:
```yaml
# config/packages/temporal.yaml
temporal:
  # Default address be localhost:7233
  address: 'localhost:7233'
  worker:
    # Set up custom worker factory if you want to use custom WorkerFactory, 
    # accepts symfony service factory format 
    #
    # Details - https://symfony.com/doc/current/service_container/factories.html
    factory: Highcore\TemporalBundle\FactoryWorkerFactory
    # Set up your own consumption queue for your Temporal Worker, you can set ENV or use string value
    queue: '%env(TEMPORAL_WORKER_QUEUE)%'
    data-converter:
      # Set up your custom Temporal\DataConverter\DataConverterInterface implementation
      class: Temporal\DataConverter\DataConverter
      # Customize the data converters, DO NOT CHANGE if you do not know what it is
      # Details - https://docs.temporal.io/dev-guide/php/foundations#activity-return-values
      #
      # Sorting order from top to bottom is very, very important
      converters:
        - Temporal\DataConverter\NullConverter
        - Temporal\DataConverter\BinaryConverter
        - Temporal\DataConverter\ProtoJsonConverter
        - Highcore\TemporalBundle\DataConverter\SymfonySerializerJsonClassObjectConverter
        - Temporal\DataConverter\JsonConverter
  workflow-client:
    options:
      # Set up custom namespace, by default will be used 'default' namespace
      namespace: monoplace

    # Set up custom workflow client factory
    # accepts any class which implements Highcore\TemporalBundle\WorkflowClientFactoryInterface
    factory: Highcore\TemporalBundle\WorkflowClientFactory
```

Example activity interface:
```php
<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Samples\FileProcessing;

use Temporal\Activity\ActivityInterface;

#[ActivityInterface(prefix:"FileProcessing.")]
interface StoreActivitiesInterface
{
    /**
     * Upload file to remote location.
     *
     * @param string $localFileName file to upload
     * @param string $url remote location
     */
    public function upload(string $localFileName, string $url): void;

    /**
     * Process file.
     *
     * @param string $inputFileName source file name @@return processed file name
     * @return string
     */
    public function process(string $inputFileName): string;

    /**
     * Downloads file to local disk.
     *
     * @param string $url remote file location
     * @return TaskQueueFilenamePair local task queue and downloaded file name
     */
    public function download(string $url): TaskQueueFilenamePair;
}
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

use Highcore\TemporalBundle\Attribute\AsActivity;use Psr\Log\LoggerInterface;
use Temporal\SampleUtils\Logger;

#[AsActivity]
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

Example workflow interface:
```php
<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Samples\FileProcessing;

use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface FileProcessingWorkflowInterface
{
    #[WorkflowMethod("FileProcessing")]
    public function processFile(
        string $sourceURL,
        string $destinationURL
    );
}
```

Example workflow:

```php
<?php
# https://github.com/temporalio/samples-php/blob/master/app/src/FileProcessing/FileProcessingWorkflow.php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Samples\FileProcessing;

use Carbon\CarbonInterval;
use Highcore\TemporalBundle\Attribute\AsWorkflow;use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Workflow;

#[AsWorkflow]
class FileProcessingWorkflow implements FileProcessingWorkflowInterface
{
    public const DEFAULT_TASK_QUEUE = 'default';

    /** @var ActivityProxy|StoreActivitiesInterface */
    private $defaultStoreActivities;

    public function __construct()
    {
        $this->defaultStoreActivities = Workflow::newActivityStub(
            StoreActivitiesInterface::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::minute(5))
                ->withTaskQueue(self::DEFAULT_TASK_QUEUE)
        );
    }

    public function processFile(string $sourceURL, string $destinationURL)
    {
        /** @var TaskQueueFilenamePair $downloaded */
        $downloaded = yield $this->defaultStoreActivities->download($sourceURL);

        $hostSpecificStore = Workflow::newActivityStub(
            StoreActivitiesInterface::class,
            ActivityOptions::new()
                ->withScheduleToCloseTimeout(CarbonInterval::minute(5))
                ->withTaskQueue($downloaded->hostTaskQueue)
        );

        // Call processFile activity to zip the file.
        // Call the activity to process the file using worker-specific task queue.
        $processed = yield $hostSpecificStore->process($downloaded->filename);

        // Call upload activity to upload the zipped file.
        yield $hostSpecificStore->upload($processed, $destinationURL);

        return 'OK';
    }
}
```

Now you can run:
```bash
rr serve rr.yaml
```

And call workflow by:
```php
<?php
declare(strict_types=1);

namespace Highcore\TemporalBundle\Example;

use Temporal\Client\WorkflowClientInterface;
use Temporal\Workflow\WorkflowRunInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\RetryOptions;

final class ExampleWorkflowRunner {

    public function __construct(private readonly WorkflowClientInterface $workflowClient)
    {
    }
    
    public function run(): void
    {
        /** @var \Temporal\Samples\FileProcessing\FileProcessingWorkflowInterface $workflow */
        $workflow = $this->workflowClient->newWorkflowStub(
            \Temporal\Samples\FileProcessing\FileProcessingWorkflowInterface::class, 
            WorkflowOptions::new()
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withMaximumAttempts(3)
                        ->withNonRetryableExceptions(\LogicException::class)
                )
        );
        
        // Start Workflow async, with no-wait result
        /** @var WorkflowRunInterface $result */
        $result = $this->workflowClient->start($workflow, 'https://example.com/example_file', 's3://s3.example.com');
        
        echo 'Run ID: ' . $result->getExecution()->getRunID();
        
        // Or you can call workflow sync with wait result
        $result = $workflow->processingFile('https://example.com/example_file', 's3://s3.example.com');
        
        echo $result; // OK
    }

}
```

More php examples you can find [here](https://github.com/temporalio/samples-php)

## Credits

- [Official Temporal PHP SDK](https://github.com/temporalio/sdk-php)
- [Official Temporal PHP Samples](https://github.com/temporalio/samples-php)
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

