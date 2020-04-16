<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Api\Model;

use \InvalidArgumentException;

class TableColumn
{
    private string $oid;

    private string $name;

    private int $type;

    private int $size;

    private int $precision;

    private int $scale;

    public static function build(array $data): self
    {
        if (!isset($data['oid'])) {
            throw new InvalidArgumentException('Oid cannot be empty.');
        }
        if (!isset($data['name'])) {
            throw new InvalidArgumentException('Name cannot be empty.');
        }
        if (!isset($data['type'])) {
            throw new InvalidArgumentException('Type cannot be empty.');
        }
        if (!isset($data['size'])) {
            throw new InvalidArgumentException('Size cannot be empty.');
        }
        if (!isset($data['precision'])) {
            throw new InvalidArgumentException('Precision cannot be empty.');
        }
        if (!isset($data['scale'])) {
            throw new InvalidArgumentException('Scale cannot be empty.');
        }

        return new self(
            $data['oid'],
            $data['name'],
            $data['type'],
            $data['size'],
            $data['precision'],
            $data['scale']
        );
    }

    public function __construct(string $id, string $name, int $type, int $size, int $precision, int $scale)
    {
        $this->oid = $id;
        $this->name = $name;
        $this->type = $type;
        $this->size = $size;
        $this->precision = $precision;
        $this->scale = $scale;
    }

    public function getOid(): string
    {
        return $this->oid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }

    public function getScale(): int
    {
        return $this->scale;
    }
}
