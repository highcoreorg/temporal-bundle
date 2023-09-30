<?php

declare(strict_types=1);

namespace Tests\Highcore\TemporalBundle\Factory;

use Highcore\TemporalBundle\Factory\ClientOptionsFactory;
use PHPUnit\Framework\TestCase;

class ClientOptionsFactoryTest extends TestCase
{
    /**
     * @dataProvider data
     */
    public function testCreateFromArray($namespace, $identity, $queryRejectionCondition)
    {
        $factory = new ClientOptionsFactory();

        $options = $factory->createFromArray([
            'namespace'                 => $namespace,
            'identity'                  => $identity,
            'query-rejection-condition' => $queryRejectionCondition,
        ]);

        $this->assertEquals($namespace, $options->namespace);
        $this->assertEquals($identity, $options->identity);
        $this->assertEquals($queryRejectionCondition, $options->queryRejectionCondition);
    }

    public static function data(): array
    {
        return [
            ['namespace', 'identity', 123],
            ['', 'another', 456],
        ];
    }
}
