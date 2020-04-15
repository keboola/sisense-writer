<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig
{
    public function getHost(): string
    {
        return $this->getValue(['parameters', 'host']);
    }

    public function getPort(): string
    {
        return $this->getValue(['parameters', 'port']);
    }

    public function getUrlAddress(): string
    {
        $protocol = '';
        if (preg_match('~^https?://~', $this->getHost())) {
            $protocol = 'https://';
        }
        return sprintf(
            '%s%s:%s',
            $protocol,
            $this->getHost(),
            $this->getPort()
        );
    }

    public function getUsername(): string
    {
        return $this->getValue(['parameters', 'username']);
    }

    public function getPassword(): string
    {
        return $this->getValue(['parameters', '#password']);
    }

    public function getTableId(): string
    {
        return $this->getValue(['parameters', 'tableId']);
    }

    public function getDatamodelName(): string
    {
        return $this->getValue(['parameters', 'datamodelName']);
    }

    public function getColumns(): array
    {
        return $this->getValue(['parameters', 'columns']);
    }

    public function getRelationships(): array
    {
        return $this->getValue(['parameters', 'relationships']);
    }
}
