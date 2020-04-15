<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Api\Model;

use \InvalidArgumentException;

class Table
{
    private string $oid;

    private string $name;

    /** @var TableColumn[] */
    private $columns = [];

    public static function build(array $data): self
    {
        if (!isset($data['oid'])) {
            throw new InvalidArgumentException('Oid cannot be empty.');
        }
        if (!isset($data['name'])) {
            throw new InvalidArgumentException('Name cannot be empty.');
        }
        $table = new self($data['oid'], $data['name']);

        foreach ($data['columns'] as $column) {
            $table->addColumn(TableColumn::build($column));
        }
        return $table;
    }

    public function __construct(string $oid, string $name)
    {
        $this->oid = $oid;
        $this->name = $name;
    }

    public function addColumn(TableColumn $column): self
    {
        $this->columns[] = $column;
        return $this;
    }

    public function getOid(): string
    {
        return $this->oid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }
}
