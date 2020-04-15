<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter;

use Keboola\Component\UserException;
use Psr\Log\LoggerInterface;

class SiSenseWriter
{
    private string $dataDir;

    private Api $api;

    private Config $config;

    private LoggerInterface $logger;

    public function __construct(string $dataDir, Config $config, Api $api, LoggerInterface $logger)
    {
        $this->dataDir = $dataDir;
        $this->api = $api;
        $this->config = $config;
        $this->logger = $logger;

        $this->api->login();
    }

    public function execute(): void
    {
        $csvFile = $this->api->uploadFile(
            sprintf('%s/in/tables/', $this->dataDir),
            sprintf('%s.csv', $this->config->getTableId())
        );

        $datamodel = $this->createAndGetDatamodel();

        $dataset = $this->createAndGetDataset($datamodel, $csvFile);

        $table = $this->createAndGetTable($datamodel, $dataset);

        if ($this->config->getRelationships()) {
            $this->createRelationships($datamodel, $dataset, $table);
        }

        $this->buildData($datamodel);
    }

    private function createAndGetDatamodel(): array
    {
        $datamodel = $this->api->getDatamodel($this->config->getDatamodelName());
        if (is_null($datamodel)) {
            $this->logger->info(sprintf('Creating new datamodel "%s"', $this->config->getDatamodelName()));
            $datamodel = $this->api->createDatamodel($this->config->getDatamodelName());
        } else {
            $this->logger->info(sprintf('Update existing datamodel "%s"', $datamodel['title']));
        }
        return $datamodel;
    }

    private function createAndGetDataset(array $datamodel, string $csvFile): array
    {
        $dataset = $this->api->getDataset(
            $datamodel['oid'],
            $this->api->getDatasetName(
                $this->config->getDatamodelName(),
                $this->config->getTableId()
            )
        );
        if (is_null($dataset)) {
            $this->logger->info(sprintf('Creating new dataset "%s"', $this->config->getTableId()));
            $dataset = $this->api->createDataset(
                $datamodel['oid'],
                $this->api->getDatasetName(
                    $this->config->getDatamodelName(),
                    $this->config->getTableId()
                ),
                $csvFile,
                sprintf('%s.csv', $this->config->getTableId())
            );
        } else {
            $this->logger->info(sprintf('Update existing dataset "%s"', $dataset['name']));
            $dataset = $this->api->updateDataset(
                $datamodel['oid'],
                $dataset['oid'],
                $csvFile,
                sprintf('%s.csv', $this->config->getTableId())
            );
        }
        return $dataset;
    }

    private function createAndGetTable(array $datamodel, array $dataset): array
    {
        $table = $this->api->getTable($datamodel['oid'], $dataset['oid'], $this->config->getTableId());
        if (is_null($table)) {
            $this->logger->info(sprintf('Creating new table "%s"', $this->config->getTableId()));
            $table = $this->api->createTable(
                $datamodel['oid'],
                $dataset['oid'],
                $this->config->getTableId(),
                $this->config->getColumns()
            );
        } else {
            $this->logger->info(sprintf('Update existing table "%s"', $table['name']));
            $table = $this->api->updateTable(
                $datamodel['oid'],
                $dataset['oid'],
                $table['oid'],
                $this->config->getColumns()
            );
        }
        return $table;
    }

    private function createRelationships(array $datamodel, array $dataset, array $sourceTable): void
    {
        foreach ($this->config->getRelationships() as $relationship) {
            $destinationTable = $this->api->getTableByName($datamodel['oid'], $relationship['target']['table']);
            $sourceColumn = array_values(
                array_filter($sourceTable['columns'], function ($column) use ($relationship) {
                    if ($relationship['column'] === $column['id']) {
                        return true;
                    }
                    return false;
                })
            );

            $destinationColumn = array_values(
                array_filter($destinationTable['table']['columns'], function ($column) use ($relationship) {
                    if ($relationship['target']['column'] === $column['id']) {
                        return true;
                    }
                    return false;
                })
            );
            $this->logger->info('Creating relationship');
            $this->api->createRelationship(
                $datamodel['oid'],
                [
                    'dataset' => $dataset['oid'],
                    'table' => $sourceTable['oid'],
                    'column' => $sourceColumn[0]['oid'],
                ],
                [
                    'dataset' => $destinationTable['dataset_oid'],
                    'table' => $destinationTable['table']['oid'],
                    'column' => $destinationColumn[0]['oid'],
                ]
            );
        }
    }

    private function buildData(array $datamodel): void
    {
        $this->logger->info('Start build data');
        $buildId = $this->api->build($datamodel['oid'], 'full');
        $oldBuildStatus = $buildStatus = null;
        while (in_array($buildStatus, [null, 'waiting', 'building'])) {
            $buildStatus = $this->api->getBuildStatus($buildId);
            if ($buildStatus === 'failed') {
                $this->logger->alert(sprintf('Build data with status "%s"', $buildStatus));
                $sisenceFailedUrl = $this->config->getUrlAddress() . '/app/data/cubes/' . $datamodel['oid'];
                throw new UserException(
                    sprintf('Build failed. Please check the error in "%s"', $sisenceFailedUrl)
                );
            } elseif ($oldBuildStatus !== $buildStatus) {
                $this->logger->info(sprintf('Build data with status "%s"', $buildStatus));
            }
            $oldBuildStatus = $buildStatus;
            sleep(1);
        }
    }
}
