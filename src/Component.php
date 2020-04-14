<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter;

use GuzzleHttp\Client;
use Keboola\Component\BaseComponent;
use Keboola\Component\Logger;

class Component extends BaseComponent
{
    protected function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();

        $writer = new SiSenseWriter(
            $this->getDataDir(),
            $config,
            new Api($config, new Client()),
            new Logger()
        );

        $writer->execute();
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }
}
