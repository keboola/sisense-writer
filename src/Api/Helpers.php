<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Api;

use Keboola\Component\UserException;

class Helpers
{
    public static function reformatColumns(array $columns): array
    {
        $reformatColumns = [];
        foreach ($columns as $column) {
            $reformatColumn = [
                'id' => $column['id'],
                'name' => $column['name'],
                'type' => self::getType($column['type']),
                'hidden' => false,
                'indexed' => false,
                'description' => null,
                'import' => true,
                'isCustom' => false,
                'expression' => null,
            ];
            $reformatColumn = array_merge($reformatColumn, self::getLength($column['size']));

            $reformatColumns[] = $reformatColumn;
        }
        return $reformatColumns;
    }

    public static function getType(string $type): int
    {
        switch (strtoupper($type)) {
            case 'BIGINT':
                return 0;
            case 'BIT':
                return 2;
            case 'CHAR':
                return 3;
            case 'DATE':
                return 31;
            case 'DATETIME':
                return 4;
            case 'DECIMAL':
                return 5;
            case 'FLOAT':
                return 6;
            case 'INT':
            case 'INTEGER':
                return 8;
            case 'SMALLINT':
                return 16;
            case 'TEXT':
                return 18;
            case 'TIME':
                return 32;
            case 'TIMESTAMP':
                return 19;
            case 'TINYINT':
                return 20;
            case 'VARCHAR':
                return 22;
            default:
                throw new UserException(sprintf('Unrecognized column type "%s"', $type));
        }
    }

    public static function getLength(string $length): array
    {
        if (strpos($length, ',')) {
            $size = 0;
            [$precision, $scale] = explode(',', $length);
        } else {
            $size = (int) $length;
            $precision = 0;
            $scale = 0;
        }

        return [
            'size' => $size,
            'precision' => $precision,
            'scale' => $scale,
        ];
    }
}
