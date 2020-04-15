<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Api\Model;

use \InvalidArgumentException;

class Datamodel
{
    private string $oid;

    private string $title;

    public static function build(array $data): self
    {
        if (!isset($data['oid'])) {
            throw new InvalidArgumentException('Oid cannot be empty.');
        }
        if (!isset($data['title'])) {
            throw new InvalidArgumentException('Title cannot be empty.');
        }
        return new self($data['oid'], $data['title']);
    }

    public function __construct(string $oid, string $title)
    {
        $this->oid = $oid;
        $this->title = $title;
    }

    public function getOid(): string
    {
        return $this->oid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
