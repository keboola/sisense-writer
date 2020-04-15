<?php

declare(strict_types=1);

namespace Keboola\SiSenseWriter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\MultipartStream;
use Keboola\Component\UserException;

class Api
{
    private Config $config;

    private Client $client;

    private string $accessToken;

    public function __construct(Config $config, Client $client)
    {
        $this->config = $config;
        $this->client = $client;
    }

    public function login(): string
    {
        try {
            $response = $this->client->post(
                sprintf('%s/api/v1/authentication/login', $this->config->getUrlAddress()),
                [
                    'form_params' => [
                        'username' => $this->config->getUsername(),
                        'password' => $this->config->getPassword(),
                    ],
                ]
            );
        } catch (ClientException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        } catch (ConnectException $exception) {
            $exceptionMessage = $exception->getMessage();
            if (strstr($exception->getMessage(), 'cURL error 6: Could not resolve host')) {
                $exceptionMessage = sprintf('Could not resolve host "%s"', $this->config->getUrlAddress());
            }
            throw new UserException($exceptionMessage, $exception->getCode(), $exception);
        }

        $responseJson = json_decode($response->getBody()->getContents(), true);
        $this->accessToken = $responseJson['access_token'];
        return $this->accessToken;
    }

    public function uploadFile(string $dataDir, string $filename): string
    {
        try {
            $streamFile = new MultipartStream(
                [
                    [
                        'name' => 'file',
                        'filename' => $filename,
                        'contents' => file_get_contents($dataDir . $filename),
                    ],
                ]
            );
            $response = $this->client->request(
                'post',
                sprintf('%s/storage/fs/upload', $this->config->getUrlAddress()),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'x-upload-token' => $this->validateFile($dataDir, $filename),
                        'Content-Type' => 'multipart/form-data; boundary=' . $streamFile->getBoundary(),
                    ],
                    'body' => $streamFile,
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $responseJson = json_decode($response->getBody()->getContents(), true);
        return $responseJson[0]['storageInfo']['path'];
    }

    public function checkDatamodelExists(string $datamodelName): ?array
    {
        try {
            $response = $this->client->get(
                sprintf('%s/api/v2/datamodels/schema', $this->config->getUrlAddress()),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'query' => [
                        'title' => $datamodelName,
                    ],
                ]
            );
        } catch (ServerException $exception) {
            return null;
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function createDatamodel(string $datamodelName): array
    {
        try {
            $response = $this->client->post(
                sprintf('%s/api/v2/datamodels', $this->config->getUrlAddress()),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'json' => [
                        'title' => $datamodelName,
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function deleteDatamodel(string $datamodelId): void
    {
        try {
            $this->client->delete(
                sprintf('%s/api/v2/datamodels/%s', $this->config->getUrlAddress(), $datamodelId),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function checkDatasetExists(string $datamodelId, string $datasetName): ?array
    {
        try {
            $response = $this->client->get(
                sprintf(
                    '%s/api/v2/datamodels/%s/schema/datasets',
                    $this->config->getUrlAddress(),
                    $datamodelId
                ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                ]
            );
        } catch (ServerException $exception) {
            return null;
        }

        $responseJson = json_decode($response->getBody()->getContents(), true);
        $filteredResponse = array_values(array_filter($responseJson, function ($item) use ($datasetName) {
            return $item['name'] === $datasetName;
        }));
        if (count($filteredResponse) === 0) {
            return null;
        }

        return $filteredResponse[0];
    }

    public function createDataset(
        string $datamodelId,
        string $datasetName,
        string $csvFilePath,
        string $filename
    ): array {
        $connection = [
            'provider' => 'CSV',
            'schema' => $csvFilePath,
            'parameters' => [
                'ApiVersion' => 2,
                'files' => [
                    $csvFilePath,
                ],
                'unionAll' => true,
            ],
            'uiParams' => [],
            'globalTableConfigOptions' => [],
            'fileName' => $filename,
        ];

        try {
            $response = $this->client->post(
                sprintf(
                    '%s/api/v2/datamodels/%s/schema/datasets',
                    $this->config->getUrlAddress(),
                    $datamodelId
                ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'json' => [
                        'name' => $datasetName,
                        'type' => 'extract',
                        'connection' => $connection,
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function updateDataset(
        string $datamodelId,
        string $datasetId,
        string $csvFilePath,
        string $filename
    ): array {
        $connection = [
            'provider' => 'CSV',
            'schema' => $csvFilePath,
            'parameters' => [
                'ApiVersion' => 2,
                'files' => [
                    $csvFilePath,
                ],
                'unionAll' => true,
            ],
            'uiParams' => [],
            'globalTableConfigOptions' => [],
            'fileName' => $filename,
        ];

        try {
            $response = $this->client->patch(
                sprintf(
                    '%s/api/v2/datamodels/%s/schema/datasets/%s',
                    $this->config->getUrlAddress(),
                    $datamodelId,
                    $datasetId
                ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'json' => [
                        'type' => 'extract',
                        'connection' => $connection,
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function deleteDataset(string $datamodelId, string $datasetId): void
    {
        try {
            $this->client->delete(
                sprintf(
                    '%s/api/v2/datamodels/%s/schema/datasets/%s',
                    $this->config->getUrlAddress(),
                    $datamodelId,
                    $datasetId
                ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function checkTableExists(string $datamodelId, string $datasetId, string $tableId): ?array
    {
        try {
            $response = $this->client->get(
                sprintf(
                    '%s/api/v2/datamodels/%s/schema/datasets',
                    $this->config->getUrlAddress(),
                    $datamodelId
                ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                ]
            );
        } catch (ServerException $exception) {
            return null;
        }

        $responseJson = json_decode($response->getBody()->getContents(), true);
        if (count($responseJson) === 0) {
            return null;
        }
        $filteredDatasets = array_values(array_filter($responseJson, function ($dataset) use ($datasetId) {
            return $dataset['oid'] === $datasetId;
        }));

        $tablesInDataset = $filteredDatasets[0]['schema']['tables'];
        $filteredTables = array_values(array_filter($tablesInDataset, function ($table) use ($tableId) {
            return $table['id'] === $tableId;
        }));

        if (count($filteredTables) === 0) {
            return null;
        }

        return $filteredTables[0];
    }

    public function getTableByName(string $datamodelId, string $tableName): array
    {
        try {
            $response = $this->client->get(
                sprintf('%s/api/v2/datamodels/%s/schema/datasets', $this->config->getUrlAddress(), $datamodelId),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'query' => [
                        'fields' => 'oid, schema',
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        foreach (json_decode($response->getBody()->getContents(), true) as $dataset) {
            $filteredTables = array_values(
                array_filter($dataset['schema']['tables'], function ($table) use ($tableName) {
                    return $table['id'] === $tableName;
                })
            );
            if ($filteredTables) {
                return [
                    'dataset_oid' => $dataset['oid'],
                    'table' => $filteredTables[0],
                ];
            }
        }

        throw new UserException(sprintf('Cannot find table "%s"', $tableName));
    }

    public function createTable(string $datamodelId, string $datasetId, string $tableId, array $columns): array
    {
        try {
            $response = $this->client->post(
                sprintf(
                    '%s/api/v2/datamodels/%s/schema/datasets/%s/tables',
                    $this->config->getUrlAddress(),
                    $datamodelId,
                    $datasetId
                ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'json' => [
                        'id' => $tableId,
                        'columns' => $this->reformatColumns($columns),
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function updateTable(string $datamodelId, string $datasetId, string $tableId, array $columns): array
    {
        try {
            $response = $this->client->patch(
                sprintf(
                    '%s/api/v2/datamodels/%s/schema/datasets/%s/tables/%s',
                    $this->config->getUrlAddress(),
                    $datamodelId,
                    $datasetId,
                    $tableId
                ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'json' => [
                        'columns' => $this->reformatColumns($columns),
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function createRelationship(string $datamodelId, array $sourceData, array $targetData): array
    {
        try {
            $response = $this->client->post(
                sprintf(
                    '%s/api/v2/datamodels/%s/schema/relations',
                    $this->config->getUrlAddress(),
                    $datamodelId
                ),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'json' => [
                        'columns' => [
                            $sourceData,
                            $targetData,
                        ],
                    ],
                ]
            );
        } catch (ClientException | ServerException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function build(string $datamodelId, string $type): string
    {
        $response = $this->client->post(
            sprintf(
                '%s/api/v2/builds',
                $this->config->getUrlAddress(),
            ),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'json' => [
                    'datamodelId' => $datamodelId,
                    'buildType' => $type,
                    'rowLimit' => 0,
                ],
            ]
        );

        $responseJson = json_decode($response->getBody()->getContents(), true);
        return $responseJson['oid'];
    }

    public function checkBuild(string $buildId): ?string
    {
        $response = $this->client->get(
            sprintf(
                '%s/api/v2/builds/%s',
                $this->config->getUrlAddress(),
                $buildId
            ),
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
            ]
        );

        $responseJson = json_decode($response->getBody()->getContents(), true);
        return is_null($responseJson['status']) ? 'waiting' : $responseJson['status'];
    }

    public function getDatasetName(string $datamodelName, string $tableName): string
    {
        return sprintf('%s-%s', $datamodelName, $tableName);
    }

    private function validateFile(string $dataDir, string $filename): string
    {
        try {
            $response = $this->client->post(
                sprintf('%s/storage/fs/validate_file', $this->config->getUrlAddress()),
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->accessToken,
                    ],
                    'json' => [
                        'filename' => $filename,
                        'size' => filesize($dataDir . $filename),
                    ],
                ]
            );
        } catch (ClientException $exception) {
            throw new UserException($exception->getMessage(), $exception->getCode(), $exception);
        }
        if ($response->getStatusCode() !== 200) {
            throw new UserException('Something has wrong. Please try again later.');
        }

        $responseJson = json_decode($response->getBody()->getContents(), true);
        return $responseJson['token'];
    }

    private function reformatColumns(array $columns): array
    {
        $reformatColumns = [];
        foreach ($columns as $column) {
            $reformatColumn = [
                'id' => $column['id'],
                'name' => $column['name'],
                'type' => $this->getType($column['type']),
                'hidden' => false,
                'indexed' => false,
                'description' => null,
                'import' => true,
                'isCustom' => false,
                'expression' => null,
            ];
            $reformatColumn = array_merge($reformatColumn, $this->getLength($column['size']));

            $reformatColumns[] = $reformatColumn;
        }
        return $reformatColumns;
    }

    private function getType(string $type): int
    {
        switch (strtoupper($type)) {
            case 'BIGINT':
                return 0;
            case 'BIT':
                return 2;
            case 'CHAR':
                return 3;
            case 'DATE':
                return 31;
            case 'DATETIME':
                return 4;
            case 'DECIMAL':
                return 5;
            case 'FLOAT':
                return 6;
            case 'INT':
            case 'INTEGER':
                return 8;
            case 'SMALLINT':
                return 16;
            case 'TEXT':
                return 18;
            case 'TIME':
                return 32;
            case 'TIMESTAMP':
                return 19;
            case 'TINYINT':
                return 20;
            case 'VARCHAR':
                return 22;
            default:
                throw new UserException(sprintf('Unrecognized column type "%s"', $type));
        }
    }

    private function getLength(string $length): array
    {
        if (strpos($length, ',')) {
            $size = 0;
            [$precision, $scale] = explode(',', $length);
        } else {
            $size = (int) $length;
            $precision = 0;
            $scale = 0;
        }

        return [
            'size' => $size,
            'precision' => $precision,
            'scale' => $scale,
        ];
    }
}
