<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Api\Model;

use \InvalidArgumentException;

class Dataset
{

    private string $oid;

    private string $name;

    public static function build(array $data): self
    {
        if (!isset($data['oid'])) {
            throw new InvalidArgumentException('Oid cannot be empty.');
        }
        if (!isset($data['name'])) {
            throw new InvalidArgumentException('Name cannot be empty.');
        }
        return new self($data['oid'], $data['name']);
    }

    public function __construct(string $oid, string $name)
    {
        $this->oid = $oid;
        $this->name = $name;
    }

    public function getOid(): string
    {
        return $this->oid;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
