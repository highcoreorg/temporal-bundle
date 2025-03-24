Writing Tests
===

Configuration
---

In order to enable the testing functionality, it is required to make some changes to the configuration and follow a few
steps from the Temporal SDK documentation.

First of all, you will have to add configuration to your app, with adding a `config/packages/test/temporal.yaml` file
inside your project, with the following contents:

```yaml
temporal:
    worker:
        factory: Temporal\Testing\WorkerFactory

        testing:
            enabled: true
            activity_invocation_cache: Temporal\Worker\ActivityInvocationCache\RoadRunnerActivityInvocationCache
```

### Creating a testing environment

The Temporal SDK documentation does not provide some extensive documentation about how to configure your testing 
environment. Some generic examples are provided, but you will usually need to dig into the internals to figure out the
proper way to do it.

Here is a simplified checklist in the context of a Symfony or API Platform project using this bundle:

1. Install PHPUnit in your preferred version, or install the latest with `composer require phpunit/phpunit`
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
  address: "${TEMPORAL_TEST_ADDRESS:-127.0.0.1:7233}" # It is important to let 127.0.0.1 here, as you will use the testing server, launched by the Temporal\Testing\Environment class.
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

You will then have a complete testing environment.

Writing your first PHPUnit test with Temporal
---

### Activity and Workflow examples

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

### How to write a PHPUnit test for your Workflow

In order to test your workflow, the preferred method is to mock every activity. The reason you should do this is to make
your unit tests isolated from any I/O or other service that could mess with the results of your tests (Database, 
Network, Files, etc.). Also, this is to prevent tests from interacting with each other, each test should be run 
independently and should not interact with other test.

This is the reasons why it is preferred to use a generic `PHPUnit\Framework\TestCase` provided by PHPUnit than the 
`Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` provided by Symfony.

However, it is not always possible. eg. if you have integration tests requiring access to a database and some data
fixtures. In those cases, you will be required to keep in mind that a Temporal environment requires 2 PHP processes and
those 2 processes needs to access to consistent data between those processes.

Here is an example to test the `App\SimpleWorkflow` Workflow class with a generic test:

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
        $this->workflowClient = new WorkflowClient(ServiceClient::create('127.0.0.1:7233'));
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

References
---

* [highcoreorg/temporal-bundle#12](https://github.com/highcoreorg/temporal-bundle/pull/12)
* [PHPUnit with Symfony](https://symfony.com/doc/current/testing/bootstrap.html)
* [PHPUnit bootstrap attribute](https://docs.phpunit.de/en/10.5/configuration.html#the-bootstrap-attribute)