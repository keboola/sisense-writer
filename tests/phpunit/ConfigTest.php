<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Tests;

use Keboola\SiSenseWriter\Config;
use Keboola\SiSenseWriter\ConfigDefinition;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigTest extends TestCase
{
    public function testValidConfig(): void
    {
        $configArray = [
            'parameters' => [
                'host' => 'xxx',
                'port' => 'xxx',
                'username' => 'xxx',
                '#password' => 'xxx',
                'tableId' => 'xxx',
                'datamodelName' => 'xxx',
                'columns' => [
                    0 => [
                        'id' => 'id',
                        'name' => 'id',
                        'type' => 'int',
                        'size' => '10',
                    ],
                ],
            ],
        ];

        $config = new Config($configArray, new ConfigDefinition());

        Assert::assertArrayHasKey('host', $config->getData()['parameters']);
        Assert::assertArrayHasKey('port', $config->getData()['parameters']);
        Assert::assertArrayHasKey('username', $config->getData()['parameters']);
        Assert::assertArrayHasKey('#password', $config->getData()['parameters']);
        Assert::assertArrayHasKey('tableId', $config->getData()['parameters']);
        Assert::assertArrayHasKey('datamodelName', $config->getData()['parameters']);
        Assert::assertArrayHasKey('columns', $config->getData()['parameters']);
    }

    public function testDefaultPort(): void
    {
        $configArray = [
            'parameters' => [
                'host' => 'xxx',
                'username' => 'xxx',
                '#password' => 'xxx',
                'tableId' => 'xxx',
                'datamodelName' => 'xxx',
                'columns' => [
                    0 => [
                        'id' => 'id',
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
                'host' => 'xxx',
                'port' => 'xxx',
                'username' => 'xxx',
                '#password' => 'xxx',
                'tableId' => 'xxx',
                'datamodelName' => 'xxx',
                'columns' => [
                    0 => [
                        'id' => 'id',
                        'name' => 'id',
                        'type' => 'int',
                        'size' => '10',
                    ],
                ],
            ],
        ];
        $config = new Config($configArray, new ConfigDefinition());
        Assert::assertEquals('http://xxx:xxx', $config->getUrlAddress());
    }

    public function missingNodeProvider(): array
    {
        return [
            [
                [
                    'parameters' => [
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'host',
                'root.parameters',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'username',
                'root.parameters',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                '#password',
                'root.parameters',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
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
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'datamodelName',
                'root.parameters',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                    ],
                ],
                'columns',
                'root.parameters',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'name' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'id',
                'root.parameters.columns.0',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
                                'type' => 'int',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'name',
                'root.parameters.columns.0',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
                                'name' => 'id',
                                'size' => '10',
                            ],
                        ],
                    ],
                ],
                'type',
                'root.parameters.columns.0',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
                                'name' => 'id',
                                'type' => 'int',
                            ],
                        ],
                    ],
                ],
                'size',
                'root.parameters.columns.0',
            ],
            [
                [
                    'parameters' => [
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
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
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
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
                        'host' => 'xxx',
                        'port' => 'xxx',
                        'username' => 'xxx',
                        '#password' => 'xxx',
                        'tableId' => 'xxx',
                        'datamodelName' => 'xxx',
                        'columns' => [
                            0 => [
                                'id' => 'id',
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
