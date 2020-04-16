<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Tests;

use GuzzleHttp\Client;
use Keboola\Component\UserException;
use Keboola\Csv\CsvReader;
use Keboola\Csv\CsvWriter;
use Keboola\SiSenseWriter\Api\Api;
use Keboola\SiSenseWriter\Config;
use Keboola\SiSenseWriter\ConfigDefinition;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{

    public function testLogin(): void
    {
        $accessToken = $this->getApiConnection()->login();
        Assert::assertNotEmpty($accessToken);
    }

    public function testInvalidAddress(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Could not resolve host "https://invalidhost.cz:30845"');
        $this->getApiConnection(['host' => 'invalidhost.cz'])->login();
    }

    public function testInvalidLogin(): void
    {
        //phpcs:disable Generic.Files.LineLength
        $expectMessage = 'Client error: `POST %s:%s/api/v1/authentication/login` resulted in a `401 Unauthorized` response:
{"error":{"code":5001,"message":"Invalid domain.","status":401,"httpMessage":"Unauthorized"}}
';
        //phpcs:enable

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(sprintf(
            $expectMessage,
            getenv('SISENSE_HOST'),
            getenv('SISENSE_PORT')
        ));
        $this->getApiConnection(['username' => 'invalid.username'])->login();
    }

    public function testUploadFile(): void
    {
        $csvFile = $this->createCsvExampleFile();
        $api = $this->getApiConnection();
        $api->login();

        $sisenseFile = $api->uploadFile(
            $csvFile->getPath() . '/',
            $csvFile->getFilename()
        );

        Assert::assertStringContainsString('/opt/sisense/storage/datasets/storage/', $sisenseFile);
    }

    public function testCreateDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $datamodel = $api->createDatamodel(uniqid('datamodel-'));
            Assert::assertNotEmpty($datamodel);
        } finally {
            $api->deleteDatamodel($datamodel->getOid());
        }
    }

    public function testDuplicityDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $datamodelName = uniqid('datamodel-');

        $datamodel = $api->createDatamodel($datamodelName);

        try {
            $api->createDatamodel($datamodelName);
            $this->fail('Cannot create duplicity datamodel name failed');
        } catch (UserException $exception) {
            // phpcs:disable Generic.Files.LineLength
            $expectedMessage = 'Client error: `POST %s:%s/api/v2/datamodels` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/general-error","title":"ElasticubeAlreadyExists","status":400,"sub":1011,"detai (truncated...)
';
            // phpcs:enable
            Assert::assertEquals(sprintf(
                $expectedMessage,
                getenv('SISENSE_HOST'),
                getenv('SISENSE_PORT')
            ), $exception->getMessage());
        } finally {
            $api->deleteDatamodel($datamodel->getOid());
        }
    }

    public function testExistsDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $datamodelName = uniqid('datamodel-');

        try {
            $createDatamodel = $api->createDatamodel($datamodelName);
            $existsDatamodel = $api->getDatamodel($datamodelName);

            Assert::assertNotNull($createDatamodel);
            Assert::assertNotNull($existsDatamodel);
            Assert::assertEquals($createDatamodel, $existsDatamodel);
        } finally {
            $api->deleteDatamodel($createDatamodel->getOid());
        }
    }

    public function testInvalidExistsDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $invalidDatamodel = $api->getDatamodel('invalidDatamodel');
        Assert::assertNull($invalidDatamodel);
    }

    public function testCreateDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $datamodel = $api->createDatamodel(uniqid('datamodel-'));

            $csvFile = $this->createCsvExampleFile();
            $sisenseFile = $api->uploadFile(
                $csvFile->getPath() . '/',
                $csvFile->getFilename()
            );

            $dataset = $api->createDataset(
                $datamodel->getOid(),
                uniqid('dataset-'),
                $sisenseFile,
                $csvFile->getFilename()
            );

            Assert::assertNotEmpty($dataset);
        } finally {
            $api->deleteDataset($datamodel->getOid(), $dataset->getOid());
            $api->deleteDatamodel($datamodel->getOid());
        }
    }

    public function testDuplicityDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();
        $datamodel = $api->createDatamodel(uniqid('datamodel-'));

        try {
            $csvFile = $this->createCsvExampleFile();
            $sisenseFile = $api->uploadFile(
                $csvFile->getPath() . '/',
                $csvFile->getFilename()
            );

            $datasetName = uniqid('dataset-');
            $dataset = $api->createDataset($datamodel->getOid(), $datasetName, $sisenseFile, $csvFile->getFilename());
            $api->createDataset($datamodel->getOid(), $datasetName, $sisenseFile, $csvFile->getFilename());
            $this->fail('Cannot create duplicity dataset name failed');
        } catch (UserException $exception) {
            //phpcs:disable Generic.Files.LineLength
            $expectedMessage = 'Client error: `POST %s:%s/api/v2/datamodels/%s/schema/datasets` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/validation-error","title":"ValidationError","status":400,"sub":1002,"detail":"V (truncated...)
';
            //phpcs:enable
            Assert::assertEquals(sprintf(
                $expectedMessage,
                getenv('SISENSE_HOST'),
                getenv('SISENSE_PORT'),
                $datamodel->getOid()
            ), $exception->getMessage());
        } finally {
            if (isset($dataset)) {
                $api->deleteDataset($datamodel->getOid(), $dataset->getOid());
            }
            $api->deleteDatamodel($datamodel->getOid());
        }
    }

    public function testExistsDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $csvFile = $this->createCsvExampleFile();
            $sisenseFile = $api->uploadFile(
                $csvFile->getPath() . '/',
                $csvFile->getFilename()
            );

            $datamodel = $api->createDatamodel(uniqid('datamodel-'));

            $datasetName = uniqid('dataset-');
            $createDataset = $api->createDataset(
                $datamodel->getOid(),
                $datasetName,
                $sisenseFile,
                $csvFile->getFilename()
            );
            $existsDataset = $api->getDataset($datamodel->getOid(), $datasetName);

            Assert::assertNotNull($createDataset);
            Assert::assertNotNull($existsDataset);
            Assert::assertEquals($createDataset, $existsDataset);
        } finally {
            $api->deleteDataset($datamodel->getOid(), $createDataset->getOid());
            $api->deleteDatamodel($datamodel->getOid());
        }
    }

    public function testInvalidExistsDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $datamodel = $api->createDatamodel(uniqid('datamodel-'));
            $invalidDataset = $api->getDataset($datamodel->getOid(), 'invalidDataset');
            Assert::assertNull($invalidDataset);
        } finally {
            $api->deleteDatamodel($datamodel->getOid());
        }
    }

    public function testCreateDatasetWithInvalidDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $csvFile = $this->createCsvExampleFile();
        $sisenseFile = $api->uploadFile(
            $csvFile->getPath() . '/',
            $csvFile->getFilename()
        );

        $this->expectException(UserException::class);
        //phpcs:disable Generic.Files.LineLength
        $expectedMessage = 'Client error: `POST %s:%s/api/v2/datamodels/invalid-datamodel/schema/datasets` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/general-error","title":"EcmApiError","status":400,"sub":1012,"detail":"Variable (truncated...)
';
        //phpcs:enable
        $this->expectExceptionMessage(sprintf(
            $expectedMessage,
            getenv('SISENSE_HOST'),
            getenv('SISENSE_PORT')
        ));
        $api->createDataset('invalid-datamodel', uniqid('dataset-'), $sisenseFile, $csvFile->getFilename());
    }

    public function testCreateTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $data = $this->createDatamodelAndDataset($api);

            $table = $api->createTable(
                $data['datamodel']->getOid(),
                $data['dataset']->getOid(),
                uniqid('table'),
                $data['columns']
            );

            Assert::assertNotEmpty($table->getOid());
            Assert::assertNotEmpty($table->getColumns());
            Assert::assertCount(count($data['columns']), $table->getColumns());
        } finally {
            $api->deleteDataset($data['datamodel']->getOid(), $data['dataset']->getOid());
            $api->deleteDatamodel($data['datamodel']->getOid());
        }
    }

    public function testUpdateTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $data = $this->createDatamodelAndDataset($api);

            $createTable = $api->createTable(
                $data['datamodel']->getOid(),
                $data['dataset']->getOid(),
                uniqid('table'),
                $data['columns']
            );

            $updateTable = $api->updateTable(
                $data['datamodel']->getOid(),
                $data['dataset']->getOid(),
                $createTable->getOid(),
                $data['columns']
            );

            Assert::assertNotEmpty($updateTable->getOid());
            Assert::assertNotEmpty($updateTable->getColumns());
            Assert::assertCount(count($data['columns']), $updateTable->getColumns());
        } finally {
            $api->deleteDataset($data['datamodel']->getOid(), $data['dataset']->getOid());
            $api->deleteDatamodel($data['datamodel']->getOid());
        }
    }

    public function testExistsTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $data = $this->createDatamodelAndDataset($api);

            $tableName = uniqid('table');

            $createTable = $api->createTable(
                $data['datamodel']->getOid(),
                $data['dataset']->getOid(),
                $tableName,
                $data['columns']
            );

            $existsTable = $api->getTable(
                $data['datamodel']->getOid(),
                $data['dataset']->getOid(),
                $tableName
            );

            Assert::assertNotNull($existsTable);
            Assert::assertEquals($createTable, $existsTable);
        } finally {
            $api->deleteDataset($data['datamodel']->getOid(), $data['dataset']->getOid());
            $api->deleteDatamodel($data['datamodel']->getOid());
        }
    }

    public function testFindTableByName(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $data = $this->createDatamodelAndDataset($api);

            $tableName = uniqid('table-');
            $createTable = $api->createTable(
                $data['datamodel']->getOid(),
                $data['dataset']->getOid(),
                $tableName,
                $data['columns']
            );

            $findTable = $api->getTableByName($data['datamodel']->getOid(), $tableName);

            Assert::assertNotNull($findTable);
            Assert::assertNotNull($findTable['table']);
            Assert::assertEquals($createTable, $findTable['table']);
        } finally {
            $api->deleteDataset($data['datamodel']->getOid(), $data['dataset']->getOid());
            $api->deleteDatamodel($data['datamodel']->getOid());
        }
    }

    public function testFindUnexistsTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $data = $this->createDatamodelAndDataset($api);

            try {
                $api->getTableByName($data['datamodel']->getOid(), 'unexists-table');
                $this->fail('Cannot find table name failed');
            } catch (UserException $exception) {
                Assert::assertEquals('Cannot find table "unexists-table"', $exception->getMessage());
            }
        } finally {
            $api->deleteDataset($data['datamodel']->getOid(), $data['dataset']->getOid());
            $api->deleteDatamodel($data['datamodel']->getOid());
        }
    }

    public function testInvalidExistsTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $data = $this->createDatamodelAndDataset($api);

            $tableName = uniqid('table');

            $existsTable = $api->getTable(
                $data['datamodel']->getOid(),
                $data['dataset']->getOid(),
                $tableName
            );

            Assert::assertNull($existsTable);
        } finally {
            $api->deleteDataset($data['datamodel']->getOid(), $data['dataset']->getOid());
            $api->deleteDatamodel($data['datamodel']->getOid());
        }
    }

    public function testCreateTableWithInvalidDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $data = $this->createDatamodelAndDataset($api);
        $tableName = uniqid('table');

        try {
            $api->createTable(
                'invalid-datamodel',
                $data['dataset']->getOid(),
                $tableName,
                $data['columns']
            );
        } catch (UserException $exception) {
            //phpcs:disable Generic.Files.LineLength
            $expectedMessage = 'Client error: `POST %s:%s/api/v2/datamodels/invalid-datamodel/schema/datasets/%s/tables` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/general-error","title":"EcmApiError","status":400,"sub":1012,"detail":"Variable (truncated...)
';
            //phpcs:enable
            Assert::assertEquals(sprintf(
                $expectedMessage,
                getenv('SISENSE_HOST'),
                getenv('SISENSE_PORT'),
                $data['dataset']->getOid()
            ), $exception->getMessage());
        } finally {
            $api->deleteDataset($data['datamodel']->getOid(), $data['dataset']->getOid());
            $api->deleteDatamodel($data['datamodel']->getOid());
        }
    }

    public function testCreateTableWithInvalidDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $data = $this->createDatamodelAndDataset($api);

        $tableName = uniqid('table');

        try {
            $api->createTable(
                $data['datamodel']->getOid(),
                'invalid-dataset',
                $tableName,
                $data['columns']
            );
        } catch (UserException $exception) {
            //phpcs:disable Generic.Files.LineLength
            $expectedMessage = 'Client error: `POST %s:%s/api/v2/datamodels/%s/schema/datasets/invalid-dataset/tables` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/general-error","title":"EcmApiError","status":400,"sub":1012,"detail":"Variable (truncated...)
';
            //phpcs:enable
            Assert::assertEquals(sprintf(
                $expectedMessage,
                getenv('SISENSE_HOST'),
                getenv('SISENSE_PORT'),
                $data['datamodel']->getOid()
            ), $exception->getMessage());
        } finally {
            $api->deleteDataset($data['datamodel']->getOid(), $data['dataset']->getOid());
            $api->deleteDatamodel($data['datamodel']->getOid());
        }
    }

    public function testCreateRelationship(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        try {
            $firstData = $this->createDatamodelAndDataset($api);
            $firstTable = $api->createTable(
                $firstData['datamodel']->getOid(),
                $firstData['dataset']->getOid(),
                uniqid('table-'),
                $firstData['columns']
            );
            $secondData = $this->createDatamodelAndDataset($api, $firstData['datamodel']->getTitle());
            $secondTable = $api->createTable(
                $secondData['datamodel']->getOid(),
                $secondData['dataset']->getOid(),
                uniqid('table-'),
                $secondData['columns']
            );

            $sourceColumn = [
                'dataset' => $firstData['dataset']->getOid(),
                'table' => $firstTable->getOid(),
                'column' => $firstTable->getColumns()[0]->getOid(),
            ];
            $targetColumn = [
                'dataset' => $secondData['dataset']->getOid(),
                'table' => $secondTable->getOid(),
                'column' => $secondTable->getColumns()[0]->getOid(),
            ];

            $relationship = $api->createRelationship(
                $firstData['datamodel']->getOid(),
                $sourceColumn,
                $targetColumn,
            );

            Assert::assertNotEmpty($relationship);
            Assert::assertEquals([$sourceColumn, $targetColumn], $relationship['columns']);
        } finally {
            $api->deleteDataset($secondData['datamodel']->getOid(), $secondData['dataset']->getOid());
            $api->deleteDataset($firstData['datamodel']->getOid(), $firstData['dataset']->getOid());
            $api->deleteDatamodel($firstData['datamodel']->getOid());
        }
    }

    private function createDatamodelAndDataset(Api $api, ?string $datamodelName = null): array
    {
        if (is_null($datamodelName)) {
            $datamodelName = uniqid('datamodel-');
        }
        $csvFile = $this->createCsvExampleFile();
        $sisenseFile = $api->uploadFile(
            $csvFile->getPath() . '/',
            $csvFile->getFilename()
        );
        $csvReader = new CsvReader($csvFile->getPathname());
        $columns = array_map(function ($item) {
            return [
                'id' => $item,
                'name' => $item,
                'type' => 'varchar',
                'size' => '255',
            ];
        }, $csvReader->getHeader());

        $datamodel = $api->getDatamodel($datamodelName);
        if (is_null($datamodel)) {
            $datamodel = $api->createDatamodel($datamodelName);
        }
        $dataset = $api->createDataset($datamodel->getOid(), uniqid('dataset-'), $sisenseFile, $csvFile->getFilename());

        return [
            'datamodel' => $datamodel,
            'dataset' => $dataset,
            'columns' => $columns,
        ];
    }

    private function getApiConnection(array $configParameters = []): Api
    {
        $configArray = [
            'parameters' => array_merge([
                'host' => getenv('SISENSE_HOST'),
                'port' => getenv('SISENSE_PORT'),
                'username' => getenv('SISENSE_USERNAME'),
                '#password' => getenv('SISENSE_PASSWORD'),
                'datamodelName' => getenv('SISENSE_DATAMODEL'),
                'tableId' => 'sales',
                'columns' => [
                    [
                        'id' => 'usergender',
                        'name' => 'usergender',
                        'type' => 'varchar',
                        'size' => '255',
                    ],
                ],
            ], $configParameters),
        ];
        $config = new Config(
            $configArray,
            new ConfigDefinition()
        );

        return new Api(
            $config,
            new Client()
        );
    }

    private function createCsvExampleFile(): \SplFileInfo
    {
        $csvFilePath = sprintf('%s/csv-sample-data-%s.csv', sys_get_temp_dir(), uniqid());
        $csvFile = new CsvWriter($csvFilePath);
        $randomColumns = mt_rand(3, 10);
        $randomRows = mt_rand(5, 20);
        $header = array_map(function ($item) {
            return 'col_' . $item;
        }, range(1, $randomColumns));

        $csvFile->writeRow($header);
        for ($row = 1; $row <= $randomRows; $row++) {
            $rowData = [];
            for ($column = 1; $column <= $randomColumns; $column++) {
                $rowData[] = $this->generateRandomString(mt_rand(5, 10));
            }
            $csvFile->writeRow($rowData);
        }
        return new \SplFileInfo($csvFilePath);
    }

    private function generateRandomString(int $stringLength): string
    {
        $randomString = '';
        for ($i = 1; $i <= $stringLength; $i++) {
            $randomString .= chr(mt_rand(97, 122));
        }
        return $randomString;
    }
}
