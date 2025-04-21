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

Example rr.yaml:
```yaml
version: "3"

server:
  command: "php bin/console temporal:workflow:runtime"
  user: "backend" # Set up your user, or remove this value
  group: "backend" # Set up your group, or remove this value

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
    # Set up your own consumption queue for your Temporal Worker, you can set ENV or use string value
    queue: '%env(TEMPORAL_WORKER_QUEUE)%'
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
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Workflow;

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

### Creating a testing environment

In order to enable the testing functionality, it is required to make some changes to the configuration and follow a few steps from the Temporal SDK documentaion.

First of all, you will have to add configuration to your app, with adding a `config/packages/test/temporal.yaml` file inside your project, with the following contents:
```yaml
temporal:
    worker:
        factory: Temporal\Testing\WorkerFactory

        testing:
            enabled: true
            activity_invocation_cache: Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache
```

The Temporal SDK documentation does not provide some extensive documentation about how to configure your testing environment. Some generic examples are provided, but you will usually need to dig into the internals to figure out the proper way to do it.

Here is a simplified checklist in the context of a Symfony or API Platform project using this bundle:

1. Install PHPUnit in your preferred version
2. Make sure your PHPUnit configuration is providing a bootstrap file, as described in the documentation [ [1](https://symfony.com/doc/current/testing/bootstrap.html) ] and [ [2](https://docs.phpunit.de/en/10.5/configuration.html#the-bootstrap-attribute) ]
```xml
<!-- phpunit.xml.dist -->
<?xml version="1.0" encoding="UTF-8" ?>
<phpunit
    bootstrap="tests/bootstrap.php"
>
    <!-- ... -->
</phpunit>
```
3. In your bootstrap file lcated in `tests/bootstrap.php` you should have the following content:
```php
<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

$environment = \Temporal\Testing\Environment::create();
// The ./rr file is created after running installation through composer
// see https://github.com/roadrunner-server/roadrunner?tab=readme-ov-file#installation-via-composer
// the Temporal\Testing\Environment class does not support other ways of installation
//$environment->start('./rr serve -w /app -c .rr.yaml');
$environment->start('./rr serve -c tests/.rr.test.yaml -w /app');
register_shutdown_function(fn () => $environment->stop());
```
4. You will then need to create a `tests/.rr.test.yaml` file with the following contents:
```yaml
version: '3'

server:
  command: "bin/console temporal:workflow:runtime --env=test"
#  user: "backend" # Set up your user, or remove this value
#  group: "backend" # Set up your group, or remove this value

temporal:
  address: "${TEMPORAL_TEST_ADDRESS:-localhost:7233}"
  namespace: "${TEMPORAL_TEST_WORKER_QUEUE:-default}" # Configure a temporal namespace (you must create a namespace manually or use the default namespace named "default")
  activities:
    num_workers: 1 # Set up your worker count

# Set up your values
logs:
  mode: development
  output: stdout
  err_output: stderr
  encoding: console
  level: debug


rpc:
    listen: 'tcp://127.0.0.1:6001'
    
kv:
    test:
        driver: memory
        config:
            interval: 10
```


### Example of a PHPUnit test

#### The `SimpleActivity` and `SimpleWorkflow` examples

Let's assume you have an Activity declared in the `App\SimpleActivity` class as follows:

```php
<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('temporal.activity.registry')]
final class SimpleActivity implements SimpleActivityInterface
{
    public function greeting(string $input): string
    {
        return $input;
    }
}
```

And the corresponding interface `App\SimpleActivityInterface` would be as follows:

```php
<?php

namespace App;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface(prefix: "SimpleActivity.")]
interface SimpleActivityInterface
{
    #[ActivityMethod('greeting')]
    public function greeting(string $input): string;
}
```

Also, you would need to have a Workflow declared in the `App\SimpleWorkflow`, as the following:

```php
<?php

declare(strict_types=1);

namespace App;

use App\Shared\Infrastructure\Cloud\CloudEnvironmentStates;
use App\Shared\Infrastructure\Cloud\CreateCloudEnvironmentActivityInterface;
use App\Shared\Infrastructure\Cloud\MissingCloudProviderInformationException;
use App\Shared\Infrastructure\Cloud\OVHCloud\MissingRegionInformationException;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Common\RetryOptions;
use Temporal\Internal\Workflow\ActivityProxy;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class SimpleWorkflow
{

    /** @var ActivityProxy<SimpleActivityInterface> */
    private ActivityProxy $activities;

    public function __construct()
    {
        $this->activities = Workflow::newActivityStub(
            SimpleActivityInterface::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(CarbonInterval::minutes(15))
                // disable retries for example to run faster
                ->withRetryOptions(
                    RetryOptions::new()
                        ->withMaximumAttempts(5)
                        ->withInitialInterval(10)
                )
        );
    }

    #[WorkflowMethod(name: 'greeting')]
    public function greeting(string $name): \Generator
    {
        return yield $this->activities->greeting($name);
    }
}
```

#### Create your first PHPUnit test for your Workflow

In order to test your workflow, the preferred method is to mock every activity. The reason you should do this is to make your unit tests isolated from any I/O or other service that could mess with the results of your tests (Database, Network, Files, etc.). Also, this is to prevent tests from interacting with each other, each test should be run independently and should not interact with other test.

This is the reasons why it is preferred to use a generic `PHPUnit\Framework\TestCase` provided by PHPUnit than the `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` provided by Symfony.

Here is an example to test the `App\SimpleWorkflow` class with a generic test:

```php
<?php

declare(strict_types=1);

namespace App\Tests;

use App\SimpleWorkflow;
use Carbon\CarbonInterval;
use PHPUnit\Framework\TestCase;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Testing\ActivityMocker;

final class SimpleWorkflowTest extends TestCase
{
    private WorkflowClient $workflowClient;
    private ActivityMocker $activityMocks;

    protected function setUp(): void
    {
        $this->workflowClient = new WorkflowClient(ServiceClient::create('localhost:7233'));
        $this->activityMocks = new ActivityMocker();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->activityMocks->clear();
        parent::tearDown();
    }

    public function testWorkflowReturnsUpperCasedInput(): void
    {
        $this->activityMocks->expectCompletion('SimpleActivity.greeting', 'hello');
        $workflow = $this->workflowClient->newWorkflowStub(SimpleWorkflow::class);

        $run = $this->workflowClient->start($workflow, 'hello');

        $this->assertSame('hello', $run->getResult('string'));
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

