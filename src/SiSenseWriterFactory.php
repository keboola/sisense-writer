<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter;

use GuzzleHttp\Client;
use Keboola\SiSenseWriter\Api\Api;
use Psr\Log\LoggerInterface;

class SiSenseWriterFactory
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function create(string $dataDir, Config $config): SiSenseWriter
    {
        $api = new Api($config, new Client());

        return new SiSenseWriter(
            $dataDir,
            $config,
            $api,
            $this->logger
        );
    }
}
