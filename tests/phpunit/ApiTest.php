<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter\Tests;

use GuzzleHttp\Client;
use Keboola\Component\UserException;
use Keboola\Csv\CsvReader;
use Keboola\Csv\CsvWriter;
use Keboola\SiSenseWriter\Api;
use Keboola\SiSenseWriter\Config;
use Keboola\SiSenseWriter\ConfigDefinition;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{

    public function testLogin(): void
    {
        $accessToken = $this->getApiConnection()->login();
        $this->assertNotEmpty($accessToken);
    }

    public function testInvalidAddress(): void
    {
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Could not resolve host "http://invalidhost.cz:30845"');
        $this->getApiConnection(['host' => 'invalidhost.cz'])->login();
    }

    public function testInvalidLogin(): void
    {
        //phpcs:disable Generic.Files.LineLength
        $expectMessage = 'Client error: `POST http://%s:%s/api/v1/authentication/login` resulted in a `401 Unauthorized` response:
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

        $this->assertStringContainsString('/opt/sisense/storage/datasets/storage/', $sisenseFile);
    }

    public function testCreateDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $datamodel = $api->createDatamodel(uniqid('datamodel-'));

        $this->assertNotEmpty($datamodel);
        $this->assertArrayHasKey('oid', $datamodel);

        $api->deleteDatamodel($datamodel['oid']);
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
            $expectedMessage = 'Client error: `POST http://%s:%s/api/v2/datamodels` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/general-error","title":"ElasticubeAlreadyExists","status":400,"sub":1011,"detai (truncated...)
';
            // phpcs:enable
            $this->assertEquals(sprintf(
                $expectedMessage,
                getenv('SISENSE_HOST'),
                getenv('SISENSE_PORT')
            ), $exception->getMessage());
        }
        $api->deleteDatamodel($datamodel['oid']);
    }

    public function testExistsDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $datamodelName = uniqid('datamodel-');

        $createDatamodel = $api->createDatamodel($datamodelName);

        $existsDatamodel = $api->checkDatamodelExists($datamodelName);

        unset($createDatamodel['lastSuccessfulBuildTime']);
        $this->assertNotNull($createDatamodel);
        $this->assertNotNull($existsDatamodel);
        $this->assertEquals($createDatamodel, $existsDatamodel);

        $api->deleteDatamodel($createDatamodel['oid']);
    }

    public function testInvalidExistsDatamodel(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $invalidDatamodel = $api->checkDatamodelExists('invalidDatamodel');
        $this->assertNull($invalidDatamodel);
    }

    public function testCreateDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $datamodel = $api->createDatamodel(uniqid('datamodel-'));

        $csvFile = $this->createCsvExampleFile();
        $sisenseFile = $api->uploadFile(
            $csvFile->getPath() . '/',
            $csvFile->getFilename()
        );

        $dataset = $api->createDataset($datamodel['oid'], uniqid('dataset-'), $sisenseFile, $csvFile->getFilename());

        $this->assertNotEmpty($dataset);
        $this->assertArrayHasKey('oid', $dataset);

        $api->deleteDataset($datamodel['oid'], $dataset['oid']);
        $api->deleteDatamodel($datamodel['oid']);
    }

    public function testDuplicityDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $datamodel = $api->createDatamodel(uniqid('datamodel-'));

        $csvFile = $this->createCsvExampleFile();
        $sisenseFile = $api->uploadFile(
            $csvFile->getPath() . '/',
            $csvFile->getFilename()
        );

        $datasetName = uniqid('dataset-');
        $dataset = $api->createDataset($datamodel['oid'], $datasetName, $sisenseFile, $csvFile->getFilename());

        try {
            $api->createDataset($datamodel['oid'], $datasetName, $sisenseFile, $csvFile->getFilename());
            $this->fail('Cannot create duplicity dataset name failed');
        } catch (UserException $exception) {
            //phpcs:disable Generic.Files.LineLength
            $expectedMessage = 'Client error: `POST http://%s:%s/api/v2/datamodels/%s/schema/datasets` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/validation-error","title":"ValidationError","status":400,"sub":1002,"detail":"V (truncated...)
';
            //phpcs:enable
            $this->assertEquals(sprintf(
                $expectedMessage,
                getenv('SISENSE_HOST'),
                getenv('SISENSE_PORT'),
                $datamodel['oid']
            ), $exception->getMessage());
        }

        $api->deleteDataset($datamodel['oid'], $dataset['oid']);
        $api->deleteDatamodel($datamodel['oid']);
    }

    public function testExistsDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $csvFile = $this->createCsvExampleFile();
        $sisenseFile = $api->uploadFile(
            $csvFile->getPath() . '/',
            $csvFile->getFilename()
        );

        $datamodel = $api->createDatamodel(uniqid('datamodel-'));

        $datasetName = uniqid('dataset-');
        $createDataset = $api->createDataset($datamodel['oid'], $datasetName, $sisenseFile, $csvFile->getFilename());
        $existsDataset = $api->checkDatasetExists($datamodel['oid'], $datasetName);

        $this->assertNotNull($createDataset);
        $this->assertNotNull($existsDataset);
        $this->assertEquals($createDataset, $existsDataset);

        $api->deleteDataset($datamodel['oid'], $createDataset['oid']);
        $api->deleteDatamodel($datamodel['oid']);
    }

    public function testInvalidExistsDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $datamodel = $api->createDatamodel(uniqid('datamodel-'));
        $invalidDataset = $api->checkDatasetExists($datamodel['oid'], 'invalidDataset');
        $this->assertNull($invalidDataset);

        $api->deleteDatamodel($datamodel['oid']);
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
        $expectedMessage = 'Client error: `POST http://%s:%s/api/v2/datamodels/invalid-datamodel/schema/datasets` resulted in a `400 Bad Request` response:
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

        $data = $this->createDatamodelAndDataset($api);

        $table = $api->createTable(
            $data['datamodel']['oid'],
            $data['dataset']['oid'],
            uniqid('table'),
            $data['columns']
        );

        $this->assertArrayHasKey('oid', $table);
        $this->assertArrayHasKey('columns', $table);
        $this->assertCount(count($data['columns']), $table['columns']);

        $api->deleteDataset($data['datamodel']['oid'], $data['dataset']['oid']);
        $api->deleteDatamodel($data['datamodel']['oid']);
    }

    public function testUpdateTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $data = $this->createDatamodelAndDataset($api);

        $createTable = $api->createTable(
            $data['datamodel']['oid'],
            $data['dataset']['oid'],
            uniqid('table'),
            $data['columns']
        );

        $updateTable = $api->updateTable(
            $data['datamodel']['oid'],
            $data['dataset']['oid'],
            $createTable['oid'],
            $data['columns']
        );

        $this->assertArrayHasKey('oid', $updateTable);
        $this->assertArrayHasKey('columns', $updateTable);
        $this->assertCount(count($data['columns']), $updateTable['columns']);

        $api->deleteDataset($data['datamodel']['oid'], $data['dataset']['oid']);
        $api->deleteDatamodel($data['datamodel']['oid']);
    }

    public function testExistsTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $data = $this->createDatamodelAndDataset($api);

        $tableName = uniqid('table');

        $createTable = $api->createTable(
            $data['datamodel']['oid'],
            $data['dataset']['oid'],
            $tableName,
            $data['columns']
        );

        $existsTable = $api->checkTableExists(
            $data['datamodel']['oid'],
            $data['dataset']['oid'],
            $tableName
        );

        $this->assertNotNull($existsTable);
        $this->assertEquals($createTable, $existsTable);

        $api->deleteDataset($data['datamodel']['oid'], $data['dataset']['oid']);
        $api->deleteDatamodel($data['datamodel']['oid']);
    }

    public function testFindTableByName(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $data = $this->createDatamodelAndDataset($api);

        $tableName = uniqid('table');
        $createTable = $api->createTable(
            $data['datamodel']['oid'],
            $data['dataset']['oid'],
            $tableName,
            $data['columns']
        );

        $findTable = $api->getTableByName($data['datamodel']['oid'], $tableName);

        $this->assertNotNull($findTable);
        $this->assertNotNull($findTable['table']);
        $this->assertEquals($createTable, $findTable['table']);

        $api->deleteDataset($data['datamodel']['oid'], $data['dataset']['oid']);
        $api->deleteDatamodel($data['datamodel']['oid']);
    }

    public function testFindUnexistsTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $data = $this->createDatamodelAndDataset($api);

        try {
            $api->getTableByName($data['datamodel']['oid'], 'unexists-table');
            $this->fail('Cannot find table name failed');
        } catch (UserException $exception) {
            $this->assertEquals('Cannot find table "unexists-table"', $exception->getMessage());
        }
        $api->deleteDataset($data['datamodel']['oid'], $data['dataset']['oid']);
        $api->deleteDatamodel($data['datamodel']['oid']);
    }

    public function testInvalidExistsTable(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $data = $this->createDatamodelAndDataset($api);

        $tableName = uniqid('table');

        $existsTable = $api->checkTableExists(
            $data['datamodel']['oid'],
            $data['dataset']['oid'],
            $tableName
        );

        $this->assertNull($existsTable);

        $api->deleteDataset($data['datamodel']['oid'], $data['dataset']['oid']);
        $api->deleteDatamodel($data['datamodel']['oid']);
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
                $data['dataset']['oid'],
                $tableName,
                $data['columns']
            );
        } catch (UserException $exception) {
            //phpcs:disable Generic.Files.LineLength
            $expectedMessage = 'Client error: `POST http://%s:%s/api/v2/datamodels/invalid-datamodel/schema/datasets/%s/tables` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/general-error","title":"EcmApiError","status":400,"sub":1012,"detail":"Variable (truncated...)
';
            //phpcs:enable
            $this->assertEquals(sprintf(
                $expectedMessage,
                getenv('SISENSE_HOST'),
                getenv('SISENSE_PORT'),
                $data['dataset']['oid']
            ), $exception->getMessage());
        }

        $api->deleteDataset($data['datamodel']['oid'], $data['dataset']['oid']);
        $api->deleteDatamodel($data['datamodel']['oid']);
    }

    public function testCreateTableWithInvalidDataset(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $data = $this->createDatamodelAndDataset($api);

        $tableName = uniqid('table');

        try {
            $api->createTable(
                $data['datamodel']['oid'],
                'invalid-dataset',
                $tableName,
                $data['columns']
            );
        } catch (UserException $exception) {
            //phpcs:disable Generic.Files.LineLength
            $expectedMessage = 'Client error: `POST http://%s:%s/api/v2/datamodels/%s/schema/datasets/invalid-dataset/tables` resulted in a `400 Bad Request` response:
{"type":"https://errors.sisense.dev/http/general-error","title":"EcmApiError","status":400,"sub":1012,"detail":"Variable (truncated...)
';
            //phpcs:enable
            $this->assertEquals(sprintf(
                $expectedMessage,
                getenv('SISENSE_HOST'),
                getenv('SISENSE_PORT'),
                $data['datamodel']['oid']
            ), $exception->getMessage());
        }

        $api->deleteDataset($data['datamodel']['oid'], $data['dataset']['oid']);
        $api->deleteDatamodel($data['datamodel']['oid']);
    }

    public function testCreateRelationship(): void
    {
        $api = $this->getApiConnection();
        $api->login();

        $firstData = $this->createDatamodelAndDataset($api);
        $firstTable = $api->createTable(
            $firstData['datamodel']['oid'],
            $firstData['dataset']['oid'],
            uniqid('table-'),
            $firstData['columns']
        );
        $secondData = $this->createDatamodelAndDataset($api, $firstData['datamodel']['title']);
        $secondTable = $api->createTable(
            $secondData['datamodel']['oid'],
            $secondData['dataset']['oid'],
            uniqid('table-'),
            $secondData['columns']
        );

        $sourceColumn = [
            'dataset' => $firstData['dataset']['oid'],
            'table' => $firstTable['oid'],
            'column' => $firstTable['columns'][0]['oid'],
        ];
        $targetColumn = [
            'dataset' => $secondData['dataset']['oid'],
            'table' => $secondTable['oid'],
            'column' => $secondTable['columns'][0]['oid'],
        ];

        $relationship = $api->createRelationship(
            $firstData['datamodel']['oid'],
            $sourceColumn,
            $targetColumn,
        );

        $this->assertNotEmpty($relationship);
        $this->assertEquals([$sourceColumn, $targetColumn], $relationship['columns']);

        $api->deleteDataset($secondData['datamodel']['oid'], $secondData['dataset']['oid']);
        $api->deleteDataset($firstData['datamodel']['oid'], $firstData['dataset']['oid']);
        $api->deleteDatamodel($firstData['datamodel']['oid']);
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

        $datamodel = $api->checkDatamodelExists($datamodelName);
        if (is_null($datamodel)) {
            $datamodel = $api->createDatamodel($datamodelName);
        }
        $dataset = $api->createDataset($datamodel['oid'], uniqid('dataset-'), $sisenseFile, $csvFile->getFilename());

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