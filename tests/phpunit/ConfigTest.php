<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Tests;

use Keboola\SiSenseWriter\Config;
use Keboola\SiSenseWriter\Configuration\ConfigDefinition;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigTest extends TestCase
{
    public function testValidConfig(): void
    {
        $configArray = [
            'parameters' => [
                'db' => [
                    'host' => 'xxx',
                    'port' => 'xxx',
                    'username' => 'xxx',
                    '#password' => 'xxx',
                ],
                'tableId' => 'xxx',
                'dbName' => 'xxx',
                'items' => [
                    0 => [
                        'dbName' => 'id',
                        'name' => 'id',
                        'type' => 'int',
                        'size' => '10',
                    ],
                ],
            ],
        ];

        $config = new Config($configArray, new ConfigDefinition());

        Assert::assertArrayHasKey('db', $config->getData()['parameters']);
        Assert::assertArrayHasKey('host', $config->getData()['parameters']['db']);
        Assert::assertArrayHasKey('port', $config->getData()['parameters']['db']);
        Assert::assertArrayHasKey('username', $config->getData()['parameters']['db']);
        Assert::assertArrayHasKey('#password', $config->getData()['parameters']['db']);
        Assert::assertArrayHasKey('tableId', $config->getData()['parameters']);
        Assert::assertArrayHasKey('dbName', $config->getData()['parameters']);
        Assert::assertArrayHasKey('items', $config->getData()['parameters']);
    }

    public function testDefaultPort(): void
    {
        $configArray = [
            'parameters' => [
                'db' => [
                    'host' => 'xxx',
                    'username' => 'xxx',
                    '#password' => 'xxx',
                ],
                'tableId' => 'xxx',
                'dbName' => 'xxx',
                'items' => [
                    0 => [
                        'dbName' => 'id',
                        'name' => 'id',
                        'type' => 'int',
                        'size' => '10',
                    ],
                ],
            ],
        ];

        $config = new Config($configArray, new ConfigDefinition());
        Assert::assertEquals(30845, $config->getPort());
    }

    /**
     * @dataProvider missingNodeProvider
     */
    public function testMissingNode(array $configArray, string $missingNode, string $path): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            sprintf(
                'The child node "%s" at path "%s" must be configured.',
                $missingNode,
                $path
            )
        );
        new Config($configArray, new ConfigDefinition());
    }

    public function testGetUrlAddress(): void
    {
        $configArray = [
            'parameters' => [
                'db' => [
                    'host' => 'xxx',
                    'port' => 'xxx',
                    'username' => 'xxx',
                    '#password' => 'xxx',
                ],
                'tableId' => 'xxx',
                'dbName' => 'xxx',
                'items' => [
                    0 => [
                        'dbName' => 'id',
                        'name' => 'id',
                        'type' => 'int',
                        'size' => '10',
                    ],
                ],
            ],
        ];
        $config = new Config($configArray, new ConfigDefinition());
        Assert::assertEquals('https://xxx:xxx', $config->getUrlAddress());
    }

    public function missingNodeProvider(): array
    {
        return [
            [
                [
                    'parameters' => [
                        'db' => [
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'host',
                'root.parameters.db',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'username',
                'root.parameters.db',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                '#password',
                'root.parameters.db',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'tableId',
                'root.parameters',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'dbName',
                'root.parameters',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                    ],
                ],
                'items',
                'root.parameters',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'dbName',
                'root.parameters.items.0',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'name',
                'root.parameters.items.0',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'type',
                'root.parameters.items.0',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                            ],
                        ],
                    ],
                ],
                'size',
                'root.parameters.items.0',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                        'relationships' => [
                            [
                                'target' => [
                                    'table' => 'xxx',
                                    'column' => 'xxx',
                                ],
                            ],
                        ],
                    ],
                ],
                'column',
                'root.parameters.relationships.0',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                        'relationships' => [
                            [
                                'column' => 'xxx',
                                'target' => [
                                    'column' => 'xxx',
                                ],
                            ],
                        ],
                    ],
                ],
                'table',
                'root.parameters.relationships.0.target',
            ],
            [
                [
                    'parameters' => [
                        'db' => [
                            'host' => 'xxx',
                            'port' => 'xxx',
                            'username' => 'xxx',
                            '#password' => 'xxx',
                        ],
                        'tableId' => 'xxx',
                        'dbName' => 'xxx',
                        'items' => [
                            0 => [
                                'dbName' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                        'relationships' => [
                            [
                                'column' => 'xxx',
                                'target' => [
                                    'table' => 'xxx',
                                ],
                            ],
                        ],
                    ],
                ],
                'column',
                'root.parameters.relationships.0.target',
            ],
        ];
    }
}
