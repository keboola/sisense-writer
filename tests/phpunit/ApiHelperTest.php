<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Tests;

use Keboola\Component\UserException;
use Keboola\SiSenseWriter\Api\Helpers;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ApiHelperTest extends TestCase
{
    public function testReformatColumns(): void
    {
        $input = [
            [
                'id' => 'testId',
                'name' => 'testName',
                'type' => 'decimal',
                'size' => '5,2',
            ],
        ];
        $expectedOutput = [
            [
                'id' => 'testId',
                'name' => 'testName',
                'type' => 5,
                'hidden' => false,
                'indexed' => false,
                'description' => null,
                'import' => true,
                'isCustom' => false,
                'expression' => null,
                'size' => 0,
                'precision' => 5,
                'scale' => 2,
            ],
        ];

        Assert::assertEquals($expectedOutput, Helpers::reformatColumns($input));
    }

    /**
     * @dataProvider columnTypesProvider
     */
    public function testGetType(string $type, int $expectedType): void
    {
        Assert::assertEquals($expectedType, Helpers::getType($type));
    }

    public function testInvalidType(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Unrecognized column type "invalidType"');
        Helpers::getType('invalidType');
    }

    /**
     * @dataProvider columnLengthProvider
     */
    public function testGetLength(string $length, array $expectedOutput): void
    {
        Assert::assertEquals($expectedOutput, Helpers::getLength($length));
    }

    public function columnTypesProvider(): array
    {
        return [
            [
                'BIGINT',
                0,
            ],
            [
                'BIT',
                2,
            ],
            [
                'CHAR',
                3,
            ],
            [
                'DATE',
                31,
            ],
            [
                'DATETIME',
                4,
            ],
            [
                'DECIMAL',
                5,
            ],
            [
                'FLOAT',
                6,
            ],
            [
                'INT',
                8,
            ],
            [
                'INTEGER',
                8,
            ],
            [
                'SMALLINT',
                16,
            ],
            [
                'TEXT',
                18,
            ],
            [
                'TIME',
                32,
            ],
            [
                'TIMESTAMP',
                19,
            ],
            [
                'TINYINT',
                20,
            ],
            [
                'VARCHAR',
                22,
            ],
        ];
    }

    public function columnLengthProvider(): array
    {
        return [
            [
                '1',
                [
                    'size' => 1,
                    'precision' => 0,
                    'scale' => 0,
                ],
            ],
            [
                '1,6',
                [
                    'size' => 0,
                    'precision' => 1,
                    'scale' => 6,
                ],
            ],
            [
                '0,1',
                [
                    'size' => 0,
                    'precision' => 0,
                    'scale' => 1,
                ],
            ],
        ];
    }
}
