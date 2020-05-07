<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter;

use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\SiSenseWriter\Configuration\TestConnectionConfigDefinition;
use Keboola\SiSenseWriter\Configuration\ConfigDefinition;

class Component extends BaseComponent
{
    private const ACTION_RUN = 'run';

    private const ACTION_TEST_CONNECTION = 'testConnection';

    protected function run(): void
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $componentFactory = new ComponentFactory($this->getLogger());
        $componentFactory->create($this->getDataDir(), $config)->execute();
    }

    public function testConnection(): array
    {
        /** @var Config $config */
        $config = $this->getConfig();
        $componentFactory = new ComponentFactory($this->getLogger());
        return $componentFactory->create($this->getDataDir(), $config)->testConnection();
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getSyncActions(): array
    {
        return [
            self::ACTION_TEST_CONNECTION => 'testConnection',
        ];
    }

    protected function getConfigDefinitionClass(): string
    {
        $action = $this->getRawConfig()['action'] ?? self::ACTION_RUN;
        switch ($action) {
            case self::ACTION_RUN:
                return ConfigDefinition::class;
            case self::ACTION_TEST_CONNECTION:
                return TestConnectionConfigDefinition::class;
            default:
                throw new UserException(sprintf('Unexpected action "%s"', $action));
        }
    }
}
