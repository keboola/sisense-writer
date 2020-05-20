<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter;

use Keboola\Component\UserException;
use Keboola\SiSenseWriter\Api\Api;
use Keboola\SiSenseWriter\Api\Model\Datamodel;
use Keboola\SiSenseWriter\Api\Model\Dataset;
use Keboola\SiSenseWriter\Api\Model\Table;
use Keboola\SiSenseWriter\Api\Model\TableColumn;
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
    }

    public function testConnection(): array
    {
        try {
            $this->api->login();
        } catch (\Throwable $exception) {
            throw new UserException(sprintf('Connection failed "%s"', $exception->getMessage()));
        }
        return [
            'status' => 'success',
        ];
    }

    public function execute(): void
    {
        $this->api->login();

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

    private function createAndGetDatamodel(): Datamodel
    {
        $datamodel = $this->api->getDatamodel($this->config->getDatamodelName());
        if (is_null($datamodel)) {
            $this->logger->info(sprintf('Creating new datamodel "%s"', $this->config->getDatamodelName()));
            $datamodel = $this->api->createDatamodel($this->config->getDatamodelName());
        } else {
            $this->logger->info(sprintf('Update existing datamodel "%s"', $datamodel->getTitle()));
        }
        return $datamodel;
    }

    private function createAndGetDataset(Datamodel $datamodel, string $csvFile): Dataset
    {
        $dataset = $this->api->getDataset(
            $datamodel->getOid(),
            $this->api->getDatasetName(
                $this->config->getDatamodelName(),
                $this->config->getTableName()
            )
        );
        if (is_null($dataset)) {
            $this->logger->info(sprintf('Creating new dataset "%s"', $this->config->getTableName()));
            $dataset = $this->api->createDataset(
                $datamodel->getOid(),
                $this->api->getDatasetName(
                    $this->config->getDatamodelName(),
                    $this->config->getTableName()
                ),
                $csvFile,
                sprintf('%s.csv', $this->config->getTableId())
            );
        } else {
            $this->logger->info(sprintf('Update existing dataset "%s"', $dataset->getName()));
            $dataset = $this->api->updateDataset(
                $datamodel->getOid(),
                $dataset->getOid(),
                $csvFile,
                sprintf('%s.csv', $this->config->getTableId())
            );
        }
        return $dataset;
    }

    private function createAndGetTable(Datamodel $datamodel, Dataset $dataset): Table
    {
        $table = $this->api->getTable($datamodel->getOid(), $dataset->getOid(), $this->config->getTableName());
        if (is_null($table)) {
            $this->logger->info(sprintf('Creating new table "%s"', $this->config->getTableName()));
            $table = $this->api->createTable(
                $datamodel->getOid(),
                $dataset->getOid(),
                $this->config->getTableName(),
                $this->config->getColumns()
            );
        } else {
            $this->logger->info(sprintf('Update existing table "%s"', $table->getName()));
            $table = $this->api->updateTable(
                $datamodel->getOid(),
                $dataset->getOid(),
                $table->getOid(),
                $this->config->getColumns()
            );
        }
        return $table;
    }

    private function createRelationships(Datamodel $datamodel, Dataset $sourceDataset, Table $sourceTable): void
    {
        foreach ($this->config->getRelationships() as $relationship) {
            $destinationData = $this->api->getTableByName($datamodel->getOid(), $relationship['target']['table']);
            /** @var Dataset $destinationDataset */
            $destinationDataset = $destinationData['dataset'];
            /** @var Table $destinationTable */
            $destinationTable = $destinationData['table'];

            $sourceColumn = array_values(
                array_filter($sourceTable->getColumns(), function (TableColumn $column) use ($relationship) {
                    return $relationship['column'] === $column->getName();
                })
            );

            $destinationColumn = array_values(
                array_filter($destinationTable->getColumns(), function (TableColumn $column) use ($relationship) {
                    return $relationship['target']['column'] === $column->getName();
                })
            );
            $this->logger->info('Creating relationship');
            $this->api->createRelationship(
                $datamodel->getOid(),
                [
                    'dataset' => $sourceDataset->getOid(),
                    'table' => $sourceTable->getOid(),
                    'column' => $sourceColumn[0]->getOid(),
                ],
                [
                    'dataset' => $destinationDataset->getOid(),
                    'table' => $destinationTable->getOid(),
                    'column' => $destinationColumn[0]->getOid(),
                ]
            );
        }
    }

    private function buildData(Datamodel $datamodel): void
    {
        $this->logger->info('Start build data');
        $buildId = $this->api->build($datamodel->getOid(), 'full');
        $oldBuildStatus = $buildStatus = null;
        while (in_array($buildStatus, [null, 'waiting', 'building'])) {
            $buildStatus = $this->api->getBuildStatus($buildId);
            if ($buildStatus === 'failed') {
                $this->logger->alert(sprintf('Build data with status "%s"', $buildStatus));
                $sisenceFailedUrl = $this->config->getUrlAddress() . '/app/data/cubes/' . $datamodel->getOid();
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
